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
                    $uri = preg_replace_callback(
                        '/\{' . preg_quote((string) $key, '/') . '(?::[^}]+)?\??\}/',
                        static fn() => rawurlencode((string) $value),
                        $uri
                    );
                }
                return $uri;
            }
        }
        return null;
    }

    public static function group(array $options, callable $callback)
    {
        self::$groupStack[] = [
            'prefix' => $options['prefix'] ?? '',
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

        foreach (self::$routes as $route) {
            if ($route['method'] === $method && $route['uri'] === $uri) {
                throw new \LogicException("Rota duplicada: {$method} {$uri}");
            }
        }

        self::$routes[] = [
            'method' => $method,
            'uri' => $uri,
            'action' => $action,
            'middleware' => array_merge($groupMiddleware, $middleware),
            'name' => $name
        ];
    }

    public static function dispatch()
    {
        $method = $_SERVER['REQUEST_METHOD'];

        $uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        $uri = '/' . trim($uri, '/');

        foreach (self::$routes as $route) {

            if ($route['method'] !== $method)
                continue;

            $pattern = self::pattern($route['uri']);

            if (preg_match($pattern, $uri, $matches)) {

                array_shift($matches);

                // Executar middlewares corretamente
                foreach ($route['middleware'] as $m) {

                    // Middleware simples
                    if (is_string($m)) {
                        if (str_contains($m, ':')) {
                            [$name, $arg] = explode(':', $m, 2);
                            if (Middleware::run($name, $arg) === false) return;
                        } else {
                            if (Middleware::run($m) === false) return;
                        }
                    }

                    // Middleware dentro de array
                    elseif (is_array($m)) {

                        // Ex: ['csrf']
                        if (count($m) === 1) {
                            if (Middleware::run($m[0]) === false) return;
                        }

                        // Ex: ['perm', 'admin.documentos']
                        elseif (count($m) >= 2) {
                            if (Middleware::run($m[0], $m[1]) === false) return;
                        }
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

    public static function all(): array
    {
        return self::$routes;
    }

    public static function pattern(string $uri): string
    {
        $pattern = '';
        $offset = 0;
        preg_match_all('/\{([a-zA-Z_][a-zA-Z0-9_]*)(?::([^}]+))?\}/', $uri, $tokens, PREG_OFFSET_CAPTURE);
        foreach ($tokens[0] as $i => [$token, $position]) {
            $pattern .= preg_quote(substr($uri, $offset, $position - $offset), '~');
            $constraint = $tokens[2][$i][0] !== '' ? $tokens[2][$i][0] : '[^/]+';
            $pattern .= '(' . str_replace('~', '\\~', $constraint) . ')';
            $offset = $position + strlen($token);
        }
        $pattern .= preg_quote(substr($uri, $offset), '~');
        return '~^' . rtrim($pattern, '/') . '/?$~D';
    }
}
