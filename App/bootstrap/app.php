<?php

use App\Core\Sessao;
use App\Core\Middleware;
use Dotenv\Dotenv;

$dotenv = Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

/*
|--------------------------------------------------------------------------
| 1. Iniciar sessão
|--------------------------------------------------------------------------
*/
Sessao::start();

/*
|--------------------------------------------------------------------------
| 2. NÃO carregar Env::load() — já carregámos o .env acima
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| 3. Carregar config global
|--------------------------------------------------------------------------
*/
$config = require __DIR__ . '/../config.php';
require __DIR__ . '/../helpers.php';

/*
|--------------------------------------------------------------------------
| 4. Middlewares
|--------------------------------------------------------------------------
*/
Middleware::register([
    'auth'      => \App\Middleware\AuthMiddleware::class,
    'twofactor' => \App\Middleware\TwoFactorMiddleware::class,
    'acl'       => \App\Middleware\AclMiddleware::class,
    'csrf'      => \App\Middleware\CsrfMiddleware::class,
]);

/*
|--------------------------------------------------------------------------
| 7. ACL opcional
|--------------------------------------------------------------------------
*/
$aclFile = __DIR__ . '/acl.php';
if (file_exists($aclFile)) {
    require $aclFile;
}