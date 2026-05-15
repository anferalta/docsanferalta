<?php

namespace App\Services;

class FileBackupService
{
    private string $baseDir;
    private string $sourceDir;
    private string $hashFile;

    public function __construct()
    {
        // Pasta correta dos ficheiros a fazer backup
        $this->sourceDir = realpath(__DIR__ . '/../../storage/uploads/documentos')
            ?: (__DIR__ . '/../../storage/uploads/documentos');

        $this->sourceDir = rtrim(str_replace('\\', '/', $this->sourceDir), '/') . '/';

        if (!is_dir($this->sourceDir)) {
            throw new \Exception("A pasta de origem para backup não existe: {$this->sourceDir}");
        }

        // Pasta base dos backups
        $this->baseDir = realpath(__DIR__ . '/../../backups/Ficheiros')
            ?: (__DIR__ . '/../../backups/Ficheiros');

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }

        $this->baseDir = rtrim(str_replace('\\', '/', $this->baseDir), '/') . '/';

        // Ficheiro de hashes para backup incremental
        $this->hashFile = __DIR__ . '/../../backups/hashes_files.json';
    }

    public function criar(): string
    {
        // Subpastas ano/mês
        $ano = date('Y');
        $mes = date('m');

        $dir = "{$this->baseDir}{$ano}/{$mes}/";

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $dir = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';

        // Nome do ZIP
        $nome = 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $dir . $nome;

        $zip = new \ZipArchive();
        if ($zip->open($zipPath, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Não foi possível criar o ZIP de backup.");
        }

        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {
            if ($file->isDir()) continue;

            $filePath = $file->getRealPath();
            if (!$filePath) continue;

            $filePath = str_replace('\\', '/', $filePath);
            $relative = substr($filePath, strlen($this->sourceDir));

            // --- BACKUP INCREMENTAL ---
            $hashAtual = md5_file($filePath);
            $hashAnterior = $this->lerHashAnterior($relative);

            if ($hashAtual === $hashAnterior) {
                continue; // ficheiro não mudou
            }

            $this->guardarHash($relative, $hashAtual);
            // --------------------------

            $zip->addFile($filePath, $relative);
        }

        $zip->close();

        // Limpeza automática (30 dias)
        $this->limparAntigos($this->baseDir, 30);

        return $zipPath;
    }

    private function lerHashAnterior(string $ficheiro): ?string
    {
        if (!file_exists($this->hashFile)) return null;

        $hashes = json_decode(file_get_contents($this->hashFile), true);
        return $hashes[$ficheiro] ?? null;
    }

    private function guardarHash(string $ficheiro, string $hash): void
    {
        $hashes = [];

        if (file_exists($this->hashFile)) {
            $hashes = json_decode(file_get_contents($this->hashFile), true) ?? [];
        }

        $hashes[$ficheiro] = $hash;

        file_put_contents($this->hashFile, json_encode($hashes, JSON_PRETTY_PRINT));
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

            if ($ficheiro->isDir()) {
                @rmdir($ficheiro->getPathname());
            }
        }
    }
}
