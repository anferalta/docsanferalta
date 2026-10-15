<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'anferalta.com',
    'secure' => true,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();

// AUTOLOAD
require __DIR__ . '/vendor/autoload.php';

// DOTENV
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// IMPORTS
use App\Core\Router;
use App\Core\RouterSingleton;
use App\Core\Middleware;
use App\Core\Auth;
use App\Core\Acl;
use App\Core\ErrorHandler;
use App\Core\CSRF;
use App\Core\Sessao;
use App\Core\Helpers;

// ERROS
ErrorHandler::register();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// LOGS
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    \App\Models\LogSistema::registar('error', $errstr, $errfile, $errline);
    return false;
});

set_exception_handler(function ($ex) {
    http_response_code(500);
    echo "<pre>";
    echo $ex->getMessage() . "\n\n";
    echo $ex->getFile() . ':' . $ex->getLine() . "\n\n";
    echo $ex->getTraceAsString();
    echo "</pre>";
    exit;
});

// MIDDLEWARES
Middleware::register('auth', function () {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }
    return true;
});

Middleware::register('perm', function ($permission = null) {
    if ($permission === null || $permission === '') {
        throw new \Exception("Middleware 'perm' requer um parâmetro.");
    }

    $acl = new Acl();

    if (!$acl->has($permission)) {
        http_response_code(403);
        echo "Acesso negado";
        exit;
    }
});

Middleware::register('csrf', function () {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            Helpers::redirect('/recuperar');
            exit;
        }
    }
    return true;
});

// ROUTER
$router = new Router();
RouterSingleton::set($router);

// CARREGAR ROTAS
foreach (glob(__DIR__ . '/routes/*.php') as $file) {
    require $file;
}

// DESPACHAR
$router->dispatch();
