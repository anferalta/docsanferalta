<?php

namespace App\Core;

class Helpers
{

    /**
     * Regista uma entrada de log num ficheiro diário
     */
    public static function log(string $acao, string $detalhes = ''): void
    {
        Sessao::set('last_action', $acao);

        $dir = __DIR__ . '/../../logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        $ficheiro = $dir . '/' . date('Y-m-d') . '.log';

        $userId = Sessao::get('user_id') ?? 'guest';
        $userEmail = Sessao::get('user_email') ?? 'guest';
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        $linha = sprintf(
            "[%s] | IP: %s | User: %s (%s) | Ação: %s | Detalhes: %s%s",
            date('Y-m-d H:i:s'),
            $ip,
            $userId,
            $userEmail,
            $acao,
            $detalhes,
            PHP_EOL
        );

        file_put_contents($ficheiro, $linha, FILE_APPEND);
    }

    function auth()
    {
        return \App\Core\Auth::user();
    }

    /**
     * Gera URL absoluta com base no APP_URL
     */
    public static function url(string $path): string
    {
        if ($path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        $base = rtrim($_ENV['APP_URL'] ?? '', '/');

        return $base . $path;
    }

    /**
     * Redirecionamento HTTP
     */
    public static function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    /**
     * Gera URL absoluta baseada no ambiente
     */
    public static function baseUrl(): string
    {
        // Se APP_URL estiver definido, usar sempre
        if (!empty($_ENV['APP_URL'])) {
            return rtrim($_ENV['APP_URL'], '/');
        }

        // Fallback para ambiente local
        if ($_ENV['APP_ENV'] === 'local') {
            return 'https://anferaltadocs.local';
        }

        // Produção
        return 'https://anferalta.com';
    }

    public static function route(string $name, array $params = []): string
    {
        global $router;

        $route = $router->getRouteByName($name);

        if (!$route) {
            throw new \Exception("Rota '{$name}' não encontrada.");
        }

        $uri = $route['uri'];

        foreach ($params as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
        }

        return $uri;
    }

    function can(string $codigo): bool
    {
        if (!isset($_SESSION['utilizador_id'])) {
            return false;
        }

        return \App\Models\Permissao::userHasPermission($_SESSION['utilizador_id'], $codigo);
    }

    function iconForExtension($ext)
    {
        $ext = strtolower($ext);

        $map = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'txt' => 'fa-file-lines',
            'png' => 'fa-file-image',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'zip' => 'fa-file-zipper',
            'rar' => 'fa-file-zipper',
        ];

        return $map[$ext] ?? 'fa-file';
    }
}

// ============================================================
// CSRF HELPERS (FUNÇÕES GLOBAIS)
// ============================================================

if (!function_exists('csrf_token')) {
    function csrf_token(): string
    {
        return \App\Core\CSRF::token();
    }
}

if (!function_exists('csrf_field')) {
    function csrf_field(): string
    {
        return '<input type="hidden" name="_csrf" value="' . \App\Core\CSRF::token() . '">';
    }
}
