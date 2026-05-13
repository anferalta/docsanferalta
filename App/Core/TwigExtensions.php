<?php

namespace App\Core;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class TwigExtensions extends AbstractExtension
{
    public function getFunctions()
    {
        return [
            new TwigFunction('url', [$this, 'generateUrl'])
        ];
    }

    public function generateUrl(string $name, array $params = [])
    {
        $router = new Router();
        return $router->route($name, $params);
    }
}