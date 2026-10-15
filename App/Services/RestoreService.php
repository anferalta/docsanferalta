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
        $comandos = $this->dividirSQL($conteudo);

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
    private function dividirSQL(string $sql): array
    {
        $sql = str_replace("\r", "\n", $sql);

        $comandos = preg_split('/;\s*\n/', $sql);
        $final = [];

        foreach ($comandos as $cmd) {
            $cmd = trim($cmd);
            if ($cmd === '') continue;

            // Ignorar comandos de estrutura
            if (preg_match('/^(CREATE|DROP|ALTER|LOCK|UNLOCK|SET)\s/i', $cmd)) {
                continue;
            }

            // Apenas comandos de dados
            if (preg_match('/^(INSERT|UPDATE|DELETE)\s/i', $cmd)) {

                // INSERT IGNORE
                if (preg_match('/^INSERT\s+/i', $cmd)) {
                    $cmd = preg_replace('/^INSERT\s+/i', 'INSERT IGNORE ', $cmd);
                }

                $final[] = $cmd;
            }
        }

        return $final;
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
     * - Extrai ZIP para STORAGE_PUBLIC_UPLOADS
     */
    public function restaurarFiles(string $path): void
    {
        PathGuardService::init();
        PathGuardService::proteger($path);

        if (!is_file($path)) {
            throw new Exception("Backup de ficheiros não encontrado: {$path}");
        }

        $destino = STORAGE_PUBLIC_UPLOADS;

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
