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

        // REGISTAR O NAMESPACE CORRETO
        $loader->addPath(__DIR__ . '/../Views/emails', 'emails');

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

        // *** ESTA LINHA É A CORREÇÃO CRÍTICA ***
        return self::$twig->render("@emails/$template", $dados);
    }
}
