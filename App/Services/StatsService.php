<?php

namespace App\Services;

use App\Services\PathGuardService;
use App\Services\BackupLogger;

class StatsService
{
    public function estatisticas(): array
    {
        PathGuardService::init();

        $base = realpath(__DIR__ . '/../../backups/BaseDados');

        // ============================
        // 1. Validar pasta base
        // ============================
        if ($base === false || !is_dir($base)) {
            BackupLogger::registar(
                'STATS',
                'BaseDados',
                false,
                "Pasta de backups BaseDados não encontrada"
            );

            return [
                'total' => 0,
                'tamanho' => 0,
                'ultimo' => null
            ];
        }

        // 🔒 Proteger pasta base
        PathGuardService::proteger($base);

        $total = 0;
        $tamanho = 0;
        $ultimo = null;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $f) {

            // ============================
            // 2. Validar ficheiro ZIP
            // ============================
            if ($f->isFile() && strtolower($f->getExtension()) === 'zip') {

                $filePath = $f->getRealPath();

                if ($filePath === false) {
                    continue;
                }

                // 🔒 Proteger ficheiro
                PathGuardService::proteger($filePath);

                $total++;
                $tamanho += $f->getSize();

                // ============================
                // 3. Encontrar o mais recente
                // ============================
                if (!$ultimo || $f->getMTime() > $ultimo['time']) {
                    $ultimo = [
                        'ficheiro' => $f->getFilename(),
                        'time' => $f->getMTime(),
                        'data' => date('Y-m-d H:i:s', $f->getMTime())
                    ];
                }
            }
        }

        return [
            'total' => $total,
            'tamanho' => round($tamanho / 1024 / 1024, 2),
            'ultimo' => $ultimo
        ];
    }
}
