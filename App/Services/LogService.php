<?php

namespace App\Services;

class LogService
{
    private string $logDir;

    public function __construct()
    {
        $this->logDir = realpath(__DIR__ . '/../../backups/logs');
    }

    public function listarLogs(): array
    {
        $ficheiro = $this->logDir . '/backup_db.log';
        if (!file_exists($ficheiro)) return [];

        $linhas = file($ficheiro, FILE_IGNORE_NEW_LINES);
        $registos = [];

        foreach ($linhas as $linha) {
            if (preg_match('/^\[(.*?)\] (.*)$/', $linha, $m)) {
                $registos[] = [
                    'data' => $m[1],
                    'mensagem' => $m[2],
                    'tipo' => str_contains($m[2], 'sucesso') ? 'sucesso' : 'erro'
                ];
            }
        }

        return array_reverse($registos);
    }
}