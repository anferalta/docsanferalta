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

        // Comando mysqldump
        $cmd = "mysqldump -h $host -u $user --password=\"$pass\" $name > $path";

        exec($cmd);

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