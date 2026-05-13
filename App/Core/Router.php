<?php

namespace App\Core;

class Router
{
    private static array $routes = [];
    private static array $groupStack = [];

    public static function get(string $uri, string $action, array $middleware = [], ?string $name = null)
    {
        self::addRoute('GET', $uri, $action, $middleware, $name);
        return new self;
    }

    public static function post(string $uri, string $action, array $middleware = [], ?string $name = null)
    {
        self::addRoute('POST', $uri, $action, $middleware, $name);
        return new self;
    }

    public function name(string $name)
    {
        $last = array_key_last(self::$routes);
        if ($last !== null) {
            self::$routes[$last]['name'] = $name;
        }
        return $this;
    }

    public static function route(string $name, array $params = []): ?string
    {
        foreach (self::$routes as $route) {
            if ($route['name'] === $name) {
                $uri = $route['uri'];
                foreach ($params as $key => $value) {
                    $uri = str_replace("{{$key}}", $value, $uri);
                }
                return $uri;
            }
        }
        return null;
    }

    public static function group(array $options, callable $callback)
    {
        self::$groupStack[] = [
            'prefix'     => $options['prefix'] ?? '',
            'middleware' => $options['middleware'] ?? []
        ];

        $callback(new self);

        array_pop(self::$groupStack);
    }

    private static function addRoute(string $method, string $uri, string $action, array $middleware, ?string $name)
    {
        $prefix = '';
        $groupMiddleware = [];

        foreach (self::$groupStack as $group) {
            if (!empty($group['prefix'])) {
                $prefix .= '/' . trim($group['prefix'], '/');
            }
            $groupMiddleware = array_merge($groupMiddleware, $group['middleware']);
        }

        $uri = '/' . trim($prefix . '/' . trim($uri, '/'), '/');

        self::$routes[] = [
            'method'     => $method,
            'uri'        => $uri,
            'action'     => $action,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name'       => $name
        ];
    }

    public static function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        // Remove query strings e normaliza barra final
        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        foreach (self::$routes as $route) {

            if ($route['method'] !== $method)
                continue;

            // Aceitar barra final opcional
            $pattern = rtrim($route['uri'], '/');

            // Parâmetros opcionais
            $pattern = preg_replace('#\{([^}/]+)\?\}#', '([^/]+)?', $pattern);

            // Parâmetros obrigatórios
            $pattern = preg_replace('#\{([^}]+)\}#', '([^/]+)', $pattern);

            // Aceitar barra final opcional
            $pattern .= '/?';

            if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {

                array_shift($matches);

                // CSRF automático em POST
                if ($method === 'POST') {
                    \App\Core\Middleware::run('csrf');
                }

                // Middlewares
                foreach ($route['middleware'] as $m) {
                    if (is_string($m)) {
                        if (str_contains($m, ':')) {
                            [$name, $arg] = explode(':', $m, 2);
                            Middleware::run($name, $arg);
                        } else {
                            Middleware::run($m);
                        }
                    } elseif (is_array($m)) {
                        Middleware::run($m[0] ?? null, $m[1] ?? null);
                    }
                }

                self::executeAction($route['action'], $matches);
                exit;
            }
        }

        http_response_code(404);
        echo "404 - Página não encontrada";
        exit;
    }

    private static function executeAction(string $action, array $params)
    {
        if (!str_contains($action, '@')) {
            throw new \Exception("Ação inválida: '$action'. Formato esperado: Controller@method");
        }

        [$controller, $method] = explode('@', $action, 2);

        $controller = "App\\Controllers\\" . str_replace(['/', '\\'], '\\', $controller);

        if (!class_exists($controller)) {
            throw new \Exception("Controller '$controller' não encontrado.");
        }

        $instance = new $controller();

        if (!method_exists($instance, $method)) {
            throw new \Exception("Método '$method' não encontrado no controller '$controller'.");
        }

        call_user_func_array([$instance, $method], $params);
        exit;
    }
}
