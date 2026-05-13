<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;
use App\Core\Menu;

class BaseController {

    protected Environment $twig;

    public function __construct() {

        // ============================
        // TWIG LOADER
        // ============================
        $loader = new FilesystemLoader();
        $loader->addPath(__DIR__ . '/../Views/site', 'site');
        $loader->addPath(__DIR__ . '/../Views/admin', 'admin');
        $loader->addPath(__DIR__ . '/../Views', '__main__');

        // ============================
        // TWIG ENGINE
        // ============================
        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true
        ]);

        $this->twig->addExtension(new DebugExtension());

        // ============================
        // ACL
        // ============================
        $acl = new Acl();
        $this->twig->addGlobal('acl', $acl);
        $this->twig->addGlobal('sessao', new \App\Core\Sessao());

        // ============================
        // FUNÇÕES TWIG
        // ============================
        $this->twig->addFunction(new TwigFunction('url', fn($p = '') => '/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('asset', fn($p) => '/assets/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('isGranted', fn($p) => $acl->has($p)));

        $this->twig->addFunction(new TwigFunction('route', function ($name, $params = []) {
            return \App\Core\Router::route($name, $params);
        }));

        // ============================
        // CSRF — CORRIGIDO
        // ============================

        // NÃO regenerar token sempre que o Twig chama csrf_token()
        $this->twig->addFunction(new TwigFunction('csrf_token', function () {
            return $_SESSION['_csrf_token'] ?? CSRF::token();
        }));

        // Campo hidden com token estável
        $this->twig->addFunction(
            new TwigFunction(
                'csrf_field',
                function () {
                    $token = $_SESSION['_csrf_token'] ?? CSRF::token();
                    return '<input type="hidden" name="' . CSRF::fieldName() . '" value="' . $token . '">';
                },
                ['is_safe' => ['html']]
            )
        );

        // ============================
        // UTILIZADOR
        // ============================
        $this->injectUser();

        // ============================
        // MENU ADMIN
        // ============================
        $menuObj = new Menu();
        $menu = $menuObj->getMenu();
        $menu = $menuObj->filtrarMenu($menu);

        $this->twig->addGlobal('menuAdmin', $menu);

        // ============================
        // NOTIFICAÇÕES
        // ============================
        if (Auth::check()) {

            $db = Conexao::getInstancia();
            $uid = Auth::id();

            $notificacoes = $db->query("
                SELECT * FROM notificacoes
                WHERE utilizador_id = {$uid}
                ORDER BY id DESC
                LIMIT 5
            ")->fetchAll();

            $nao_lidas = $db->query("
                SELECT COUNT(*) FROM notificacoes
                WHERE utilizador_id = {$uid} AND lida = 0
            ")->fetchColumn();

            $this->twig->addGlobal('notificacoes', $notificacoes);
            $this->twig->addGlobal('notificacoes_nao_lidas', $nao_lidas);
        }
    }

    // ============================
    // INJETAR UTILIZADOR NO TWIG
    // ============================
    protected function injectUser(): void {
        $this->twig->addGlobal('auth', (object)[
            'user' => Auth::user()
        ]);
    }

    // ============================
    // RENDERIZAÇÃO
    // ============================
    protected function render(string $template, array $data = []): void {
        $this->injectUser();
        echo $this->twig->render($template, $data);
        exit;
    }

    // ============================
    // REDIRECIONAMENTO
    // ============================
    protected function redirect(string $url): void {
        header("Location: $url");
        exit;
    }

    // ============================
    // AUTORIZAÇÃO
    // ============================
    protected function authorize(string $permission) {
        $user = Auth::user();

        if ($user && $user->isAdmin()) {
            return;
        }

        $acl = new Acl();

        if (!$acl->has($permission)) {
            return $this->acessoNegado();
        }
    }

    protected function authorizeAny(array $permissoes) {
        $user = Auth::user();

        if ($user && $user->isAdmin()) {
            return true;
        }

        foreach ($permissoes as $p) {
            if ($user->hasPermissao($p)) {
                return true;
            }
        }

        return $this->acessoNegado();
    }

    protected function acessoNegado() {
        http_response_code(403);
        return $this->render('@admin/errors/403.twig');
    }
}
