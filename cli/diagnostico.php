<?php
// Read-only CLI diagnosis. Never publish this as a web diagnostic endpoint.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$root = dirname(__DIR__);
$failed = false;
$check = static function (bool $ok, string $label) use (&$failed): void {
    if (!$ok) $failed = true;
    echo ($ok ? '[OK] ' : '[FALTA] ') . $label . PHP_EOL;
};
$check(PHP_VERSION_ID >= 80300, 'PHP >= 8.3; versão CLI: ' . PHP_VERSION);
$check(PHP_INT_SIZE === 8, 'PHP de 64 bits');
foreach (['pdo_mysql', 'fileinfo', 'zip', 'openssl', 'dom', 'xml', 'xmlreader', 'xmlwriter', 'gd', 'iconv'] as $extension) {
    $check(extension_loaded($extension), 'ext-' . $extension);
}
foreach (['vendor/autoload.php', 'App/Core/Env.php', 'App/Services/DatabaseConnection.php', 'App/Core/MaintenanceLock.php', 'App/bootstrap/runtime.php', 'App/bootstrap/app.php', 'public/index.php', 'routes/index.php'] as $relative) {
    $check(is_file($root . '/' . $relative) && is_readable($root . '/' . $relative), $relative . ' presente e legível');
}
if (PHP_VERSION_ID < 80300 || !is_readable($root . '/vendor/autoload.php')) exit(1);
try {
    require $root . '/vendor/autoload.php';
    \App\Core\Env::load();
    $check(true, 'Autoload e configuração carregados');
    $configured = true;
    foreach (['DB_HOST', 'DB_NAME', 'DB_USER'] as $key) {
        $ok = is_string(\App\Core\Env::get($key)) && \App\Core\Env::get($key) !== '';
        $check($ok, $key . ' definido (valor omitido)');
        $configured = $configured && $ok;
    }
    foreach (['DB_PASS_FILE', 'MAIL_PASS_FILE', 'BACKUP_PASSWORD_FILE', 'MAIL_CA_FILE'] as $key) {
        $path = (string) \App\Core\Env::get($key, '');
        if ($path === '') continue;
        $wrongPlatform = PHP_OS_FAMILY !== 'Windows' && preg_match('/\A[A-Za-z]:[\\\\\/]/', $path);
        $ok = !$wrongPlatform && is_file($path) && is_readable($path);
        $check($ok, $key . ($wrongPlatform ? ': caminho Windows incompatível com este servidor' : ': ficheiro existente e legível; caminho e conteúdo omitidos'));
        if ($key === 'DB_PASS_FILE') $configured = $configured && $ok;
    }
    foreach (['storage', 'storage/temp', 'storage/logs', 'storage/sessions'] as $relative) {
        $check(is_dir($root . '/' . $relative) && is_writable($root . '/' . $relative), $relative . ' existe e permite escrita ao PHP CLI');
    }
    foreach (['.installation-incomplete', '.recovery-incomplete'] as $marker) {
        $check(!is_file($root . '/storage/' . $marker), 'Sem marcador de operação incompleta: ' . $marker);
    }
    if ($configured && extension_loaded('pdo_mysql')) {
        try {
            $pdo = \App\Services\DatabaseConnection::open();
            $check((int)$pdo->query('SELECT 1')->fetchColumn() === 1, 'Ligação à base de dados e SELECT 1');
            // Check only schema, never rows, passwords or document contents.
            $pdo->query('SELECT id, email, ativo, aprovado_em FROM utilizadores LIMIT 0');
            $pdo->query('SELECT id FROM perfis LIMIT 0');
            $pdo->query('SELECT codigo FROM permissoes LIMIT 0');
            $check(true, 'Tabelas/colunas base de autenticação acessíveis');
        } catch (\PDOException $error) {
            $code = (int)($error->errorInfo[1] ?? 0);
            $reason = match ($code) {
                1045 => 'Credenciais ou permissões MySQL recusadas',
                1049 => 'Base de dados configurada não existe',
                2002, 2003 => 'Não foi possível estabelecer ligação MySQL',
                1054, 1146 => 'Tabela/coluna necessária ausente: verificar atualização do esquema',
                default => 'Falha MySQL: consultar o log privado do alojamento',
            };
            $check(false, $reason . '; código ' . $code . ' (detalhes privados omitidos)');
        }
    }
} catch (\Throwable $error) {
    $check(false, 'Erro ao carregar dependências/configuração: ' . get_class($error) . '; consultar o log privado, sem publicar segredos');
}
echo 'Diagnóstico de leitura: não executa migrações, não altera dados e não envia emails.' . PHP_EOL;
echo 'Confirmar também a versão/permissões do PHP web e DocumentRoot exclusivo em public/.' . PHP_EOL;
exit($failed ? 1 : 0);
