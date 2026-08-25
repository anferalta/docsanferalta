<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;

class EmailTemplate
{
    protected static ?Environment $twig = null;

    protected static function init(): void
    {
        if (self::$twig !== null) {
            return;
        }

        $loader = new FilesystemLoader();

        // Caminho correto dos templates de email
        $loader->addPath(__DIR__ . '/../Views/site/emails', 'emails');

        self::$twig = new Environment($loader, [
            'cache' => false,
            'debug' => false
        ]);
    }

    public static function render(string $template, array $dados = []): string
    {
        self::init();

        if (!str_contains($template, '.twig')) {
            $template .= '.twig';
        }

        return self::$twig->render("@emails/$template", $dados);
    }
}
