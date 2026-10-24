<?php

namespace App\Core;

class Redirect
{
    public static function to(string $url): void
    {
        header('Location: ' . $url, true, 302);
        exit;
    }
}
