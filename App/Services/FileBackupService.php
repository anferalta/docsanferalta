<?php

namespace App\Services;

use ZipArchive;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class FileBackupService
{
    private string $baseDir;
    private string $sourceDir;
    private string $hashFile;

    public function __construct()
    {
        PathGuardService::init();

        // PASTA CORRETA DOS DOCUMENTOS
        $this->sourceDir = realpath(__DIR__ . '/../../storage/documentos') 
            ?: (__DIR__ . '/../../storage/documentos');

        $this->sourceDir = rtrim(str_replace('\\', '/', $this->sourceDir), '/') . '/';

        // 🔒 Proteger pasta de origem
        PathGuardService::proteger($this->sourceDir);

        if (!is_dir($this->sourceDir)) {
            throw new \Exception("A pasta de origem para backup não existe: {$this->sourceDir}");
        }

        // Pasta base dos backups
        $this->baseDir = realpath(__DIR__ . '/../../backups/Ficheiros') 
            ?: (__DIR__ . '/../../backups/Ficheiros');

        // 🔒 Proteger pasta de destino
        PathGuardService::proteger($this->baseDir);

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
            file_put_contents($this->baseDir . '/.keep', 'sentinel');
        }

        $this->baseDir = rtrim(str_replace('\\', '/', $this->baseDir), '/') . '/';

        // Ficheiro de hashes para backup incremental
        $this->hashFile = __DIR__ . '/../../backups/hashes_files.json';

        // 🔒 Proteger ficheiro de hashes
        PathGuardService::proteger($this->hashFile);
    }

    public function criar(): string
    {
        // Verificar espaço mínimo (200 MB)
        if (!$this->temEspaco(200)) {
            throw new \Exception("Espaço insuficiente para criar backup de ficheiros.");
        }

        // Subpastas ano/mês
        $ano = date('Y');
        $mes = date('m');

        $dir = "{$this->baseDir}{$ano}/{$mes}/";

        // 🔒 Proteger diretório antes de criar
        PathGuardService::proteger($dir);

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/.keep', 'sentinel');
        }

        $dir = rtrim(str_replace('\\', '/', realpath($dir)), '/') . '/';

        // Nome do ZIP
        $nome = 'backup_files_' . date('Y-m-d_H-i-s') . '.zip';
        $zipPath = $dir . $nome;

        // 🔒 Proteger ZIP antes de criar
        PathGuardService::proteger($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Não foi possível criar o ZIP de backup.");
        }

        $totalAdicionados = 0;

        // ITERAR DOCUMENTOS REAIS
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY
        );

        foreach ($files as $file) {

            if ($file->isDir()) {
                continue;
            }

            $filePath = str_replace('\\', '/', $file->getRealPath());
            $relative = substr($filePath, strlen($this->sourceDir));

            // 🔒 Proteger ficheiro antes de ler
            PathGuardService::proteger($filePath);

            // Ignorar ficheiros corrompidos ou vazios
            if (!is_readable($filePath) || filesize($filePath) === 0) {
                BackupLogger::registar('BACKUP', $filePath, false, "Ficheiro ignorado (corrompido ou vazio)");
                continue;
            }

            // BACKUP INCREMENTAL
            $hashAtual = md5_file($filePath);
            $hashAnterior = $this->lerHashAnterior($relative);

            if ($hashAtual === $hashAnterior) {
                continue;
            }

            $this->guardarHash($relative, $hashAtual);

            if (!$zip->addFile($filePath, $relative)) {
                throw new \Exception("Erro ao adicionar ficheiro ao ZIP: {$relative}");
            }

            $totalAdicionados++;
        }

        $zip->close();

        // Proteger contra ZIP vazio
        if ($totalAdicionados === 0) {
            PathGuardService::proteger($zipPath);
            unlink($zipPath);
            throw new \Exception("Nenhum ficheiro novo ou alterado para backup.");
        }

        // Verificar integridade do ZIP
        if (!$this->validarZip($zipPath)) {
            PathGuardService::proteger($zipPath);
            unlink($zipPath);
            throw new \Exception("Backup inválido — ZIP corrompido.");
        }

        // Limpeza automática (30 dias)
        $this->limparAntigos($this->baseDir, 30);

        return $zipPath;
    }

    private function temEspaco(int $minMB): bool
    {
        $livre = disk_free_space(__DIR__);
        return ($livre / 1024 / 1024) > $minMB;
    }

    private function validarZip(string $ficheiro): bool
    {
        PathGuardService::proteger($ficheiro);

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

    private function lerHashAnterior(string $ficheiro): ?string
    {
        PathGuardService::proteger($this->hashFile);

        if (!file_exists($this->hashFile)) {
            return null;
        }

        $hashes = json_decode(file_get_contents($this->hashFile), true);
        return $hashes[$ficheiro] ?? null;
    }

    private function guardarHash(string $ficheiro, string $hash): void
    {
        PathGuardService::proteger($this->hashFile);

        $hashes = [];

        if (file_exists($this->hashFile)) {
            $hashes = json_decode(file_get_contents($this->hashFile), true) ?? [];
        }

        $hashes[$ficheiro] = $hash;

        file_put_contents($this->hashFile, json_encode($hashes, JSON_PRETTY_PRINT));
    }

    private function limparAntigos(string $baseDir, int $dias): void
    {
        PathGuardService::proteger($baseDir);

        $limite = strtotime("-{$dias} days");

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($baseDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $ficheiro) {

            $path = $ficheiro->getPathname();

            // 🔒 Proteger antes de apagar
            PathGuardService::proteger($path);

            // Apagar apenas ZIPs antigos
            if ($ficheiro->isFile() && strtolower($ficheiro->getExtension()) === 'zip') {
                if ($ficheiro->getMTime() < $limite) {
                    unlink($path);
                }
            }

            // Nunca apagar diretórios automaticamente
        }
    }
}
