<?php
// Front controller para a instalação /www/index.php + /www/anferaltadocs/.
ob_start();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

$sessionPath = __DIR__ . '/storage/sessions';
if (!is_dir($sessionPath) && !mkdir($sessionPath, 0770, true) && !is_dir($sessionPath)) {
    http_response_code(500);
    exit('Não foi possível iniciar a sessão. Contacte o administrador.');
}
ini_set('session.save_path', $sessionPath);

require __DIR__ . '/anferaltadocs/vendor/autoload.php';
require __DIR__ . '/anferaltadocs/App/Config/paths.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/anferaltadocs');
$dotenv->load();

use App\Core\Router;
use App\Core\RouterSingleton;
use App\Core\Middleware;
use App\Core\MiddlewarePostRestore;
use App\Core\CSRF;
use App\Core\Auth;
use App\Core\Sessao;

// Uma única implementação configura cookies, expiração e token de sessão.
Sessao::start();
Middleware::register('csrf', function () {
    CSRF::exigirTokenValido();
    return true;
});
Middleware::register('auth', function () {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }
    return true;
});
Middleware::register('perm', function ($permission) {
    $user = Auth::user();
    if (!$user) {
        header('Location: /login');
        exit;
    }
    if (!$user->hasPermissao($permission)) {
        http_response_code(403);
        exit('Acesso negado.');
    }
    return true;
});

$router = new Router();
RouterSingleton::set($router);
require __DIR__ . '/anferaltadocs/routes/public.php';
require __DIR__ . '/anferaltadocs/routes/admin.php';
MiddlewarePostRestore::check();
$router->dispatch();
