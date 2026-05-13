<?php

namespace App\Core;

class Helpers {

    /**
     * Regista uma entrada de log num ficheiro diário
     */
    public static function log(string $acao, string $detalhes = ''): void {
        // Garante que a sessão está ativa
        Sessao::set('last_action', $acao);

        // Diretório dos logs
        $dir = __DIR__ . '/../../logs';

        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }

        // Nome do ficheiro (um por dia)
        $ficheiro = $dir . '/' . date('Y-m-d') . '.log';

        // Dados do utilizador
        $userId = Sessao::get('user_id') ?? 'guest';
        $userEmail = Sessao::get('user_email') ?? 'guest';

        // IP do utilizador
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';

        // Linha de log
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

        // Escrever no ficheiro
        file_put_contents($ficheiro, $linha, FILE_APPEND);
    }

    function auth() {
        return \App\Core\Auth::user();
    }

    public static function url(string $path): string {
        // Garante que começa com /
        if ($path[0] !== '/') {
            $path = '/' . ltrim($path, '/');
        }

        // Base da aplicação
        $base = $_ENV['APP_URL'] ?? '';

        return rtrim($base, '/') . $path;
    }

    public static function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    public static function route(string $name, array $params = []): string {
        global $router; // o router principal

        $route = $router->getRouteByName($name);

        if (!$route) {
            throw new \Exception("Rota '{$name}' não encontrada.");
        }

        $uri = $route['uri'];

        // Substituir parâmetros {id}, {slug}, etc.
        foreach ($params as $key => $value) {
            $uri = str_replace("{{$key}}", $value, $uri);
        }

        return $uri;
    }

    function can(string $codigo): bool {
        if (!isset($_SESSION['utilizador_id'])) {
            return false;
        }

        return \App\Models\Permissao::userHasPermission($_SESSION['utilizador_id'], $codigo);
    }

    function iconForExtension($ext) {
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
