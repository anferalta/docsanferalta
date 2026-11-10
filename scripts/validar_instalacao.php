<?php
// Isolated checks: no production DB, email, documents or services are accessed.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Router;
use App\Core\LoginLimiter;
use App\Services\UploadPolicy;

$checks = 0;
$failures = [];
function checkInstallation(bool $ok, string $message): void {
    global $checks, $failures;
    $checks++;
    if (!$ok) $failures[] = $message;
}
function rejectedInstallation(callable $action): bool {
    try { $action(); return false; } catch (RuntimeException $e) { return true; }
}

$temp = sys_get_temp_dir() . '/anferaltadocs-check-' . bin2hex(random_bytes(8));
mkdir($temp, 0700);
try {
    $_ENV['APP_ENV'] = 'production';
    $router = new Router();
    App\Core\RouterSingleton::set($router);
    // Mirrors the older external front controller as well as canonical imports.
    foreach (glob(dirname(__DIR__) . '/routes/*.php') as $file) require $file;
    $routes = Router::all();
    $keys = [];
    foreach ($routes as $route) {
        $key = $route['method'] . ' ' . $route['uri'];
        checkInstallation(!isset($keys[$key]), 'Duplicate: ' . $key);
        $keys[$key] = true;
        [$controller, $method] = explode('@', $route['action'], 2);
        $class = 'App\\Controllers\\' . preg_replace('/[\\\\\/]+/', '\\', $controller);
        checkInstallation(class_exists($class) && method_exists($class, $method), 'Missing action: ' . $route['action']);
        if ($route['method'] === 'POST') {
            checkInstallation(in_array('csrf', $route['middleware'], true), 'Missing CSRF: ' . $key);
        }
        if ($route['uri'] === '/public/documentos/{id}') {
            checkInstallation(in_array('auth', $route['middleware'], true), 'Old document URL requires auth');
        }
        checkInstallation(!str_contains($route['uri'], 'restaurar-reiniciar'), 'No service restart route');
    }
    checkInstallation(!isset($keys['GET /test']), 'No production diagnostic route');
    checkInstallation(!isset($keys['GET /admin/backups/bd/criar']) && isset($keys['POST /admin/backups/bd/criar']), 'Database backup only via POST');
    checkInstallation(!isset($keys['GET /admin/backups/files/criar']) && isset($keys['POST /admin/backups/files/criar']), 'File backup only via POST');
    $duplicate = false;
    try { Router::get('/login', 'AuthController@login'); } catch (LogicException $e) { $duplicate = true; }
    checkInstallation($duplicate, 'Duplicate registration is rejected');
    checkInstallation(preg_match(Router::pattern('/file/{id:\d+}'), '/file/123') === 1, 'Numeric route accepts ID');
    checkInstallation(preg_match(Router::pattern('/file/{id:\d+}'), '/file/abc') === 0, 'Numeric route rejects text');
    checkInstallation(preg_match(Router::pattern('/backup/{file:.+}'), '/backup/2026/11/item.zip') === 1, 'Backup route accepts subdirectories');
    checkInstallation(preg_match(Router::pattern('/a.b/{id}'), '/axb/123') === 0, 'Route literal punctuation is escaped');

    $text = $temp . '/text';
    file_put_contents($text, "Plain document\n");
    UploadPolicy::validarConteudo($text, 'document.txt');
    checkInstallation(true, 'Plain text accepted');
    foreach (['document.php', 'document.html', 'document.php.txt', 'document.pdf', 'document.docx'] as $name) {
        checkInstallation(rejectedInstallation(fn() => UploadPolicy::validarConteudo($text, $name)), 'Reject invalid upload: ' . $name);
    }
    file_put_contents($text, '<html><script>alert(1)</script></html>');
    checkInstallation(rejectedInstallation(fn() => UploadPolicy::validarConteudo($text, 'document.txt')), 'Reject HTML disguised as text');
    file_put_contents($text, str_repeat('A', 2 * 1024 * 1024 + 1));
    checkInstallation(rejectedInstallation(fn() => UploadPolicy::validarConteudo($text, 'document.txt')), 'Reject oversized text');
    checkInstallation(rejectedInstallation(fn() => UploadPolicy::validarUpload($text, 'document.txt')), 'Local files cannot bypass upload origin checks');

    $limiter = new LoginLimiter($temp . '/limits');
    checkInstallation($limiter->consume('one', 2), 'First login attempt allowed');
    checkInstallation($limiter->consume('one', 2), 'Second login attempt allowed');
    checkInstallation(!$limiter->consume('one', 2), 'Excess login attempt refused');
    checkInstallation($limiter->consume('two', 2), 'Independent origin remains allowed');
    file_put_contents($temp . '/limits/' . hash('sha256', 'one') . '.json', json_encode(['until' => time() - 1, 'count' => 2]));
    checkInstallation($limiter->consume('one', 2), 'Expired limit resets');

    // The recovery guard must run before touching PDO or a backup.
    $_ENV['ALLOW_LEGACY_RESTORE'] = 'false';
    $restore = (new ReflectionClass(App\Services\RestoreService::class))->newInstanceWithoutConstructor();
    checkInstallation(rejectedInstallation(fn() => $restore->restaurarBD('/not-a-backup')), 'Unsafe database import disabled by default');
    checkInstallation(rejectedInstallation(fn() => $restore->restaurarFiles('/not-a-backup')), 'Unsafe file import disabled by default');

    // No sockets: prove user-facing encryption modes configure the transport
    // correctly, including the PHPMailer-specific SMTPS constant.
    $smtpEnv = ['MAIL_HOST' => 'smtp.example.test', 'MAIL_USER' => 'fixture@example.test', 'MAIL_PASS' => 'fixture-not-a-real-secret', 'MAIL_PASS_FILE' => '', 'MAIL_CA_FILE' => '', 'MAIL_FROM' => 'fixture@example.test', 'MAIL_AUTH' => 'true', 'MAIL_ENCRYPTION' => 'smtps', 'MAIL_PORT' => '465'];
    $previousEnv = [];
    foreach ($smtpEnv as $key => $value) { $previousEnv[$key] = getenv($key); putenv($key . '=' . $value); }
    try {
        $configure = new ReflectionMethod(App\Services\EmailService::class, 'configureSmtp');
        $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
        $configure->invoke(null, $mailer);
        checkInstallation($mailer->SMTPSecure === PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS && $mailer->Port === 465, 'SMTPS uses implicit TLS rather than plain TCP');
        checkInstallation($mailer->SMTPOptions['ssl']['verify_peer'] && $mailer->SMTPOptions['ssl']['verify_peer_name'] && !$mailer->SMTPOptions['ssl']['allow_self_signed'], 'SMTP validates certificates and server identity');
        putenv('MAIL_ENCRYPTION=tls'); putenv('MAIL_PORT=587');
        $configure->invoke(null, $mailer);
        checkInstallation($mailer->SMTPSecure === PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS && $mailer->Port === 587, 'STARTTLS mode uses the expected transport');
        putenv('MAIL_ENCRYPTION=none');
        checkInstallation(rejectedInstallation(fn() => $configure->invoke(null, $mailer)), 'Unencrypted SMTP mode is refused');
        putenv('MAIL_ENCRYPTION=smtps'); putenv('MAIL_PASS=');
        checkInstallation(rejectedInstallation(fn() => $configure->invoke(null, $mailer)), 'Empty SMTP credential is refused before any connection');
    } finally {
        foreach ($previousEnv as $key => $value) putenv($value === false ? $key : $key . '=' . $value);
    }

    $twig = new Twig\Environment(new Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/App/Views'));
    $twig->registerUndefinedFunctionCallback(static fn($name) => new Twig\TwigFunction($name, static fn() => null));
    $twig->registerUndefinedFilterCallback(static fn($name) => new Twig\TwigFilter($name, static fn($value) => $value));
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__) . '/App/Views', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file->getExtension() !== 'twig') continue;
        // Parse syntax without rendering or calling any application functions.
        try {
            $twig->parse($twig->tokenize(new Twig\Source(file_get_contents($file->getPathname()), $file->getPathname())));
        } catch (Throwable $e) { checkInstallation(false, 'Twig syntax: ' . $file->getPathname() . ': ' . $e->getMessage()); }
    }
} finally {
    // Only remove the randomly named fixture directory created above.
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
    rmdir($temp);
}
echo "Installation checks: {$checks}; failures: " . count($failures) . PHP_EOL;
foreach ($failures as $failure) echo 'FAIL: ' . $failure . PHP_EOL;
exit($failures === [] ? 0 : 1);
