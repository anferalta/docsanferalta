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

        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true
        ]);

        // ============================
        // TWIG ENGINE
        // ============================
        $this->twig = new Environment($loader, [
            'cache' => false,
            'debug' => true
        ]);

        $this->twig->addExtension(new DebugExtension());

        // ============================
        // FLASH MESSAGES
        // ============================
        $this->twig->addFunction(new TwigFunction('flash_peek', fn($key) => Sessao::peek($key)));
        $this->twig->addFunction(new TwigFunction('flash_clear', fn($key) => Sessao::flash($key)));
        $this->twig->addGlobal('sessao', new Sessao());

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
        // MENU ADMIN (carregado uma vez)
        // ============================
        $menuObj = new Menu();
        $this->twig->addGlobal('menuAdmin', $menuObj->filtrarMenu($menuObj->getMenu()));

        // ============================
        // NOTIFICAÇÕES (carregadas apenas se autenticado)
        // ============================
        if (Auth::check()) {
            $this->injectNotifications();
        }

        // ============================
        // FUNÇÕES TWIG ADICIONAIS
        // ============================
        $this->twig->addFunction(new TwigFunction('url', fn($p = '') => '/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('asset', fn($p) => '/assets/' . ltrim($p, '/')));
        $this->twig->addFunction(new TwigFunction('isGranted', fn($p) => $acl->has($p)));

        $this->twig->addFunction(new TwigFunction('route', fn($name, $params = []) =>
                        Router::route($name, $params)
        ));

        // ============================
        // CSRF FIELD
        // ============================
        $this->twig->addFunction(
                new TwigFunction(
                        'csrf_field',
                        fn() => '<input type="hidden" name="' . CSRF::fieldName() . '" value="' . CSRF::token() . '">',
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

        $this->twig->addGlobal('auth', (object) ['user' => $user]);
        $this->twig->addGlobal('user', $user);
    }

    // ============================
    // INJETAR NOTIFICAÇÕES
    // ============================
    protected function injectNotifications(): void
    {
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
    // RENDERIZAÇÃO
    // ============================
    protected function render(string $template, array $data = []): void
    {
        $this->injectUser();

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

        if (!$user) {
            return $this->acessoNegado();
        }

        if ($user->isAdmin()) {
            return;
        }

        if (!$user->hasPermissao($permission)) {
            return $this->acessoNegado($permission);
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

    protected function acessoNegado(?string $permissao = null)
    {
        http_response_code(403);

        return $this->render('@admin/errors/403.twig', [
                    'mensagem' => $permissao
        ]);
    }

    // ============================
    // FLASH MESSAGE
    // ============================
    protected function flash($tipo, $mensagem)
    {
        Sessao::flash($tipo, $mensagem);
    }

    public function error($codigo, $mensagem = null, $extra = [])
    {
        $template = "@admin/errors/{$codigo}.twig";

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
