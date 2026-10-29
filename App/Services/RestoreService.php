<?php

namespace App\Services;

use Exception;
use ZipArchive;
use PDO;
use App\Services\PathGuardService;
use App\Services\BackupLogger;
use App\Services\DatabaseBackupService;

class RestoreService
{
    private PDO $pdo;

    public function __construct()
    {
        $host = $_ENV['DB_HOST'];
        $db   = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    /**
     * RESTAURO SEGURO DA BASE DE DADOS
     * - Cria backup automático antes
     * - Extrai ZIP para pasta temporária
     * - Lê backup.sql
     * - Divide em comandos seguros
     * - Executa apenas INSERT/UPDATE/DELETE
     * - INSERT → INSERT IGNORE
     */
    public function restaurarBD(string $path): void
    {
        PathGuardService::init();
        PathGuardService::proteger($path);

        if (!is_file($path)) {
            throw new Exception("Ficheiro de backup não encontrado: {$path}");
        }

        // 1️⃣ Backup automático antes do restauro
        $backupAntes = (new DatabaseBackupService())->criar();
        BackupLogger::registar('BD', $backupAntes, true, "Backup automático antes do restauro.");

        // 2️⃣ Extrair ZIP para pasta temporária
        $tempDir = STORAGE_TEMP . '/restore_db_' . uniqid();
        mkdir($tempDir, 0777, true);

        PathGuardService::proteger($tempDir);

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ZIP.");
        }

        $zip->extractTo($tempDir);
        $zip->close();

        // 3️⃣ Encontrar backup.sql
        $sqlFiles = glob($tempDir . '/*.sql');
        if (empty($sqlFiles)) {
            throw new Exception("Nenhum ficheiro SQL encontrado no backup.");
        }

        $sqlFile = $sqlFiles[0];
        PathGuardService::proteger($sqlFile);

        // 4️⃣ Validar SQL
        if (!$this->validarSQL($sqlFile)) {
            throw new Exception("SQL inválido ou corrompido.");
        }

        // 5️⃣ Ler conteúdo SQL
        $conteudo = file_get_contents($sqlFile);

        // 6️⃣ Dividir em comandos seguros
        $colunasBackup = $this->extrairColunasBackup($conteudo);
        $comandos = $this->dividirSQL($conteudo, $colunasBackup);

        // 7️⃣ Executar comandos via PDO
        foreach ($comandos as $cmd) {
            if (trim($cmd) === '') continue;

            try {
                $this->pdo->exec($cmd);
            } catch (Exception $e) {
                throw new Exception("Erro ao executar comando SQL: " . $e->getMessage());
            }
        }

        // Marcar que houve restauro
        $_SESSION['restauro_bd_feito'] = true;

        // 8️⃣ Limpar pasta temporária
        foreach (glob($tempDir . '/*') as $f) {
            @unlink($f);
        }
        @rmdir($tempDir);
    }

    /**
     * Divide SQL em comandos seguros
     */
    private function dividirSQL(string $sql, array $colunasBackup = []): array
    {
        $sql = str_replace("\r", "\n", $sql);

        $comandos = preg_split('/;\s*\n/', $sql);
        $final = [];

        foreach ($comandos as $cmd) {
            $cmd = trim($cmd);
            $cmd = $this->removerComentariosIniciais($cmd);
            if ($cmd === '') continue;

            // Ignorar comandos de estrutura
            if (preg_match('/^(CREATE|DROP|ALTER|LOCK|UNLOCK|SET)\s/i', $cmd)) {
                continue;
            }

            // Apenas comandos de dados
            if (preg_match('/^(INSERT|UPDATE|DELETE)\s/i', $cmd)) {

                // Backups antigos não indicavam as colunas no INSERT. Como o
                // restauro mantém a estrutura atual, acrescentamos as colunas
                // da estrutura guardada no próprio backup para evitar que uma
                // alteração posterior da tabela cause erro 1136.
                $cmd = $this->adicionarColunasAoInsertLegado($cmd, $colunasBackup);

                // INSERT IGNORE
                if (preg_match('/^INSERT\s+(?!IGNORE\b)/i', $cmd)) {
                    $cmd = preg_replace('/^INSERT\s+/i', 'INSERT IGNORE ', $cmd);
                }

                $final[] = $cmd;
            }
        }

        return $final;
    }

    /** Obtém os nomes das colunas a partir dos CREATE TABLE guardados no dump. */
    private function extrairColunasBackup(string $sql): array
    {
        $colunasPorTabela = [];
        $padrao = '/CREATE\s+TABLE\s+(?:IF\s+NOT\s+EXISTS\s+)?`((?:``|[^`])+)`\s*\(/i';

        if (!preg_match_all($padrao, $sql, $matches, PREG_OFFSET_CAPTURE)) {
            return $colunasPorTabela;
        }

        foreach ($matches[0] as $indice => $correspondencia) {
            $cabecalho = $correspondencia[0];
            $inicio = $correspondencia[1] + strlen($cabecalho);
            $profundidade = 1;
            $aspas = null;
            $escape = false;
            $fim = null;

            for ($i = $inicio, $comprimento = strlen($sql); $i < $comprimento; $i++) {
                $caractere = $sql[$i];

                if ($aspas !== null) {
                    if ($escape) {
                        $escape = false;
                    } elseif ($caractere === '\\' && $aspas !== '`') {
                        $escape = true;
                    } elseif ($caractere === $aspas) {
                        if ($aspas === '`' && $i + 1 < $comprimento && $sql[$i + 1] === '`') {
                            $i++;
                        } else {
                            $aspas = null;
                        }
                    }
                    continue;
                }

                if ($caractere === "'" || $caractere === '"' || $caractere === '`') {
                    $aspas = $caractere;
                } elseif ($caractere === '(') {
                    $profundidade++;
                } elseif ($caractere === ')' && --$profundidade === 0) {
                    $fim = $i;
                    break;
                }
            }

            if ($fim === null) {
                continue;
            }

            $corpo = substr($sql, $inicio, $fim - $inicio);
            preg_match_all('/^\s*`((?:``|[^`])+)`\s+/m', $corpo, $colunas);
            $nomes = array_map(static fn($nome) => str_replace('``', '`', $nome), $colunas[1]);
            $tabela = str_replace('``', '`', $matches[1][$indice][0]);

            if ($nomes !== []) {
                $colunasPorTabela[strtolower($tabela)] = $nomes;
            }
        }

        return $colunasPorTabela;
    }

    private function adicionarColunasAoInsertLegado(string $comando, array $colunasBackup): string
    {
        if (!preg_match(
            '/^(INSERT\s+(?:IGNORE\s+)?INTO\s+)(`((?:``|[^`])+)`|([a-zA-Z0-9_.$]+))\s+(VALUES|VALUE)\b/i',
            $comando,
            $partes
        )) {
            return $comando;
        }

        $tabela = str_replace('``', '`', $partes[3] !== '' ? $partes[3] : $partes[4]);
        $colunas = $colunasBackup[strtolower($tabela)] ?? null;

        if (!$colunas) {
            throw new Exception("O backup não inclui a estrutura necessária para restaurar a tabela '{$tabela}'.");
        }

        $lista = implode(',', array_map(
            static fn($coluna) => '`' . str_replace('`', '``', $coluna) . '`',
            $colunas
        ));

        return substr_replace(
            $comando,
            $partes[1] . $partes[2] . ' (' . $lista . ') ' . $partes[5],
            0,
            strlen($partes[0])
        )
            . substr($comando, strlen($partes[0]));
    }

    /** Remove comentários que precedem um comando SQL no dump. */
    private function removerComentariosIniciais(string $comando): string
    {
        do {
            $anterior = $comando;
            $comando = preg_replace('/^\s*(?:--[^\n]*(?:\n|$)|#[^\n]*(?:\n|$)|\/\*.*?\*\/)/s', '', $comando);
        } while ($comando !== $anterior);

        return trim($comando);
    }

    /**
     * Validação básica do SQL
     */
    private function validarSQL(string $ficheiro): bool
    {
        if (!file_exists($ficheiro) || filesize($ficheiro) < 1024) {
            return false;
        }

        $conteudo = file_get_contents($ficheiro);

        return (
            str_contains($conteudo, 'CREATE TABLE') ||
            str_contains($conteudo, 'INSERT INTO')
        );
    }

    /**
     * RESTAURO DE FICHEIROS
     * - Extrai ZIP para STORAGE_DOCUMENTOS, a mesma origem usada no backup
     */
    public function restaurarFiles(string $path): void
    {
        PathGuardService::init();
        PathGuardService::proteger($path);

        if (!is_file($path)) {
            throw new Exception("Backup de ficheiros não encontrado: {$path}");
        }

        $destino = STORAGE_DOCUMENTOS;

        if (!is_dir($destino)) {
            mkdir($destino, 0777, true);
        }

        PathGuardService::proteger($destino);

        $zip = new ZipArchive();
        if ($zip->open($path) !== true) {
            throw new Exception("Não foi possível abrir o ZIP.");
        }

        $zip->extractTo($destino);
        $zip->close();
    }
}
