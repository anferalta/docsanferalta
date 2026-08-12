<?php

namespace App\Core;

use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Twig\Extension\DebugExtension;
use Twig\TwigFunction;
use App\Core\Menu;
use App\Services\AnexosGuardService;

class BaseController
{

    protected Environment $twig;
    protected AnexosGuardService $anexosGuard;

    public function __construct()
    {
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
        // FLASH MESSAGES — FUNÇÕES SEGURAS
        // ============================
        $this->twig->addFunction(new TwigFunction('flash_peek', fn($key) => Sessao::peek($key)));
        $this->twig->addFunction(new TwigFunction('flash_clear', fn($key) => Sessao::flash($key)));

        // ============================
        // ACL
        // ============================
        $acl = new Acl();
        $this->twig->addGlobal('acl', $acl);

        // ============================
        // UTILIZADOR AUTENTICADO
        // ============================
        $this->injectUser();

        // ============================
        // MENU ADMIN
        // ============================
        $menuObj = new Menu();
        $menu = $menuObj->filtrarMenu($menuObj->getMenu());
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

        // ============================
        // FUNÇÕES TWIG ADICIONAIS
        // ============================
        $this->twig->addFunction(new TwigFunction('url', fn($p = '') => '/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('asset', fn($p) => '/assets/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('isGranted', fn($p) => $acl->has($p)));

        $this->twig->addFunction(new TwigFunction('route', function ($name, $params = []) {
                            return Router::route($name, $params);
                        }));

        // ============================
        // CSRF FIELD
        // ============================
        $this->twig->addFunction(
                new TwigFunction(
                        'csrf_field',
                        function () {
                            return '<input type="hidden" name="' . CSRF::fieldName() . '" value="' . CSRF::token() . '">';
                        },
                        ['is_safe' => ['html']]
                )
        );
    }

    // ============================
    // INJETAR UTILIZADOR NO TWIG
    // ============================
    protected function injectUser(): void
    {
        $user = Auth::user();

        $this->twig->addGlobal('auth', (object) [
                    'user' => $user
        ]);

        $this->twig->addGlobal('user', $user);
    }

    // ============================
    // RENDERIZAÇÃO
    // ============================
    protected function render(string $template, array $data = []): void
    {
        $this->injectUser();

        if (
                str_starts_with($template, 'admin/') ||
                str_starts_with($template, '@admin/')
        ) {
            $menu = (new \App\Core\Menu())->filtrarMenu((new \App\Core\Menu())->getMenu());
            $data['menuAdmin'] = $menu;   // ← ESTE É O NOME CERTO
        }

        echo $this->twig->render($template, $data);
        exit;
    }

    protected function view(string $template, array $data = []): void
    {
        $this->render($template, $data);
    }

    // ============================
    // REDIRECIONAMENTO
    // ============================
    protected function redirect(string $url): void
    {
        header("Location: $url");
        exit;
    }

    // ============================
    // AUTORIZAÇÃO
    // ============================
    protected function authorize(string $permission)
    {
        $user = Auth::user();

        if ($user && $user->isAdmin()) {
            return;
        }

        $acl = new Acl();

        if (!$acl->has($permission)) {
            return $this->acessoNegado();
        }
    }

    protected function authorizeAny(array $permissoes)
    {
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

    protected function acessoNegado()
    {
        http_response_code(403);
        return $this->render('@admin/errors/403.twig');
    }

    // ============================
    // FLASH MESSAGE (CONTROLLER)
    // ============================
    protected function flash($tipo, $mensagem)
    {
        Sessao::flash($tipo, $mensagem);
    }

    public function error($codigo, $mensagem = null, $extra = [])
    {
        $template = "@admin/errors/{$codigo}.twig";

        // Se o template não existir, usar fallback
        if (!$this->twig->getLoader()->exists($template)) {
            $template = "@admin/errors/fallback.twig";
            $extra['codigo'] = $codigo;
            $extra['mensagem'] = $mensagem;
        }

        return $this->render($template, array_merge([
                    'mensagem' => $mensagem
                                ], $extra));
    }
}
