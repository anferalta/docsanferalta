<?php

namespace App\Services;

class DatabaseBackupService
{
    private string $baseDir;
    private string $logFile;

    public function __construct()
    {
        // Diretório base dos backups
        $this->baseDir = realpath(__DIR__ . '/../../backups/BaseDados') ?: (__DIR__ . '/../../backups/BaseDados');

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

    public function criar(): string
    {
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

        // Credenciais — SEM Env::get()
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

        // Criar ZIP
        $ficheiroZIP = $this->comprimirZip($ficheiroSQL);

        // Limpeza automática (manter 30 dias)
        $this->limparAntigos($this->baseDir, 30);

        $this->log("Backup criado e comprimido com sucesso: $ficheiroZIP");

        return $ficheiroZIP;
    }

    private function detetarMysqldump(): ?string
    {
        // 1) .env — SEM Env::get()
        $envPath = $_ENV['MYSQLDUMP_PATH'] ?? null;
        if ($envPath && file_exists($envPath)) {
            return $envPath;
        }

        // 2) Caminhos típicos do WAMP/XAMPP
        $possiveis = [
            'C:\\wamp64\\bin\\mysql\\mysql9.1.0\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.31\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql8.0.30\\bin\\mysqldump.exe',
            'C:\\wamp64\\bin\\mysql\\mysql5.7.31\\bin\\mysqldump.exe',
            'C:\\xampp\\mysql\\bin\\mysqldump.exe',
        ];

        foreach ($possiveis as $p) {
            if (file_exists($p)) {
                return $p;
            }
        }

        // 3) Linux
        $which = trim(shell_exec('which mysqldump 2>/dev/null') ?? '');
        if ($which !== '' && file_exists($which)) {
            return $which;
        }

        return null;
    }

    private function comprimirZip(string $ficheiroSQL): string
    {
        $zipPath = $ficheiroSQL . '.zip';

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE) === true) {
            $zip->addFile($ficheiroSQL, basename($ficheiroSQL));
            $zip->close();

            unlink($ficheiroSQL);

            return $zipPath;
        }

        throw new \Exception("Falha ao criar ZIP.");
    }

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
        }
    }

    private function log(string $mensagem): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
        file_put_contents($this->logFile, $linha, FILE_APPEND);
    }
}