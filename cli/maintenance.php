<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/App/bootstrap/runtime.php';

use App\Core\Env;
use App\Core\MaintenanceLock;
use App\Services\DatabaseConnection;
use App\Services\MigrationService;
use App\Services\RecoveryService;

$command = $argv[1] ?? 'help';
$options = [];
foreach (array_slice($argv, 2) as $argument) {
    if (!preg_match('/\A--([a-z-]+)=(.+)\z/s', $argument, $matches)) throw new RuntimeException('Use opções --nome=valor.');
    $options[$matches[1]] = $matches[2];
}
$migrations = BASE_PATH . '/database/migrations';
$password = static fn() => Env::secret('BACKUP_PASSWORD');
$backup = static function ($pdo) use ($password): array {
    $directory = BASE_PATH . '/backups/Completos';
    if (!is_dir($directory) && !mkdir($directory, 0700, true)) throw new RuntimeException('Não foi possível criar a pasta de backups.');
    $legacy = ['public-uploads' => BASE_PATH . '/public/uploads', 'app-public-uploads' => BASE_PATH . '/App/public/uploads', 'app-documents' => BASE_PATH . '/App/storage/documentos'];
    return (new RecoveryService($pdo, STORAGE_PATH, $legacy))->create($directory . '/full-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.zip', $password());
};

switch ($command) {
    case 'status':
        $result = (new MigrationService(DatabaseConnection::open(), $migrations))->status();
        $result = array_map(static fn($m) => ['version' => $m['version'], 'applied' => $m['applied']], $result);
        break;
    case 'migrate':
    case 'install':
        $maintenance = new MaintenanceLock(STORAGE_PATH, true);
        $pdo = DatabaseConnection::open();
        if ($command === 'install' && $pdo->query('SHOW TABLES')->fetchAll()) throw new RuntimeException('A instalação inicial exige uma base vazia. Use migrate para uma instalação existente.');
        if ($command === 'install') {
            $email = $options['admin-email'] ?? '';
            $adminPassword = isset($options['admin-password-file']) ? Env::readSecretFile($options['admin-password-file']) : '';
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($adminPassword) < 14) throw new RuntimeException('Indique um email válido e um ficheiro privado com password de pelo menos 14 caracteres.');
        }
        $result = ['migrations' => (new MigrationService($pdo, $migrations))->migrate(static function () use ($backup, $pdo): void {
            $saved = $backup($pdo);
            // Path and checksum only; never credentials or operational data.
            fwrite(STDOUT, json_encode(['pre_migration_backup' => $saved], JSON_UNESCAPED_SLASHES) . PHP_EOL);
        })];
        if ($command === 'install') {
            $stmt = $pdo->prepare('INSERT INTO utilizadores(nome,email,password,perfil_id,ativo,aprovado_em) VALUES (?,?,?,1,1,NOW())');
            $stmt->execute([$options['admin-name'] ?? 'Administrador', strtolower(trim($email)), password_hash($adminPassword, PASSWORD_DEFAULT)]);
            $result['administrator_created'] = true;
        }
        break;
    case 'backup':
        $maintenance = new MaintenanceLock(STORAGE_PATH, true);
        $result = $backup(DatabaseConnection::open());
        break;
    case 'verify':
        if (empty($options['archive'])) throw new RuntimeException('Indique --archive.');
        $manifest = (new RecoveryService(null, STORAGE_PATH))->inspect($options['archive'], $password());
        $result = ['verified' => true, 'tables' => count($manifest['tables']), 'entries' => count($manifest['entries'])];
        break;
    case 'restore':
        foreach (['archive', 'target-config', 'target-storage'] as $key) if (empty($options[$key])) throw new RuntimeException('Opção obrigatória: --' . $key);
        $config = Dotenv\Dotenv::parse(file_get_contents($options['target-config']));
        $target = DatabaseConnection::open($config);
        // No connection to the failed source is required. The service refuses
        // ANY non-empty target database or storage before making changes.
        $result = (new RecoveryService($target, $options['target-storage']))->restore($options['archive'], $password());
        break;
    default:
        fwrite(STDOUT, "Comandos: status, install, migrate, backup, verify, restore.\nConsulte docs/recuperacao-e-instalacao.md.\n");
        exit($command === 'help' ? 0 : 1);
}
fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
