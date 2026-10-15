<?php

namespace App\Core;

class Middleware
{
    private static array $middlewares = [];

    public static function register(string $name, callable $callback): void
    {
        self::$middlewares[$name] = $callback;
    }

    public static function run(string $name, $param = null)
    {
        if (!isset(self::$middlewares[$name])) {
            throw new \Exception("Middleware '{$name}' não existe.");
        }

        // CSRF só corre em POST
        if ($name === 'csrf' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            return true;
        }

        $callback = self::$middlewares[$name];

        if ($param === null) {
            return $callback();
        }

        return $callback($param);
    }

    public static function runChain(string $chain)
    {
        $parts = explode('|', $chain);

        foreach ($parts as $item) {

            if (str_contains($item, ':')) {
                [$name, $param] = explode(':', $item, 2);
                self::run($name, $param);
                continue;
            }

            self::run($item);
        }
    }
}
