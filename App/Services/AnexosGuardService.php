<?php

namespace App\Services;

use App\Services\BackupLogger;
use App\Services\PathGuardService;

class AnexosGuardService
{

    private string $baseDir;
    private string $logFile;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);

        $this->baseDir = $root . '/storage/documentos';
        $this->logFile = $root . '/storage/logs/anexos_guard.log';

        // Inicializar proteção global
        PathGuardService::init();

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
            file_put_contents($this->baseDir . '/.keep', 'sentinel');
        }

        // proteção só para leitura futura
        PathGuardService::proteger($this->baseDir);
    }

    /**
     * Protege toda a estrutura: ano/mês/dia
     */
    public function protegerEstruturaCompleta(): void
    {
        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        $this->protegerPasta($this->baseDir);
        $this->protegerPasta("{$this->baseDir}/{$ano}");
        $this->protegerPasta("{$this->baseDir}/{$ano}/{$mes}");
        $this->protegerPasta("{$this->baseDir}/{$ano}/{$mes}/{$dia}");
    }

    /**
     * Protege uma pasta específica
     */
    private function protegerPasta(string $dir): void
    {
        if (!is_dir($dir)) {
            $this->log("Pasta não existia, criada: {$dir}");
            mkdir($dir, 0777, true);
            file_put_contents($dir . '/.keep', 'sentinel');
            return;
        }

        // Blindagem apenas depois de existir
        PathGuardService::proteger($dir);

        // Verificar permissões
        if (!is_writable($dir)) {
            $this->log("Pasta sem permissões de escrita: {$dir}");
            BackupLogger::registar('ANEXOS', $dir, false, "Pasta sem permissões: {$dir}");
        }

        // Verificar se está vazia
        $conteudo = array_diff(scandir($dir), ['.', '..']);
        if (empty($conteudo)) {
            $this->log("Pasta existe mas está vazia: {$dir}");
            file_put_contents($dir . '/.keep', 'sentinel');
        }
    }

    /**
     * Verificar integridade de ficheiros
     */
    public function validarIntegridade(string $path): bool
    {
        // Blindagem absoluta
        PathGuardService::proteger($path);

        if (!file_exists($path)) {
            $this->log("Ficheiro desapareceu: {$path}");
            BackupLogger::registar('ANEXOS', $path, false, "Ficheiro desaparecido: {$path}");
            return false;
        }

        if (filesize($path) === 0) {
            $this->log("Ficheiro corrompido (0 bytes): {$path}");
            BackupLogger::registar('ANEXOS', $path, false, "Ficheiro corrompido: {$path}");
            return false;
        }

        return true;
    }

    private function log(string $msg): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        @file_put_contents($this->logFile, $linha, FILE_APPEND);
    }
}
