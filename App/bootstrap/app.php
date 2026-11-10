<?php
require_once __DIR__ . '/runtime.php';
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');
try {
    $maintenanceLock = new \App\Core\MaintenanceLock(STORAGE_PATH, false);
} catch (\RuntimeException $e) {
    http_response_code(503);
    header('Retry-After: 60');
    exit('Aplicação temporariamente em manutenção.');
}
\App\Core\Sessao::start();
\App\Core\Middleware::register('auth', static function () {
    return (new \App\Middleware\AuthMiddleware())->handle();
});
\App\Core\Middleware::register('csrf', static function () {
    return (new \App\Middleware\CsrfMiddleware())->handle();
});
\App\Core\Middleware::register('perm', require dirname(__DIR__) . '/Middleware/perm.php');
