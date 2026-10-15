<?php

namespace App\Services;

use ZipArchive;
use PDO;

class DatabaseBackupService
{
    private string $baseDir;
    private string $logFile;
    private PDO $pdo;

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

        // Conexão PDO
        $host = $_ENV['DB_HOST'];
        $db   = $_ENV['DB_NAME'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];

        $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

        $this->pdo = new PDO($dsn, $user, $pass, [
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    }

    public function criar(): string
    {
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

        // Caminho do SQL temporário
        $tempSQL = $dir . 'backup_db_' . date('Y-m-d_H-i-s') . '.sql';

        // Gerar SQL via PDO
        $sql = $this->gerarDumpPDO();
        file_put_contents($tempSQL, $sql);

        if (!$this->validarSQL($tempSQL)) {
            unlink($tempSQL);
            throw new \Exception("Backup inválido — SQL corrompido ou incompleto.");
        }

        // Criar ZIP final
        $zipPath = $dir . 'backup_db_' . date('Y-m-d_H-i-s') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE) !== true) {
            unlink($tempSQL);
            throw new \Exception("Falha ao criar ZIP.");
        }

        // Adicionar SQL ao ZIP com nome padrão
        $zip->addFile($tempSQL, "backup.sql");

        // Encriptar dentro do ZIP (AES‑256)
        $password = $_ENV['BACKUP_PASSWORD'] ?? null;
        if ($password) {
            $zip->setEncryptionName("backup.sql", ZipArchive::EM_AES_256, $password);
        }

        $zip->close();
        unlink($tempSQL);

        // Limpeza automática (manter 30 dias)
        $this->limparAntigos($this->baseDir, 30);

        $this->log("Backup criado com sucesso: $zipPath");

        return $zipPath;
    }

    private function gerarDumpPDO(): string
    {
        $sql = "-- Backup gerado via PDO\n";
        $sql .= "-- Data: " . date('Y-m-d H:i:s') . "\n\n";

        $tabelas = $this->pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        foreach ($tabelas as $tabela) {

            // Estrutura
            $create = $this->pdo->query("SHOW CREATE TABLE `$tabela`")->fetch(PDO::FETCH_ASSOC);
            $sql .= "\n\n-- Estrutura da tabela `$tabela`\n";
            $sql .= $create['Create Table'] . ";\n\n";

            // Dados
            $rows = $this->pdo->query("SELECT * FROM `$tabela`")->fetchAll(PDO::FETCH_ASSOC);

            if (!empty($rows)) {
                $sql .= "-- Dados da tabela `$tabela`\n";

                foreach ($rows as $row) {
                    $cols = array_map(fn($c) => "`$c`", array_keys($row));

                    $vals = array_map(function($v) {
                        if ($v === null) {
                            return "NULL";
                        }
                        return $this->pdo->quote($v);
                    }, array_values($row));

                    $sql .= "INSERT INTO `$tabela` (" . implode(',', $cols) . ") VALUES (" . implode(',', $vals) . ");\n";
                }
            }
        }

        return $sql;
    }

    private function temEspaco(int $minMB): bool
    {
        $livre = disk_free_space(__DIR__);
        return ($livre / 1024 / 1024) > $minMB;
    }

    private function validarSQL(string $ficheiro): bool
    {
        return file_exists($ficheiro) && filesize($ficheiro) > 1024;
    }

    private function limparAntigos(string $baseDir, int $dias): void
    {
        $limite = strtotime("-{$dias} days");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $ficheiro) {
            if ($ficheiro->isFile() && strtolower($ficheiro->getExtension()) === 'zip') {
                if ($ficheiro->getMTime() < $limite) {
                    unlink($ficheiro->getPathname());
                }
            }
        }
    }

    private function log(string $msg): void
    {
        file_put_contents(
            $this->logFile,
            "[" . date('Y-m-d H:i:s') . "] $msg\n",
            FILE_APPEND
        );
    }
}
