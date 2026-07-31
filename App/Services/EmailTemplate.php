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

        // *** CORREÇÃO CRÍTICA ***
        // Os templates de email estão em: App/Views/site/emails
        $loader->addPath(__DIR__ . '/../Views/site/emails', 'emails');

        self::$twig = new Environment($loader, [
            'cache' => false,
            'debug' => false
        ]);
    }

    public static function render(string $template, array $dados = []): string
    {
        self::init();

        // Permitir passar apenas "utilizador_rejeitado"
        if (!str_contains($template, '.twig')) {
            $template .= '.twig';
        }

        return self::$twig->render("@emails/$template", $dados);
    }
}
