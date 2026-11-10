<?php
namespace App\Services;

use App\Core\Env;
use PDO;

/** Installs only a fresh database; never adopts an existing installation. */
final class InstallerService
{
    public function __construct(private string $root, private string $migrations) {}

    public static function requirements(): array
    {
        $checks = ['PHP >= 8.3' => PHP_VERSION_ID >= 80300, 'PHP 64 bits' => PHP_INT_SIZE === 8];
        $checks['proc_open (instalador CLI)'] = function_exists('proc_open');
        foreach (['pdo_mysql', 'fileinfo', 'zip', 'openssl', 'dom', 'xml', 'xmlreader', 'xmlwriter', 'gd', 'iconv'] as $extension) $checks['ext-' . $extension] = extension_loaded($extension);
        $checks['ZIP AES-256'] = class_exists(\ZipArchive::class) && \ZipArchive::isEncryptionMethodSupported(\ZipArchive::EM_AES_256);
        return $checks;
    }

    public function plan(array $input, bool $resume = false): array
    {
        $statePath = $this->root . '/storage/installation-state.json';
        $state = is_file($statePath) ? json_decode(file_get_contents($statePath), true, 512, JSON_THROW_ON_ERROR) : null;
        if (file_exists($this->root . '/.env') && !($resume && $state !== null && is_file($this->root . '/storage/.installation-incomplete'))) throw new \RuntimeException('Já existe .env. Para atualizar uma instalação use cli/maintenance.php migrate.');
        $config = self::validate($input);
        if (in_array(false, self::requirements(), true)) throw new \RuntimeException('Há requisitos PHP em falta.');
        if (!is_dir($this->root) || !is_writable($this->root)) throw new \RuntimeException('A pasta de instalação não permite escrita.');
        if ($state !== null && (!$resume || ($state['target'] ?? '') !== self::target($config))) throw new \RuntimeException('Instalação interrompida: use --resume com a mesma configuração e consulte o estado privado.');
        if ($resume && $state === null) throw new \RuntimeException('Não existe instalação interrompida para retomar.');
        $pdo = DatabaseConnection::open($config);
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if ($tables && !$resume) throw new \RuntimeException('A base de dados deve estar vazia. Nenhum dado foi alterado.');
        if ($resume) (new MigrationService($pdo, $this->migrations))->status();
        return ['url' => $config['APP_URL'], 'database' => $config['DB_NAME'], 'administrator' => $config['ADMIN_EMAIL'], 'resume' => $resume, 'migrations' => count(glob($this->migrations . '/*.sql')), 'smtp_configured' => !empty($config['MAIL_HOST'])];
    }

    public function install(array $input, bool $resume = false): array
    {
        $this->plan($input, $resume);
        $config = self::validate($input);
        $storage = $this->root . '/storage';
        foreach (['', '/documentos', '/documentos_arquivados', '/tramitacao', '/uploads', '/agendamentos', '/temp', '/logs', '/sessions', '/backups'] as $suffix) self::directory($storage . $suffix);
        $lock = fopen($storage . '/.installer.lock', 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) throw new \RuntimeException('Outro instalador está em execução.');
        try {
            if (file_exists($this->root . '/.env') && !($resume && is_file($storage . '/.installation-incomplete'))) throw new \RuntimeException('A instalação já foi concluída por outro processo.');
            $secrets = $this->root . '/secrets';
            self::directory($secrets);
            self::protectWindows($secrets);
            $state = ['target' => self::target($config), 'phase' => 'preparing', 'started_at' => gmdate('c')];
            self::write($storage . '/.installation-incomplete', 'Installation in progress.');
            self::write($storage . '/installation-state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $backupKey = $secrets . '/recovery.key';
            if (!is_file($backupKey)) self::write($backupKey, base64_encode(random_bytes(48)), true);
            if (strlen(Env::readSecretFile($backupKey)) < 32) throw new \RuntimeException('A chave de recuperação existente é inválida.');
            $escrow = $secrets . '/recovery.escrow';
            if (!is_file($escrow)) self::write($escrow, Env::readSecretFile($backupKey), true);
            if (!hash_equals(Env::readSecretFile($backupKey), Env::readSecretFile($escrow))) throw new \RuntimeException('As cópias da chave de recuperação não correspondem.');
            if (!chmod($backupKey, 0400) || !chmod($escrow, 0400)) throw new \RuntimeException('Não foi possível proteger as chaves contra escrita.');
            $adminFile = $secrets . '/initial-admin-password.txt';
            if (!is_file($adminFile)) self::write($adminFile, $config['ADMIN_PASSWORD'], true);
            $adminPassword = Env::readSecretFile($adminFile);
            if (!$resume && !hash_equals($config['ADMIN_PASSWORD'], $adminPassword)) throw new \RuntimeException('Já existe uma password inicial diferente.');
            self::write($secrets . '/database-password.txt', $config['DB_PASS']);
            if (!empty($config['MAIL_HOST'])) self::write($secrets . '/smtp-password.txt', $config['MAIL_PASS']);
            $pdo = DatabaseConnection::open($config);
            $runner = new MigrationService($pdo, $this->migrations);
            // Resuming only accepts this installer's recorded target. DDL failure
            // remains dirty and is rejected by MigrationService::status().
            $applied = $runner->migrate($resume ? function () use ($pdo, $storage, $backupKey): void {
                $directory = $this->root . '/backups'; self::directory($directory);
                (new RecoveryService($pdo, $storage))->create($directory . '/installation-resume-' . bin2hex(random_bytes(8)) . '.zip', Env::readSecretFile($backupKey));
            } : null);
            $state['phase'] = 'schema-ready';
            self::write($storage . '/installation-state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            $existing = $pdo->query('SELECT email,password,perfil_id FROM utilizadores')->fetchAll(PDO::FETCH_ASSOC);
            if ($existing) {
                if (!$resume || count($existing) !== 1 || $existing[0]['email'] !== $config['ADMIN_EMAIL'] || (int) $existing[0]['perfil_id'] !== 1 || !password_verify($adminPassword, $existing[0]['password'])) throw new \RuntimeException('O administrador existente não corresponde à instalação interrompida.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO utilizadores(nome,email,password,perfil_id,ativo,aprovado_em) VALUES (?,?,?,1,1,NOW())');
                $stmt->execute([$config['ADMIN_NAME'], $config['ADMIN_EMAIL'], password_hash($adminPassword, PASSWORD_DEFAULT)]);
            }
            $env = [
                'APP_ENV' => $config['APP_ENV'], 'APP_DEBUG' => 'false', 'APP_URL' => $config['APP_URL'], 'APP_TIMEZONE' => $config['APP_TIMEZONE'],
                'DB_HOST' => $config['DB_HOST'], 'DB_PORT' => (string) $config['DB_PORT'], 'DB_NAME' => $config['DB_NAME'], 'DB_USER' => $config['DB_USER'], 'DB_PASS_FILE' => $secrets . '/database-password.txt',
                'SESSION_COOKIE_SECURE' => str_starts_with($config['APP_URL'], 'https://') ? 'true' : 'false', 'SESSION_IDLE_TIMEOUT' => '1800',
                'BACKUP_PASSWORD_FILE' => $backupKey, 'ALLOW_LEGACY_RESTORE' => 'false', 'ADMIN_EMAILS' => $config['ADMIN_EMAIL'], 'SLA_REPORT_EMAILS' => $config['ADMIN_EMAIL'],
            ];
            if (!empty($config['MAIL_HOST'])) {
                foreach (['MAIL_HOST', 'MAIL_PORT', 'MAIL_ENCRYPTION', 'MAIL_USER', 'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_CA_FILE'] as $key) $env[$key] = (string) ($config[$key] ?? '');
                $env['MAIL_AUTH'] = 'true'; $env['MAIL_PASS_FILE'] = $secrets . '/smtp-password.txt';
            }
            $content = '';
            foreach ($env as $key => $value) $content .= $key . '=' . self::quote($value) . "\n";
            $parsed = \Dotenv\Dotenv::parse($content);
            if ($parsed !== $env) throw new \RuntimeException('A configuração gerada não passou na verificação.');
            if (file_exists($this->root . '/.env')) {
                if (!$resume || is_link($this->root . '/.env') || !hash_equals($content, file_get_contents($this->root . '/.env'))) throw new \RuntimeException('A configuração publicada foi alterada; rever antes de retomar.');
            } else {
                $pending = $this->root . '/.env.installing';
                if (is_file($pending)) {
                    if (!$resume || is_link($pending) || !hash_equals($content, file_get_contents($pending))) throw new \RuntimeException('Configuração temporária diferente: rever antes de retomar.');
                } else self::write($pending, $content, true);
                if (!link($pending, $this->root . '/.env')) throw new \RuntimeException('Não foi possível publicar .env sem substituir configuração existente.');
                unlink($pending);
            }
            $state['phase'] = 'complete'; $state['completed_at'] = gmdate('c');
            self::write($storage . '/installation-state.json', json_encode($state, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
            unlink($storage . '/.installation-incomplete');
            return ['installed' => true, 'url' => $config['APP_URL'] . '/login', 'migrations_applied' => $applied, 'administrator' => $config['ADMIN_EMAIL'], 'initial_password_file' => $adminFile, 'recovery_key_file' => $backupKey, 'document_root' => $this->root . '/public'];
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private static function validate(array $input): array
    {
        $config = array_merge(['APP_ENV' => 'production', 'APP_TIMEZONE' => 'Europe/Lisbon', 'DB_PORT' => 3306, 'ADMIN_NAME' => 'Administrador', 'MAIL_HOST' => ''], $input);
        foreach (['APP_ENV', 'APP_TIMEZONE', 'ADMIN_NAME', 'MAIL_HOST', 'MAIL_USER', 'MAIL_FROM', 'MAIL_FROM_NAME', 'MAIL_CA_FILE'] as $key) {
            if (isset($config[$key]) && (!is_string($config[$key]) || preg_match('/[\x00-\x1f]/', $config[$key]))) throw new \RuntimeException('Parâmetro inválido: ' . $key);
        }
        foreach (['APP_URL', 'DB_HOST', 'DB_NAME', 'DB_USER', 'ADMIN_EMAIL'] as $key) if (!is_string($config[$key] ?? null) || trim($config[$key]) === '' || preg_match('/[\x00-\x1f]/', $config[$key])) throw new \RuntimeException('Parâmetro inválido: ' . $key);
        if (!in_array($config['APP_ENV'], ['production', 'local'], true)) throw new \RuntimeException('APP_ENV inválido.');
        if (!filter_var($config['DB_PORT'], FILTER_VALIDATE_INT) || (int) $config['DB_PORT'] < 1 || (int) $config['DB_PORT'] > 65535) throw new \RuntimeException('Porta MySQL inválida.');
        $config['DB_PORT'] = (int) $config['DB_PORT'];
        if (trim($config['ADMIN_NAME']) === '') throw new \RuntimeException('Nome do administrador obrigatório.');
        $url = parse_url($config['APP_URL']);
        if (!filter_var($config['APP_URL'], FILTER_VALIDATE_URL) || !in_array($url['scheme'] ?? '', ['https', 'http'], true) || isset($url['user']) || isset($url['pass']) || isset($url['query']) || isset($url['fragment']) || !in_array($url['path'] ?? '', ['', '/'], true)) throw new \RuntimeException('APP_URL deve indicar a raiz do domínio, sem credenciais ou parâmetros.');
        if ($config['APP_ENV'] === 'production' && ($url['scheme'] !== 'https' || strtolower($config['DB_USER']) === 'root')) throw new \RuntimeException('Produção exige HTTPS e uma conta MySQL dedicada.');
        if (!in_array($config['APP_TIMEZONE'], timezone_identifiers_list(), true)) throw new \RuntimeException('Fuso horário inválido.');
        DatabaseConnection::identifier($config['DB_NAME']);
        $config['DB_PASS'] = isset($config['DB_PASS_FILE']) && $config['DB_PASS_FILE'] !== '' ? Env::readSecretFile($config['DB_PASS_FILE']) : (string) ($config['DB_PASS'] ?? '');
        unset($config['DB_PASS_FILE']);
        if ($config['APP_ENV'] === 'production' && $config['DB_PASS'] === '') throw new \RuntimeException('Produção exige uma credencial MySQL própria.');
        $config['ADMIN_PASSWORD'] = !empty($config['ADMIN_PASSWORD_FILE']) ? Env::readSecretFile($config['ADMIN_PASSWORD_FILE']) : (string) ($config['ADMIN_PASSWORD'] ?? '');
        if (strlen($config['ADMIN_PASSWORD']) < 14 || !filter_var($config['ADMIN_EMAIL'], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Email administrativo inválido ou password inicial com menos de 14 caracteres.');
        $config['ADMIN_EMAIL'] = strtolower(trim($config['ADMIN_EMAIL'])); $config['APP_URL'] = rtrim($config['APP_URL'], '/');
        if (!empty($config['MAIL_HOST'])) {
            foreach (['MAIL_USER', 'MAIL_FROM'] as $key) if (empty($config[$key])) throw new \RuntimeException('Configuração SMTP incompleta: ' . $key);
            if (!filter_var($config['MAIL_FROM'], FILTER_VALIDATE_EMAIL)) throw new \RuntimeException('Remetente SMTP inválido.');
            $config += ['MAIL_PORT' => 465, 'MAIL_ENCRYPTION' => 'smtps', 'MAIL_FROM_NAME' => 'Gestão Documental', 'MAIL_CA_FILE' => ''];
            if (!in_array($config['MAIL_ENCRYPTION'], ['smtps', 'tls'], true) || !filter_var($config['MAIL_PORT'], FILTER_VALIDATE_INT) || $config['MAIL_PORT'] < 1 || $config['MAIL_PORT'] > 65535) throw new \RuntimeException('Porta ou modo SMTP inválido.');
            $config['MAIL_PASS'] = !empty($config['MAIL_PASS_FILE']) ? Env::readSecretFile($config['MAIL_PASS_FILE']) : (string) ($config['MAIL_PASS'] ?? '');
            if ($config['MAIL_PASS'] === '') throw new \RuntimeException('Falta a credencial SMTP.');
        }
        return $config;
    }

    private static function target(array $config): string
    {
        return hash('sha256', json_encode(array_intersect_key($config, array_flip(['DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_USER', 'APP_URL', 'ADMIN_EMAIL'])), JSON_THROW_ON_ERROR));
    }

    private static function quote(string $value): string
    {
        if (preg_match('/[\x00-\x1f]/', $value)) throw new \RuntimeException('Valor de configuração contém caracteres de controlo.');
        return '"' . str_replace(['\\', '"', '$'], ['\\\\', '\\"', '\\$'], $value) . '"';
    }

    private static function directory(string $path): void
    {
        if (is_link($path)) throw new \RuntimeException('Não instalar sobre ligações simbólicas.');
        if (!is_dir($path) && !mkdir($path, 0700, true) && !is_dir($path)) throw new \RuntimeException('Não foi possível criar a pasta privada.');
    }

    private static function write(string $path, string $content, bool $new = false): void
    {
        if (is_link($path)) throw new \RuntimeException('Ficheiro privado inválido.');
        $handle = fopen($path, $new ? 'xb' : 'wb');
        if (!$handle) throw new \RuntimeException('Não foi possível preparar o ficheiro privado.');
        try { if (fwrite($handle, $content) !== strlen($content) || !fflush($handle)) throw new \RuntimeException('Falha ao gravar ficheiro privado.'); }
        finally { fclose($handle); }
        chmod($path, 0600);
    }

    private static function protectWindows(string $path): void
    {
        if (PHP_OS_FAMILY !== 'Windows') return;
        $process = proc_open(['whoami.exe', '/user', '/fo', 'csv', '/nh'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Não foi possível identificar a conta Windows.');
        $identity = stream_get_contents($pipes[1]); fclose($pipes[1]); fclose($pipes[2]); $code = proc_close($process);
        if ($code !== 0 || !preg_match('/S-1-5-[0-9-]+/', $identity, $matches)) throw new \RuntimeException('Não foi possível identificar a conta Windows.');
        $process = proc_open(['icacls.exe', $path, '/inheritance:r', '/grant:r', '*' . $matches[0] . ':(OI)(CI)F', '*S-1-5-18:(OI)(CI)F'], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) throw new \RuntimeException('Não foi possível proteger os ficheiros privados.');
        stream_get_contents($pipes[1]); stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new \RuntimeException('O Windows recusou a proteção dos ficheiros privados.');
    }
}
