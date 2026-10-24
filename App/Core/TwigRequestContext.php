<?php

namespace App\Core;

/**
 * Pequeno adaptador para a API app.request usada nos templates Twig.
 */
class TwigRequestContext
{
    public string $path;
    public string $queryString;
    public TwigQueryContext $query;

    public function __construct()
    {
        $this->path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $this->queryString = $_SERVER['QUERY_STRING'] ?? '';
        $this->query = new TwigQueryContext($_GET);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }
}

class TwigQueryContext
{
    public function __construct(private array $parameters)
    {
    }

    public function all(): array
    {
        return $this->parameters;
    }
}