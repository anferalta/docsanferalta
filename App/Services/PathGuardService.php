<?php

namespace App\Services;

class PathGuardService
{
    private static array $protegidos = [];

    public static function init(): void
    {
        // Carregar lista de caminhos protegidos
        $lista = require __DIR__ . '/../Config/protected_paths.php';

        // Converter todos os caminhos para realpath
        self::$protegidos = array_filter(array_map(function ($p) {
            $real = realpath($p);
            return $real !== false ? $real : null;
        }, $lista));
    }

    public static function proteger(string $path): void
    {
        $real = realpath($path);

        if ($real === false) {
            return;
        }

        foreach (self::$protegidos as $protegido) {

            // Blindagem absoluta: impedir operações dentro de pastas protegidas
            if (str_starts_with($real, $protegido)) {
                throw new \Exception("Tentativa bloqueada: acesso ou modificação de pasta protegida ({$real})");
            }
        }
    }
}
