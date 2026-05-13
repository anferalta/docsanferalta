<?php

namespace App\Core;

class RouterSingleton
{
    private static ?Router $instance = null;

    public static function set(Router $router)
    {
        self::$instance = $router;
    }

    public static function get(): Router
    {
        return self::$instance;
    }
}