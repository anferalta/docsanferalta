<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
require_once dirname(__DIR__, 2) . '/vendor/autoload.php';
\App\Core\Env::load();
require_once dirname(__DIR__) . '/Config/paths.php';
if (!date_default_timezone_set((string) \App\Core\Env::get('APP_TIMEZONE', 'Europe/Lisbon'))) {
    throw new RuntimeException('APP_TIMEZONE inválido.');
}
$debug = \App\Core\Env::get('APP_ENV', 'production') === 'local'
    && filter_var(\App\Core\Env::get('APP_DEBUG', 'false'), FILTER_VALIDATE_BOOLEAN);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);
set_exception_handler(static function (Throwable $error) use ($debug): void {
    error_log((string) $error);
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "A operação falhou; consulte o log privado do PHP.\n");
        exit(1);
    }
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo $debug ? (string) $error : 'Não foi possível concluir o pedido. Contacte o administrador.';
});
