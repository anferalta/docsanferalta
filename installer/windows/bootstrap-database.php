<?php
// CLI-only; credentials are read from private files, never process arguments/output.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit(1); }
try {
    $config = json_decode(file_get_contents($argv[1] ?? ''), true, 16, JSON_THROW_ON_ERROR);
    $read = static function (string $name) use ($config): string {
        $value = file_get_contents($config['private'] . '/' . $name . '.key');
        if (!preg_match('/\A[a-f0-9]{64}\z/', $value)) throw new RuntimeException('Invalid generated credential');
        return $value;
    };
    $root = $read('mysql-root');
    $dsn = 'mysql:host=127.0.0.1;port=' . (int)($config['port'] ?? 3306) . ';charset=utf8mb4';
    $options = [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false];
    try { $pdo = new PDO($dsn, 'root', $root, $options); }
    catch (PDOException $error) {
        // Only the newly initialized, installer-owned service is allowed to start blank.
        if (!is_file($config['private'] . '/mysql-initialized')) throw $error;
        $pdo = new PDO($dsn, 'root', '', $options);
    }
    $normalize = static fn(string $p): string => strtolower(rtrim(str_replace('\\', '/', $p), '/'));
    if ($normalize($pdo->query('SELECT @@datadir')->fetchColumn()) !== $normalize($config['data'])) throw new RuntimeException('Unexpected MySQL data directory');
    $pdo->exec("ALTER USER 'root'@'localhost' IDENTIFIED BY " . $pdo->quote($root));
    $pdo->exec('CREATE DATABASE IF NOT EXISTS `anferaltadocs` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    foreach (['maintenance' => 'anferalta_maintenance', 'application' => 'anferalta_app'] as $file => $user) {
        $password = $read('mysql-' . $file);
        $account = $pdo->quote($user) . "@'127.0.0.1'";
        $pdo->exec('CREATE USER IF NOT EXISTS ' . $account . ' IDENTIFIED BY ' . $pdo->quote($password));
        $pdo->exec('ALTER USER ' . $account . ' IDENTIFIED BY ' . $pdo->quote($password));
        $pdo->exec('REVOKE ALL PRIVILEGES, GRANT OPTION FROM ' . $account);
        $privileges = $file === 'maintenance' ? 'ALL PRIVILEGES' : 'SELECT, INSERT, UPDATE, DELETE';
        $pdo->exec('GRANT ' . $privileges . ' ON `anferaltadocs`.* TO ' . $account);
    }
    echo "Database accounts configured.\n";
} catch (Throwable $error) {
    // SQL failures can contain credentials; keep them out of the installer log/UI.
    fwrite(STDERR, "Falha na configuração das contas MySQL. Verifique o serviço e o diretório de dados privado.\n");
    exit(1);
}
