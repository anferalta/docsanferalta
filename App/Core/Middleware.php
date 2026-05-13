<?php

namespace App\Core;

class Middleware
{
    private static array $middlewares = [];

    public static function register(string $name, callable $callback): void
    {
        self::$middlewares[$name] = $callback;
    }

    public static function run(string $name, $param = null): void
    {
        if (!isset(self::$middlewares[$name])) {
            throw new \Exception("Middleware '{$name}' não existe.");
        }

        $callback = self::$middlewares[$name];

        if ($param === null) {
            $callback();
            return;
        }

        $callback($param);
    }

    /**
     * Ex: "auth|perm:admin.utilizadores.ver"
     */
    public static function runChain(string $chain): void
    {
        $parts = explode('|', $chain);

        foreach ($parts as $item) {
            if (str_contains($item, ':')) {
                [$name, $param] = explode(':', $item, 2);
                self::run($name, $param);
            } else {
                self::run($item);
            }
        }
    }
}