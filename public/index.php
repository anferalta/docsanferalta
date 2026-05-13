<?php

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => 'anferaltadocs.local',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax'
]);

session_start();


require __DIR__ . '/../vendor/autoload.php';

use App\Core\Router;
use App\Core\RouterSingleton;
use App\Core\Middleware;
use App\Core\Auth;
use App\Core\Acl;
use App\Core\ErrorHandler;
use App\Core\CSRF;

// ---------------------------------------------------------
// 🔥 ERROS E EXCEÇÕES
// ---------------------------------------------------------

ErrorHandler::register();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Capturar erros PHP
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    \App\Models\LogSistema::registar('error', $errstr, $errfile, $errline);
    return false; // deixar PHP continuar
});

// Capturar exceções não tratadas
set_exception_handler(function ($ex) {
    http_response_code(500);
    echo "<pre>";
    echo $ex->getMessage() . "\n\n";
    echo $ex->getFile() . ':' . $ex->getLine() . "\n\n";
    echo $ex->getTraceAsString();
    echo "</pre>";
    exit;
});

// ---------------------------------------------------------
// 🔥 VARIÁVEIS DE AMBIENTE
// ---------------------------------------------------------

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

// ---------------------------------------------------------
// 🔥 REGISTAR MIDDLEWARES
// ---------------------------------------------------------

// Middleware de autenticação
Middleware::register('auth', function () {
    if (!Auth::check()) {
        header('Location: /login');
        exit;
    }
    return true;
});

// Middleware de permissões (ACL)
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

// ⭐ Middleware CSRF
Middleware::register('csrf', function () {
    CSRF::middleware();
});

// ---------------------------------------------------------
// 🔥 INICIALIZAR ROUTER
// ---------------------------------------------------------

$router = new Router();

// Guardar instância global (para route() no Twig)
RouterSingleton::set($router);

// Carregar ficheiro de rotas
require __DIR__ . '/../routes/index.php';

// ---------------------------------------------------------
// 🔥 DESPACHAR ROTA
// ---------------------------------------------------------

$router->dispatch();