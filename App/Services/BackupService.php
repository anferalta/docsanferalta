<?php

namespace App\Services;

class BackupService
{

    public static function backupDatabase(): string
    {
        $host = $_ENV['DB_HOST'];
        $user = $_ENV['DB_USER'];
        $pass = $_ENV['DB_PASS'];
        $name = $_ENV['DB_NAME'];

        $filename = "backup_db_" . date('Y-m-d_H-i-s') . ".sql";
        $path = __DIR__ . '/../../backups/db/' . $filename;

        // Caminho completo do mysqldump
        $mysqldump = "C:\\wamp64\\bin\\mysql\\mysql8.0.36\\bin\\mysqldump.exe";

        // Comando
        $cmd = "\"$mysqldump\" -h $host -u $user --password=\"$pass\" $name > \"$path\" 2>&1";

        $output = [];
        $return = 0;

        exec($cmd, $output, $return);

        if ($return !== 0) {
            throw new \Exception("mysqldump falhou:\n" . implode("\n", $output));
        }

        return $filename;
    }

    public static function backupFiles(): string
    {
        $source = __DIR__ . '/../../uploads';
        $filename = "backup_files_" . date('Y-m-d_H-i-s') . ".zip";
        $path = __DIR__ . '/../../backups/files/' . $filename;

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

        $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($source),
                \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if (!$file->isDir()) {
                $filePath = $file->getRealPath();
                $relative = substr($filePath, strlen($source) + 1);
                $zip->addFile($filePath, $relative);
            }
        }

        $zip->close();

        return $filename;
    }
}
