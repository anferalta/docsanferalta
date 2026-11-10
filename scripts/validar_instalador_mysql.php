<?php
if (PHP_SAPI !== 'cli' || getenv('RUN_MYSQL_INTEGRATION') !== '1') { fwrite(STDERR, "Use RUN_MYSQL_INTEGRATION=1 num servidor de testes.\n"); exit(1); }
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\DatabaseConnection;
use App\Services\InstallerService;

Env::load();
$token = bin2hex(random_bytes(6));
$names = ['anferalta_install_' . $token . '_fresh', 'anferalta_install_' . $token . '_resume'];
$parent = dirname(__DIR__) . '/storage/temp/install-verify-' . $token;
mkdir($parent, 0700, true);
foreach (['fresh', 'resume', 'occupied', 'inputs'] as $dir) mkdir($parent . '/' . $dir, 0700);
$base = [];
foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_PASS_FILE'] as $key) $base[$key] = Env::get($key, $key === 'DB_PORT' ? 3306 : '');
$admin = DatabaseConnection::open($base + ['DB_NAME' => Env::required('DB_NAME')]);
$created = []; $checks = 0; $failures = [];
function installerCheck(bool $ok, string $label): void { global $checks, $failures; $checks++; if (!$ok) $failures[] = $label; }
function installerRejected(callable $f): bool { try { $f(); return false; } catch (Throwable $e) { return true; } }
try {
    foreach ($names as $name) {
        if (!preg_match('/\Aanferalta_install_[a-f0-9]{12}_(fresh|resume)\z/', $name) || $name === Env::get('DB_NAME')) throw new RuntimeException('Unsafe fixture name');
        $admin->exec('CREATE DATABASE ' . DatabaseConnection::identifier($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created[] = $name;
    }
    file_put_contents($parent . '/inputs/admin.txt', 'Fixture-initial-password-123!');
    file_put_contents($parent . '/inputs/mail.txt', 'Fixture-SMTP-not-a-real-secret');
    $input = array_merge($base, [
        'APP_ENV' => 'local', 'APP_URL' => 'https://install.example.test', 'DB_NAME' => $names[0],
        'ADMIN_EMAIL' => 'initial@example.test', 'ADMIN_NAME' => 'Fixture administrator', 'ADMIN_PASSWORD_FILE' => $parent . '/inputs/admin.txt',
        'MAIL_HOST' => 'smtp.example.test', 'MAIL_USER' => 'fixture@example.test', 'MAIL_FROM' => 'fixture@example.test',
        'MAIL_PASS_FILE' => $parent . '/inputs/mail.txt', 'MAIL_FROM_NAME' => 'Empresa "A" ${NOT_EXPANDED}',
    ]);
    $migrations = dirname(__DIR__) . '/database/migrations';
    $installer = new InstallerService($parent . '/fresh', $migrations);
    $plan = $installer->plan($input);
    installerCheck($plan['migrations'] === 3 && $plan['administrator'] === 'initial@example.test', 'Plan contains migration count and selected administrator');
    installerCheck(count(scandir($parent . '/fresh')) === 2, 'Plan creates no files');
    $fresh = DatabaseConnection::open(array_merge($base, ['DB_NAME' => $names[0]]));
    installerCheck($fresh->query('SHOW TABLES')->fetchAll() === [], 'Plan does not create database objects');
    installerCheck(installerRejected(fn() => $installer->plan(array_merge($input, ['APP_ENV' => 'production', 'APP_URL' => 'http://install.example.test']))), 'Production HTTP rejected before writes');
    installerCheck(installerRejected(fn() => $installer->plan(array_merge($input, ['ADMIN_PASSWORD_FILE' => '', 'ADMIN_PASSWORD' => 'short']))), 'Short administrator password rejected');
    installerCheck(installerRejected(fn() => $installer->plan(array_merge($input, ['MAIL_FROM_NAME' => "bad\nvalue"]))), 'Configuration control characters rejected before writes');
    $result = $installer->install($input);
    installerCheck($result['installed'] === true, 'Fresh install completes');
    $env = Dotenv\Dotenv::parse(file_get_contents($parent . '/fresh/.env'));
    installerCheck($env['MAIL_FROM_NAME'] === 'Empresa "A" ${NOT_EXPANDED}', 'Generated dotenv preserves quotes and literal dollar expressions');
    installerCheck(!isset($env['DB_PASS'], $env['MAIL_PASS'], $env['ADMIN_PASSWORD']), 'Dotenv stores paths instead of credential values');
    installerCheck(Env::readSecretFile($env['MAIL_PASS_FILE']) === 'Fixture-SMTP-not-a-real-secret', 'SMTP secret copied into private storage');
    installerCheck(strlen(Env::readSecretFile($env['BACKUP_PASSWORD_FILE'])) >= 32, 'Unique recovery key generated');
    installerCheck(hash_equals(Env::readSecretFile($env['BACKUP_PASSWORD_FILE']), Env::readSecretFile($parent . '/fresh/secrets/recovery.escrow')), 'Recovery escrow matches active key');
    $user = $fresh->query('SELECT email,password,perfil_id FROM utilizadores')->fetch(PDO::FETCH_ASSOC);
    installerCheck($user['email'] === 'initial@example.test' && (int) $user['perfil_id'] === 1 && password_verify('Fixture-initial-password-123!', $user['password']), 'Selected administrator created with hashed password');
    installerCheck((int) $fresh->query('SELECT COUNT(*) FROM documentos')->fetchColumn() === 0, 'No operational documents imported');
    installerCheck(!is_file($parent . '/fresh/storage/.installation-incomplete'), 'Successful install clears maintenance marker');
    $hash = hash_file('sha256', $parent . '/fresh/.env');
    installerCheck(installerRejected(fn() => $installer->install($input)), 'Repeat install refused');
    installerCheck(hash_file('sha256', $parent . '/fresh/.env') === $hash, 'Existing configuration preserved');
    file_put_contents($parent . '/fresh/storage/.installation-incomplete', 'Simulated interruption after dotenv publication');
    installerCheck($installer->install($input, true)['installed'], 'Resume can finalize an already published matching configuration');
    installerCheck(hash_file('sha256', $parent . '/fresh/.env') === $hash && !is_file($parent . '/fresh/storage/.installation-incomplete'), 'Finalization preserves configuration and clears maintenance');
    $occupied = new InstallerService($parent . '/occupied', $migrations);
    installerCheck(installerRejected(fn() => $occupied->install($input)), 'Existing database refused even in another directory');
    installerCheck(count(scandir($parent . '/occupied')) === 2, 'Refusal does not create files');
    $resumeInput = array_merge($input, ['DB_NAME' => $names[1]]);
    $resume = new InstallerService($parent . '/resume', $migrations);
    // Force a filesystem publication failure after schema/admin preparation.
    mkdir($parent . '/resume/.env.installing');
    set_error_handler(static fn() => true);
    try { $failed = installerRejected(fn() => $resume->install($resumeInput)); }
    finally { restore_error_handler(); }
    installerCheck($failed && is_file($parent . '/resume/storage/.installation-incomplete'), 'Interrupted installation stays in maintenance');
    installerCheck(installerRejected(fn() => new App\Core\MaintenanceLock($parent . '/resume/storage', false)), 'Web requests blocked for incomplete installation');
    rmdir($parent . '/resume/.env.installing');
    installerCheck($resume->install($resumeInput, true)['installed'], 'Recorded installation can resume without replacing administrator');
    $resumed = DatabaseConnection::open(array_merge($base, ['DB_NAME' => $names[1]]));
    installerCheck((int) $resumed->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn() === 1, 'Resume does not duplicate accounts');
} finally {
    foreach ($created as $name) {
        if (!in_array($name, $names, true) || !preg_match('/\Aanferalta_install_[a-f0-9]{12}_(fresh|resume)\z/', $name)) throw new RuntimeException('Unsafe fixture cleanup');
        $admin->exec('DROP DATABASE ' . DatabaseConnection::identifier($name));
    }
    $resolved = realpath($parent); $safeParent = realpath(dirname(__DIR__) . '/storage/temp');
    if ($resolved && $safeParent && dirname($resolved) === $safeParent && basename($parent) === 'install-verify-' . $token) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($parent, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isDir() && !$file->isLink()) rmdir($file->getPathname());
            else { chmod($file->getPathname(), 0600); unlink($file->getPathname()); }
        }
        rmdir($parent);
    }
}
echo "Installer checks: {$checks}; failures: " . count($failures) . PHP_EOL;
foreach ($failures as $failure) echo 'FAIL: ' . $failure . PHP_EOL;
exit($failures === [] ? 0 : 1);
