<?php

namespace App\Services;

class PathGuardService
{
    private static array $protegidos = [];

    public static function init(): void
    {
        self::$protegidos = require __DIR__ . '/../Config/protected_paths.php';
    }

    public static function proteger(string $path): void
    {
        $real = realpath($path);

        if ($real === false) {
            return;
        }

        foreach (self::$protegidos as $protegido) {
            if ($protegido !== false && str_starts_with($real, $protegido)) {
                throw new \Exception("Tentativa bloqueada: apagar pasta protegida ({$real})");
            }
        }
    }
}
