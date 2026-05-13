<?php

namespace App\Services;

class FileBackupService
{
    private string $baseDir;
    private string $sourceDir;
    private string $logFile;

    public function __construct()
    {
        // Diretório onde os backups de ficheiros são guardados
        $this->baseDir = realpath(__DIR__ . '/../../backups/Ficheiros') ?: (__DIR__ . '/../../backups/Ficheiros');
        if (!is_dir($this->baseDir)) mkdir($this->baseDir, 0777, true);
        $this->baseDir = rtrim(str_replace('\\', '/', $this->baseDir), '/') . '/';

        // Diretório REAL dos ficheiros do site (uploads)
        $this->sourceDir = realpath(__DIR__ . '/../../storage/uploads');
        if (!$this->sourceDir) {
            throw new \Exception("Pasta de ficheiros não encontrada.");
        }
        $this->sourceDir = rtrim(str_replace('\\', '/', $this->sourceDir), '/') . '/';

        // Logs
        $logDir = __DIR__ . '/../../backups/logs';
        if (!is_dir($logDir)) mkdir($logDir, 0777, true);
        $this->logFile = rtrim(str_replace('\\', '/', realpath($logDir)), '/') . '/backup_files.log';
    }

    public function criar(): string
    {
        // Subpastas ano/mês
        $ano = date('Y');
        $mes = date('m');
        $dir = "{$this->baseDir}{$ano}/{$mes}/";

        if (!is_dir($dir)) mkdir($dir, 0777, true);
        $dir = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';

        // Nome do ZIP
        $nome = 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
        $ficheiroZIP = $dir . $nome;

        // Criar ZIP
        $zip = new \ZipArchive();
        if ($zip->open($ficheiroZIP, \ZipArchive::CREATE) !== true) {
            throw new \Exception("Não foi possível criar o ZIP.");
        }

        // Adicionar ficheiros ao ZIP
        $this->adicionarPastaAoZip($this->sourceDir, $zip);

        $zip->close();

        // Limpeza automática (30 dias)
        $this->limparAntigos($this->baseDir, 30);

        $this->log("Backup de ficheiros criado: $ficheiroZIP");

        return $ficheiroZIP;
    }

    private function adicionarPastaAoZip(string $pasta, \ZipArchive $zip): void
    {
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($pasta, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $ficheiro) {
            $path = str_replace('\\', '/', $ficheiro->getPathname());

            // Caminho relativo correto
            $local = ltrim(str_replace($this->sourceDir, '', $path), '/');

            if ($ficheiro->isDir()) {
                $zip->addEmptyDir($local);
            } else {
                $zip->addFile($path, $local);
            }
        }
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

    private function log(string $msg): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        file_put_contents($this->logFile, $linha, FILE_APPEND);
    }
}