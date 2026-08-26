<?php

namespace App\Services;

use ZipArchive;

class DatabaseBackupService
{
    private string $baseDir;
    private string $logFile;

    public function __construct()
    {
        // Diretório base dos backups
        $this->baseDir = realpath(__DIR__ . '/../../backups/BaseDados')
            ?: (__DIR__ . '/../../backups/BaseDados');

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        $this->baseDir = rtrim(str_replace('\\', '/', $this->baseDir), '/') . '/';

        // Diretório dos logs
        $logDir = __DIR__ . '/../../backups/logs';
        if (!is_dir($logDir)) {
            mkdir($logDir, 0777, true);
        }

        $this->logFile = rtrim(str_replace('\\', '/', realpath($logDir)), '/') . '/backup_db.log';
    }

    /**
     * Criar backup completo da base de dados
     */
    public function criar(): string
    {
        // Verificar espaço mínimo (200 MB)
        if (!$this->temEspaco(200)) {
            throw new \Exception("Espaço insuficiente para criar backup da base de dados.");
        }

        // Criar subpastas ano/mês
        $ano = date('Y');
        $mes = date('m');

        $dir = "{$this->baseDir}{$ano}/{$mes}/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $dir = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';

        // Nome do ficheiro SQL
        $nome = 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';
        $ficheiroSQL = $dir . $nome;

        // Credenciais
        $host = $_ENV['DB_HOST'] ?? 'localhost';
        $db   = $_ENV['DB_NAME'] ?? 'anferaltadocs';
        $user = $_ENV['DB_USER'] ?? 'root';
        $pass = $_ENV['DB_PASS'] ?? '';

        // Encontrar mysqldump
        $mysqldump = $this->detetarMysqldump();
        if (!$mysqldump) {
            $this->log("mysqldump não encontrado.");
            throw new \Exception("mysqldump não encontrado.");
        }

        // Comando
        $isWindows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';

        if ($isWindows) {
            $cmd = "\"{$mysqldump}\" --host={$host} --user={$user} --password={$pass} {$db} > \"{$ficheiroSQL}\"";
        } else {
            $cmd = sprintf(
                '%s -h%s -u%s -p%s %s > %s',
                escapeshellarg($mysqldump),
                escapeshellarg($host),
                escapeshellarg($user),
                escapeshellarg($pass),
                escapeshellarg($db),
                escapeshellarg($ficheiroSQL)
            );
        }

        $this->log("A executar comando: $cmd");

        $out = [];
        $code = 0;
        exec($cmd, $out, $code);

        if ($code !== 0 || !is_file($ficheiroSQL)) {
            $this->log("Falha ao criar backup. Código: $code | Output: " . implode("\n", $out));
            throw new \Exception("Falha ao criar backup da base de dados.");
        }

        // Verificar integridade mínima do SQL
        if (!$this->validarSQL($ficheiroSQL)) {
            unlink($ficheiroSQL);
            throw new \Exception("Backup inválido — SQL corrompido ou incompleto.");
        }

        // Criar ZIP
        $ficheiroZIP = $this->comprimirZip($ficheiroSQL);

        // Verificar integridade do ZIP
        if (!$this->validarZip($ficheiroZIP)) {
            unlink($ficheiroZIP);
            throw new \Exception("Backup inválido — ZIP corrompido.");
        }

        // Teste automático de restauração (sandbox)
        if (!$this->testarRestauro($ficheiroZIP)) {
            unlink($ficheiroZIP);
            throw new \Exception("Backup inválido — falha no teste de restauração.");
        }

        // Encriptar ZIP (AES‑256)
        $ficheiroFinal = $this->encriptar($ficheiroZIP);

        // Limpeza automática (manter 30 dias)
        $this->limparAntigos($this->baseDir, 30);

        $this->log("Backup criado com sucesso: $ficheiroFinal");

        return $ficheiroFinal;
    }

    /**
     * Verificar espaço em disco
     */
    private function temEspaco(int $minMB): bool
    {
        $livre = disk_free_space(__DIR__);
        return ($livre / 1024 / 1024) > $minMB;
    }

    /**
     * Verificar integridade mínima do SQL
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
     * Verificar integridade do ZIP
     */
    private function validarZip(string $ficheiro): bool
    {
        if (!file_exists($ficheiro) || filesize($ficheiro) < 1024) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($ficheiro) !== true) {
            return false;
        }

        $ok = $zip->numFiles > 0;
        $zip->close();

        return $ok;
    }

    /**
     * Teste automático de restauração (sandbox)
     */
    private function testarRestauro(string $ficheiroZIP): bool
    {
        $tempDir = sys_get_temp_dir() . '/restore_test_' . uniqid();
        mkdir($tempDir);

        $zip = new ZipArchive();
        if ($zip->open($ficheiroZIP) !== true) {
            return false;
        }

        $zip->extractTo($tempDir);
        $zip->close();

        $sqlFiles = glob($tempDir . '/*.sql');
        if (empty($sqlFiles)) {
            return false;
        }

        $sql = $sqlFiles[0];

        // Criar BD temporária
        $tempDB = "test_restore_" . uniqid();

        $mysql = $this->detetarMysql();
        if (!$mysql) {
            return false;
        }

        $cmdCreate = "\"{$mysql}\" -u root -e \"CREATE DATABASE {$tempDB}\"";
        exec($cmdCreate);

        $cmdImport = "\"{$mysql}\" {$tempDB} < \"{$sql}\"";
        exec($cmdImport, $out, $code);

        // Apagar BD temporária
        exec("\"{$mysql}\" -u root -e \"DROP DATABASE {$tempDB}\"");

        // Limpar diretório temporário
        array_map('unlink', glob("$tempDir/*"));
        @rmdir($tempDir);

        return $code === 0;
    }

    /**
     * Encriptar ZIP com AES‑256
     */
    private function encriptar(string $zipPath): string
    {
        $password = $_ENV['BACKUP_PASSWORD'] ?? null;

        if (!$password) {
            return $zipPath;
        }

        $zipEnc = $zipPath . '.enc';

        $cmd = "openssl enc -aes-256-cbc -salt -in \"$zipPath\" -out \"$zipEnc\" -k \"$password\"";

        exec($cmd, $out, $code);

        if ($code !== 0 || !file_exists($zipEnc)) {
            throw new \Exception("Falha ao encriptar o backup.");
        }

        unlink($zipPath);

        return $zipEnc;
    }

    /**
     * DETETAR mysqldump.exe automaticamente
     */
    private function detetarMysqldump(): ?string
    {
        $envPath = $_ENV['MYSQLDUMP_PATH'] ?? null;
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        $possiveis = [
            'C:\\wamp\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ];

        foreach ($possiveis as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }

        $which = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
        return ($which !== '' && file_exists($which)) ? $which : null;
    }

    /**
     * DETETAR mysql.exe automaticamente (para RESTAURO)
     */
    public function detetarMysql(): ?string
    {
        $envPath = $_ENV['MYSQL_PATH'] ?? null;
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        $possiveis = [
            'C:\\wamp\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe',
            'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysql.exe',
            'C:\\xampp\\mysql\\bin\\mysql.exe',
        ];

        foreach ($possiveis as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }

        $which = trim(shell_exec('which mysql 2>/dev/null') ?? '');
        return ($which !== '' && file_exists($which)) ? $which : null;
    }

    /**
     * Criar ZIP
     */
    private function comprimirZip(string $ficheiroSQL): string
    {
        $zipPath = $ficheiroSQL . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) === true) {
            $zip->addFile($ficheiroSQL, basename($ficheiroSQL));
            $zip->close();

            unlink($ficheiroSQL);

            return $zipPath;
        }

        throw new \Exception("Falha ao criar ZIP.");
    }

    /**
     * Apagar backups antigos
     */
    private function limparAntigos(string $baseDir, int $dias): void
    {
        $limite = strtotime("-{$dias} days");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $ficheiro) {
            if ($ficheiro->isFile() && $ficheiro->getExtension() === 'zip') {
                if ($ficheiro->getMTime() < $limite) {
                    unlink($ficheiro->getPathname());
                }
            }

            if ($ficheiro->isDir()) {
                @rmdir($ficheiro->getPathname());
            }
        }
    }

    /**
     * Log
     */
    private function log(string $mensagem): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
        file_put_contents($this->logFile, $linha, FILE_APPEND);
    }
}
