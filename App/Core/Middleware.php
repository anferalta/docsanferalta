<?php

namespace App\Core;

class Middleware
{

    /**
     * Armazena todos os middlewares registados
     */
    private static array $middlewares = [];

    /**
     * Registar um middleware
     */
    public static function register(string $name, callable $callback): void
    {
        self::$middlewares[$name] = $callback;
    }

    /**
     * Executar um middleware
     * Retorna SEMPRE o resultado do callback
     */
    public static function run(string $name, $param = null)
    {
        if (!isset(self::$middlewares[$name])) {
            throw new \Exception("Middleware '{$name}' não existe.");
        }

        $callback = self::$middlewares[$name];

        if ($param === null) {
            return $callback();   // ← devolve o resultado
        }

        return $callback($param); // ← devolve o resultado
    }

    /**
     * Executar cadeia de middlewares
     * Ex: "auth|perm:admin.documentos.criar"
     */
    public static function runChain(string $chain)
    {
        $parts = explode('|', $chain);

        foreach ($parts as $item) {

            // Middleware com parâmetro
            if (str_contains($item, ':')) {
                [$name, $param] = explode(':', $item, 2);
                self::run($name, $param);
                continue;
            }

            // Middleware simples
            self::run($item);
        }
    }
}
