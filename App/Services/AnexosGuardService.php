<?php

namespace App\Services;

class AnexosGuardService
{
    private string $baseDir;
    private string $logFile;

    public function __construct()
    {
        $root = dirname(__DIR__, 2);
        $this->baseDir = $root . '/storage/documentos';
        $this->logFile = $root . '/storage/logs/anexos_guard.log';

        if (!is_dir($this->baseDir)) {
            mkdir($this->baseDir, 0777, true);
        }
    }

    public function garantirEstrutura(): void
    {
        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        $dir = "{$this->baseDir}/{$ano}/{$mes}/{$dia}";

        if (!is_dir($dir)) {
            $this->log("Pasta não existia, criada: {$dir}");
            mkdir($dir, 0777, true);
            // opcional: criar um ficheiro sentinel
            file_put_contents($dir . '/.keep', 'sentinel');
        } else {
            // se existir mas estiver vazia, registar
            $files = array_diff(scandir($dir), ['.', '..']);
            if (empty($files)) {
                $this->log("Pasta existe mas está vazia: {$dir}");
                // opcional: criar sentinel
                file_put_contents($dir . '/.keep', 'sentinel');
            }
        }
    }

    private function log(string $msg): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $msg . PHP_EOL;
        @file_put_contents($this->logFile, $linha, FILE_APPEND);
    }
}
