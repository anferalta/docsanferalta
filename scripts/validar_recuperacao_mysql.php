<?php
// Explicitly opted-in integration test. Creates and drops ONLY random fixture DBs.
if (PHP_SAPI !== 'cli' || getenv('RUN_MYSQL_INTEGRATION') !== '1') { fwrite(STDERR, "Use RUN_MYSQL_INTEGRATION=1 num servidor de testes.\n"); exit(1); }
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Env;
use App\Services\DatabaseConnection;
use App\Services\MigrationService;
use App\Services\RecoveryService;

Env::load();
$config = [];
foreach (['DB_HOST', 'DB_PORT', 'DB_USER', 'DB_PASS', 'DB_PASS_FILE'] as $key) $config[$key] = Env::get($key, $key === 'DB_PORT' ? 3306 : '');
$admin = DatabaseConnection::open($config + ['DB_NAME' => Env::required('DB_NAME')]);
$token = bin2hex(random_bytes(6));
$names = array_map(static fn($suffix) => 'anferalta_verify_' . $token . '_' . $suffix, ['source', 'target', 'dirty']);
$root = dirname(__DIR__) . '/storage/temp/recovery-verify-' . $token;
mkdir($root, 0700, true);
$created = []; $checks = 0; $failures = [];
function recoveryCheck(bool $value, string $name): void { global $checks, $failures; $checks++; if (!$value) $failures[] = $name; }
function recoveryRejected(callable $action): bool { try { $action(); return false; } catch (Throwable $e) { return true; } }
function recoveryFingerprint(PDO $pdo): array {
    $result = [];
    foreach ($pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN) as $name) {
        $rows = [];
        foreach ($pdo->query('SELECT * FROM ' . DatabaseConnection::identifier($name))->fetchAll(PDO::FETCH_NUM) as $row) $rows[] = json_encode(array_map(static fn($v) => $v === null ? null : base64_encode((string) $v), $row));
        sort($rows); $result[$name] = hash('sha256', implode("\n", $rows));
    }
    ksort($result); return $result;
}
try {
    foreach ($names as $name) {
        if (!preg_match('/\Aanferalta_verify_[a-f0-9]{12}_(source|target|dirty)\z/', $name) || $name === Env::get('DB_NAME')) throw new RuntimeException('Unsafe fixture name');
        $admin->exec('CREATE DATABASE ' . DatabaseConnection::identifier($name) . ' CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci'); $created[] = $name;
    }
    [$source, $target, $dirty] = array_map(static fn($name) => DatabaseConnection::open(array_merge($config, ['DB_NAME' => $name])), $names);
    $migrations = dirname(__DIR__) . '/database/migrations';
    $runner = new MigrationService($source, $migrations);
    recoveryCheck(count($runner->migrate()) === 3, 'Fresh schema installs all migrations');
    recoveryCheck($runner->migrate() === [], 'Second migration run is a no-op');
    recoveryCheck((int) $source->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn() === 0, 'Clean schema contains no real users');
    recoveryCheck((int) $source->query('SELECT COUNT(*) FROM documentos')->fetchColumn() === 0, 'Clean schema contains no documents');
    recoveryCheck((int) $source->query("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND ENGINE <> 'InnoDB'")->fetchColumn() === 0, 'All initial tables use InnoDB');
    $source->exec("INSERT INTO utilizadores(id,nome,email,password,perfil_id,ativo) VALUES (1,'Fixture','fixture@example.test','not-a-real-password',1,1)");
    $source->exec("INSERT INTO documento_tipos(tipo_id,nome) VALUES (1,'Fixture')");
    $source->exec("INSERT INTO documentos(id,tipo_id,titulo,ficheiro,criado_por) VALUES (1,1,'Snapshot fixture','active.txt',1)");
    $source->exec("INSERT INTO documento_ficheiros(documento_id,ficheiro,caminho,mime_type,hash) VALUES (1,'active.txt','2026/11','text/plain','hash-fixture')");
    $source->exec("INSERT INTO documentos_arquivados(documento_id,ficheiro,caminho,arquivado_em) VALUES (1,'archived.txt','2026',NOW())");
    $source->exec('CREATE TABLE recovery_fixture(id INT PRIMARY KEY, payload LONGBLOB, note TEXT, nullable TEXT NULL) ENGINE=MyISAM');
    $insert = $source->prepare('INSERT INTO recovery_fixture VALUES (?,?,?,?)');
    $insert->execute([1, "\x00\xff\x80binary", "quotes ' ;\nnew line and UTF-8: ação", null]);
    $sourceStorage = $root . '/source-storage';
    foreach (['documentos/2026/11', 'documentos_arquivados/2026', 'tramitacao/1', 'uploads', 'agendamentos'] as $directory) mkdir($sourceStorage . '/' . $directory, 0700, true);
    $fixtures = ['documentos/2026/11/active.txt' => "active\x00data", 'documentos_arquivados/2026/archived.txt' => 'archived', 'tramitacao/1/anexo.txt' => 'workflow', 'uploads/área.txt' => 'upload', 'agendamentos/agendamentos.json' => '{"enabled":true}'];
    foreach ($fixtures as $path => $value) file_put_contents($sourceStorage . '/' . $path, $value);
    $shared = new \App\Core\MaintenanceLock($sourceStorage, false);
    recoveryCheck(recoveryRejected(fn() => new \App\Core\MaintenanceLock($sourceStorage, true)), 'Maintenance refuses backup while requests are active');
    unset($shared);
    $exclusive = new \App\Core\MaintenanceLock($sourceStorage, true);
    recoveryCheck(recoveryRejected(fn() => new \App\Core\MaintenanceLock($sourceStorage, false)), 'Requests are refused during exclusive maintenance');
    unset($exclusive);
    $archive = $root . '/full.zip'; $password = bin2hex(random_bytes(32));
    $legacy = $root . '/legacy-uploads'; mkdir($legacy); file_put_contents($legacy . '/old.txt', 'legacy-data');
    $service = new RecoveryService($source, $sourceStorage, ['public-uploads' => $legacy]);
    $before = recoveryFingerprint($source);
    $saved = $service->create($archive, $password);
    recoveryCheck(is_file($saved['path']) && $saved['tables'] === 22, 'Full encrypted snapshot created');
    $manifest = $service->inspect($archive, $password);
    recoveryCheck((new RecoveryService(null, $sourceStorage))->inspect($archive, $password)['format'] === $manifest['format'], 'Archive verification works without the source database');
    recoveryCheck(count($manifest['tables']) === 22, 'Every table is verified');
    recoveryCheck(recoveryRejected(fn() => $service->inspect($archive, str_repeat('wrong', 8))), 'Wrong backup key is refused');
    $zip = new ZipArchive(); $zip->open($archive); $allEncrypted = true;
    for ($i = 0; $i < $zip->numFiles; $i++) $allEncrypted = $allEncrypted && $zip->statIndex($i)['encryption_method'] === ZipArchive::EM_AES_256;
    $zip->close(); recoveryCheck($allEncrypted, 'Manifest, database and all files use AES-256');
    // A modified encrypted archive with the same password must still fail the manifest hashes.
    $corrupt = $root . '/corrupt.zip'; copy($archive, $corrupt); $zip = new ZipArchive(); $zip->open($corrupt); $zip->setPassword($password);
    $entry = 'database/recovery_fixture.jsonl'; $zip->addFromString($entry, "tampered\n"); $zip->setEncryptionName($entry, ZipArchive::EM_AES_256, $password); $zip->close();
    $restore = new RecoveryService($target, $root . '/target-storage');
    recoveryCheck(recoveryRejected(fn() => $restore->restore($corrupt, $password)), 'Tampered data refused before restore');
    recoveryCheck($target->query('SHOW TABLES')->fetchAll() === [], 'Invalid archive leaves target DB untouched');
    mkdir($root . '/target-storage');
    file_put_contents($root . '/target-storage/existing.txt', 'do not overwrite');
    recoveryCheck(recoveryRejected(fn() => $restore->restore($archive, $password)), 'Existing storage cannot be overwritten');
    recoveryCheck(file_get_contents($root . '/target-storage/existing.txt') === 'do not overwrite', 'Existing target file is preserved');
    unlink($root . '/target-storage/existing.txt');
    $result = $restore->restore($archive, $password);
    recoveryCheck($result['verified'] && recoveryFingerprint($target) === $before, 'Recovery exactly matches every row, including binary, NULL, references and migration ledger');
    foreach ($fixtures as $path => $value) recoveryCheck(file_get_contents($root . '/target-storage/' . $path) === $value, 'Restored file: ' . $path);
    recoveryCheck(file_get_contents($root . '/target-storage/uploads/legacy/public-uploads/old.txt') === 'legacy-data', 'Legacy uploads preserved in a private recovery directory');
    recoveryCheck(!file_exists($root . '/target-storage/.recovery-incomplete'), 'Successful recovery clears maintenance marker');
    recoveryCheck(recoveryRejected(fn() => $restore->restore($archive, $password)), 'Existing database cannot be overwritten');
    recoveryCheck(recoveryFingerprint($source) === $before, 'Source data remains unchanged');
    // Failed DDL remains dirty; modifying applied migration files is rejected.
    $dirtyDir = $root . '/migrations'; mkdir($dirtyDir);
    file_put_contents($dirtyDir . '/0001_test.sql', 'CREATE TABLE fixture(id INT); INVALID SQL;');
    $badRunner = new MigrationService($dirty, $dirtyDir);
    recoveryCheck(recoveryRejected(fn() => $badRunner->migrate()), 'Invalid migration fails');
    recoveryCheck(recoveryRejected(fn() => $badRunner->status()), 'Interrupted migration cannot silently resume');
    $copyDir = $root . '/migration-copy'; mkdir($copyDir);
    foreach (glob($migrations . '/*.sql') as $file) copy($file, $copyDir . '/' . basename($file));
    file_put_contents($copyDir . '/0001_initial.sql', "\n-- changed\n", FILE_APPEND);
    recoveryCheck(recoveryRejected(fn() => (new MigrationService($source, $copyDir))->status()), 'Modified historical migration rejected');
    recoveryCheck(MigrationService::statements("INSERT INTO t VALUES ('a;b'); -- comment\n SELECT 1;") === ["INSERT INTO t VALUES ('a;b')", 'SELECT 1'], 'SQL parser preserves semicolons inside quoted values');
} finally {
    foreach ($created as $name) {
        if (!in_array($name, $names, true) || !preg_match('/\Aanferalta_verify_[a-f0-9]{12}_(source|target|dirty)\z/', $name)) throw new RuntimeException('Unsafe fixture cleanup');
        $admin->exec('DROP DATABASE ' . DatabaseConnection::identifier($name));
    }
    $resolved = realpath($root); $parent = realpath(dirname(__DIR__) . '/storage/temp');
    if ($resolved && $parent && dirname($resolved) === $parent && basename($root) === 'recovery-verify-' . $token) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) $file->isDir() && !$file->isLink() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        rmdir($root);
    }
}
echo "MySQL recovery checks: {$checks}; failures: " . count($failures) . PHP_EOL;
foreach ($failures as $failure) echo 'FAIL: ' . $failure . PHP_EOL;
exit($failures === [] ? 0 : 1);
