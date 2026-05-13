<?php

namespace App\Services;

class StatsService
{
    public function estatisticas(): array
    {
        $base = realpath(__DIR__ . '/../../backups/BaseDados');
        $total = 0;
        $tamanho = 0;
        $ultimo = null;

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $f) {
            if ($f->isFile() && $f->getExtension() === 'zip') {
                $total++;
                $tamanho += $f->getSize();

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