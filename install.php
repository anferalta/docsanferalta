<?php
// Run from a terminal: php install.php. This entry point is never a web installer.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
ini_set('display_errors', '0');
if (PHP_VERSION_ID < 80300 || PHP_INT_SIZE !== 8) { fwrite(STDERR, "É necessário PHP 8.3+ de 64 bits.\n"); exit(1); }
$root = __DIR__;
$options = ['check' => false, 'plan' => false, 'yes' => false, 'resume' => false];
foreach (array_slice($argv, 1) as $argument) {
    if (in_array($argument, ['--check', '--plan', '--yes', '--resume', '--help'], true)) $options[substr($argument, 2)] = true;
    elseif (preg_match('/\A--(config|composer-phar)=(.+)\z/s', $argument, $match)) $options[$match[1]] = $match[2];
    else { fwrite(STDERR, "Opção desconhecida. Use php install.php --help.\n"); exit(1); }
}
if (!empty($options['help'])) {
    echo "Instalador AnferaltaDocs\n\nphp install.php\nphp install.php --check\nphp install.php --config=/caminho/privado/install.json --plan\nphp install.php --config=/caminho/privado/install.json --yes\nphp install.php --config=/caminho/privado/install.json --resume --yes\n\nRequisitos: PHP 8.3+, Composer e uma base MySQL vazia.\nNão passar palavras-passe em argumentos. Consulte docs/instalador.md.\n";
    exit;
}
if (!$options['check'] && file_exists($root . '/.env') && !($options['resume'] && is_file($root . '/storage/.installation-incomplete') && is_file($root . '/storage/installation-state.json'))) { fwrite(STDERR, "Já existe uma instalação/configuração. Use cli/maintenance.php migrate.\n"); exit(1); }
// Check extensions before Composer/autoload can abort on platform requirements.
require_once $root . '/App/Services/InstallerService.php';
$preflight = \App\Services\InstallerService::requirements();
if (in_array(false, $preflight, true)) {
    foreach ($preflight as $label => $passed) echo ($passed ? '[OK] ' : '[FALTA] ') . $label . PHP_EOL;
    fwrite(STDERR, "Corrija os requisitos do PHP CLI antes de instalar.\n");
    exit(1);
}
if (!file_exists($root . '/vendor/autoload.php') && !$options['check'] && !$options['plan']) {
    if (!function_exists('proc_open')) { fwrite(STDERR, "O PHP CLI precisa de proc_open para executar o Composer.\n"); exit(1); }
    fwrite(STDOUT, "A instalar as dependências fixadas no composer.lock...\n");
    $command = isset($options['composer-phar'])
        ? [PHP_BINARY, $options['composer-phar'], 'install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', '--no-scripts', '--no-plugins']
        : 'composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction --no-scripts --no-plugins';
    // The shell fallback is a fixed command: no configuration or secrets are interpolated.
    $process = proc_open($command, [0 => STDIN, 1 => STDOUT, 2 => STDERR], $pipes, $root);
    if (!is_resource($process) || proc_close($process) !== 0) { fwrite(STDERR, "Instale o Composer e repita, ou indique --composer-phar=/caminho/composer.phar.\n"); exit(1); }
}
if (is_file($root . '/vendor/autoload.php')) require $root . '/vendor/autoload.php';

use App\Services\InstallerService;

function installationPrompt(string $label, string $default = ''): string {
    fwrite(STDOUT, $label . ($default !== '' ? ' [' . $default . ']' : '') . ': ');
    $line = fgets(STDIN);
    if ($line === false) throw new RuntimeException('Entrada terminada. Para automação use --config e --yes.');
    $value = trim($line);
    return $value !== '' ? $value : $default;
}

try {
    $requirements = InstallerService::requirements();
    $requirements['vendor/autoload.php'] = is_file($root . '/vendor/autoload.php');
    foreach ($requirements as $label => $passed) echo ($passed ? '[OK] ' : '[FALTA] ') . $label . PHP_EOL;
    if ($options['check']) { echo file_exists($root . '/.env') ? "Instalação existente detetada; nenhum ficheiro ou dado foi alterado.\n" : "Ainda sem configuração de instalação.\n"; exit(in_array(false, $requirements, true) ? 1 : 0); }
    if (in_array(false, $requirements, true)) throw new RuntimeException('Corrija os requisitos antes de instalar.');
    if (isset($options['config'])) {
        if (!is_file($options['config']) || filesize($options['config']) > 65536) throw new RuntimeException('Ficheiro de configuração inválido.');
        $config = json_decode(file_get_contents($options['config']), true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($config)) throw new RuntimeException('A configuração deve ser um objeto JSON.');
    } else {
        echo "\nInstalação inicial. As passwords são lidas de ficheiros privados.\n";
        $config = [
            'APP_URL' => installationPrompt('URL HTTPS da aplicação'),
            'APP_TIMEZONE' => installationPrompt('Fuso horário', 'Europe/Lisbon'),
            'DB_HOST' => installationPrompt('Servidor MySQL', '127.0.0.1'),
            'DB_PORT' => installationPrompt('Porta MySQL', '3306'),
            'DB_NAME' => installationPrompt('Nome da base vazia'),
            'DB_USER' => installationPrompt('Utilizador MySQL dedicado'),
            'DB_PASS_FILE' => installationPrompt('Caminho do ficheiro com a password MySQL'),
            'ADMIN_EMAIL' => installationPrompt('Email do primeiro administrador'),
            'ADMIN_NAME' => installationPrompt('Nome do administrador', 'Administrador'),
            'ADMIN_PASSWORD_FILE' => installationPrompt('Ficheiro da password inicial (Enter para gerar uma própria)'),
        ];
        if (strtolower(installationPrompt('Configurar SMTP agora? s/n', 'n')) === 's') {
            $config += [
                'MAIL_HOST' => installationPrompt('Servidor SMTP'), 'MAIL_PORT' => installationPrompt('Porta SMTP', '465'),
                'MAIL_ENCRYPTION' => installationPrompt('Encriptação smtps/tls', 'smtps'), 'MAIL_USER' => installationPrompt('Utilizador SMTP'),
                'MAIL_PASS_FILE' => installationPrompt('Ficheiro da password SMTP'), 'MAIL_FROM' => installationPrompt('Email do remetente'),
                'MAIL_FROM_NAME' => installationPrompt('Nome do remetente', 'Gestão Documental'),
                'MAIL_CA_FILE' => installationPrompt('Ficheiro CA adicional (opcional)'),
            ];
        }
    }
    if (empty($config['ADMIN_PASSWORD_FILE']) && empty($config['ADMIN_PASSWORD'])) $config['ADMIN_PASSWORD'] = base64_encode(random_bytes(24));
    $installer = new InstallerService($root, $root . '/database/migrations');
    $plan = $installer->plan($config, $options['resume']);
    echo "\nPlano (sem passwords):\n" . json_encode($plan, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if ($options['plan']) { echo "Simulação concluída; nenhum ficheiro ou dado foi alterado.\n"; exit; }
    if (!$options['yes'] && strtolower(installationPrompt('Executar esta instalação? s/n', 'n')) !== 's') { echo "Instalação cancelada sem alterações.\n"; exit; }
    $result = $installer->install($config, $options['resume']);
    echo "\nInstalação concluída:\n" . json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    echo "\nConfigure o servidor HTTPS com DocumentRoot em public/. Ajuste a conta/grupo do PHP para ler a configuração e escrever no storage.\nGuarde a chave de recuperação fora do servidor, entre com a password inicial do ficheiro privado e altere-a no perfil.\n";
} catch (Throwable $error) {
    // PDO errors are intentionally not printed: server details belong in private diagnostics.
    $message = $error instanceof PDOException ? 'Não foi possível preparar a base de dados. Verifique a ligação e os privilégios; nenhum dado existente é substituído.' : $error->getMessage();
    fwrite(STDERR, 'Instalação não concluída: ' . $message . PHP_EOL);
    exit(1);
}
