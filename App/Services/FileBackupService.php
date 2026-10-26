<?php

namespace App\Services;

use ZipArchive;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class FileBackupService
{
    private string $baseDir;
    private string $sourceDir;

    public function __construct()
{
    PathGuardService::init();

    // Os documentos ficam no storage privado do projeto, não na raiz pública
    // do servidor web. DOCUMENT_ROOT varia conforme o virtual host e o rewrite.
    $this->sourceDir = rtrim(str_replace('\\', '/', STORAGE_DOCUMENTOS), '/') . '/';

    // 🔒 Proteger pasta de origem
    PathGuardService::proteger($this->sourceDir);

    if (!is_dir($this->sourceDir)) {
        throw new \Exception("A pasta de origem para backup não existe: {$this->sourceDir}");
    }

    // Pasta base dos backups (mantém em anferaltadocs/backups)
    $this->baseDir = realpath(__DIR__ . '/../../backups/Ficheiros') 
        ?: (__DIR__ . '/../../backups/Ficheiros');

    PathGuardService::proteger($this->baseDir);

    if (!is_dir($this->baseDir)) {
        mkdir($this->baseDir, 0777, true);
        file_put_contents($this->baseDir . '/.keep', 'sentinel');
    }

    $this->baseDir = rtrim(str_replace('\\', '/', $this->baseDir), '/') . '/';

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
        $nome = 'backup_files_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(3)) . '.zip';
        $zipPath = $dir . $nome;

        // 🔒 Proteger ZIP antes de criar
        PathGuardService::proteger($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception("Não foi possível criar o ZIP de backup.");
        }

        $totalAdicionados = 0;
        // Cada execução manual cria uma cópia completa e independente.
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

            if (!is_readable($filePath)) {
                $zip->close();
                throw new \Exception("Sem permissão para ler o ficheiro: {$relative}");
            }

            if (!$zip->addFile($filePath, $relative)) {
                $zip->close();
                throw new \Exception("Erro ao adicionar ficheiro ao ZIP: {$relative}");
            }

            $totalAdicionados++;
        }

        $fechoOk = $zip->close();
        if (!$this->validarZip($zipPath)) {
            $this->recuperarZipTemporario($zipPath);
        }

        // Proteger contra ZIP vazio
        if ($totalAdicionados === 0) {
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            throw new \Exception('A pasta de documentos não contém ficheiros para incluir no backup.');
        }

        // Verificar integridade do ZIP
        if (!$this->validarZip($zipPath)) {
            PathGuardService::proteger($zipPath);
            if (is_file($zipPath)) {
                @unlink($zipPath);
            }
            $motivo = $fechoOk
                ? 'Backup inválido — o ZIP não passou na verificação de integridade.'
                : 'O servidor não conseguiu finalizar o ZIP; verifique permissões e bloqueios na pasta de backups.';
            throw new \Exception($motivo);
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

        if (!is_file($ficheiro) || !is_readable($ficheiro) || filesize($ficheiro) === 0) {
            return false;
        }

        $zip = new ZipArchive();
        if ($zip->open($ficheiro, ZipArchive::CHECKCONS) !== true) {
            return false;
        }

        $ok = $zip->numFiles > 0;
        $fechoOk = $zip->close();

        return $ok && $fechoOk;
    }

    /** Tenta promover um ZIP temporário do libzip para o nome final. */
    private function recuperarZipTemporario(string $zipPath): bool
    {
        $diretorio = dirname($zipPath);
        $prefixo = basename($zipPath) . '.';
        $temporarios = [];

        foreach (new \DirectoryIterator($diretorio) as $item) {
            if (!$item->isFile() || !str_starts_with($item->getFilename(), $prefixo)) {
                continue;
            }

            $sufixo = substr($item->getFilename(), strlen($prefixo));
            if (preg_match('/^[a-f0-9]{8}$/i', $sufixo) && $item->getSize() > 0) {
                $temporarios[] = $item->getPathname();
            }
        }

        usort($temporarios, static fn(string $a, string $b): int => filemtime($b) <=> filemtime($a));

        foreach ($temporarios as $temporario) {
            if (!$this->validarZip($temporario)) {
                continue;
            }

            if (@rename($temporario, $zipPath) && $this->validarZip($zipPath)) {
                return true;
            }

            // Se o rename for bloqueado temporariamente no Windows, copiar e
            // validar o destino antes de aceitar o backup.
            if (@copy($temporario, $zipPath) && $this->validarZip($zipPath)) {
                @unlink($temporario);
                return true;
            }
        }

        return false;
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
