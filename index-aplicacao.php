<?php
/** Hosting-only entrypoint. Uses the installed application and its own dependencies. */
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

try {
    $anferaltaApplicationRoot = __DIR__ . '/anferaltadocs';
    foreach (['vendor/autoload.php', 'App/Config/paths.php', 'App/Middleware/perm.php', 'routes/index.php'] as $anferaltaRequiredFile) {
        if (!is_file($anferaltaApplicationRoot . '/' . $anferaltaRequiredFile)
            || !is_readable($anferaltaApplicationRoot . '/' . $anferaltaRequiredFile)) {
            throw new RuntimeException('Ficheiro de arranque em falta: ' . $anferaltaRequiredFile);
        }
    }
    require_once $anferaltaApplicationRoot . '/vendor/autoload.php';
    \Dotenv\Dotenv::createImmutable($anferaltaApplicationRoot)->load();
    require_once $anferaltaApplicationRoot . '/App/Config/paths.php';

    // Keep the application's own session behavior and middleware contracts.
    \App\Core\Sessao::start();
    \App\Core\Middleware::register('auth', static function () {
        return (new \App\Middleware\AuthMiddleware())->handle();
    });
    \App\Core\Middleware::register('csrf', static function () {
        return (new \App\Middleware\CsrfMiddleware())->handle();
    });
    \App\Core\Middleware::register('perm', require $anferaltaApplicationRoot . '/App/Middleware/perm.php');

    $router = new \App\Core\Router();
    \App\Core\RouterSingleton::set($router);
    // Canonical routes first; legacy extra route files are loaded once only.
    // This accepts the restored layout and prevents duplicate inclusion when
    // routes/index.php itself imports public.php, admin.php or test.php.
    require_once $anferaltaApplicationRoot . '/routes/index.php';
    foreach (glob($anferaltaApplicationRoot . '/routes/*.php') as $anferaltaRouteFile) {
        require_once $anferaltaRouteFile;
    }
    $router->dispatch();
} catch (\Throwable $anferaltaStartupError) {
    error_log('Anferalta application entrypoint: ' . get_class($anferaltaStartupError) . ': ' . $anferaltaStartupError->getMessage());
    http_response_code(500);
    header('Content-Type: text/plain; charset=UTF-8');
    echo 'Não foi possível iniciar a aplicação. Consulte o log privado do alojamento.';
}
