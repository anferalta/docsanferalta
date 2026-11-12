<?php
// Real registration controller/models + in-memory SQLite. No SMTP/network or production DB.
namespace App\Core {
    class Helpers {
        public static ?string $destination = null;
        public static function redirect(string $url): void { self::$destination = $url; http_response_code(302); }
    }
}
namespace App\Services {
    class EmailService {
        public static string $mode = 'success';
        public static int $calls = 0;
        public static function enviar(string|array $para, string $assunto, string $template, array $dados = [], array $anexos = []): bool {
            self::$calls++;
            if (self::$mode === 'rejected') throw new \PHPMailer\PHPMailer\Exception('SMTP recipient rejected: internal-fixture-detail');
            if (self::$mode === 'configuration') throw new \RuntimeException('Private SMTP configuration unavailable');
            return self::$mode !== 'false';
        }
    }
}
namespace {
    require dirname(__DIR__) . '/vendor/autoload.php';
    use App\Controllers\AuthController;
    use App\Core\Conexao;
    use App\Core\Helpers;
    use App\Core\Sessao;
    use App\Services\EmailService;

    class RegistrationFixtureController extends AuthController {
        public ?array $failure = null;
        public function __construct() {}
        public function error($codigo, $mensagem = null, $extra = []) {
            $this->failure = [$codigo, $mensagem]; http_response_code($codigo);
        }
    }
    error_reporting(E_ALL);
    // Keep session data in memory, independent of the host's PHP session path.
    session_set_save_handler(new class implements \SessionHandlerInterface {
        public function open(string $path, string $name): bool { return true; }
        public function close(): bool { return true; }
        public function read(string $id): string|false { return ''; }
        public function write(string $id, string $data): bool { return true; }
        public function destroy(string $id): bool { return true; }
        public function gc(int $max_lifetime): int|false { return 0; }
    });
    session_start(['use_cookies' => 0, 'cache_limiter' => '']);
    $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
    $db->exec('CREATE TABLE utilizadores (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT, email TEXT UNIQUE, password TEXT, perfil_id INTEGER, ativo INTEGER, aprovado_em TEXT)');
    $connection = new ReflectionProperty(Conexao::class, 'instancia');
    $connection->setValue(null, $db);
    $checks = 0; $failures = [];
    $verify = static function (bool $valid, string $label) use (&$checks, &$failures): void { $checks++; if (!$valid) $failures[] = $label; };
    $submit = static function (string $email, string $mode) {
        $_SESSION = []; Helpers::$destination = null; EmailService::$mode = $mode;
        $_POST = ['nome' => 'Fixture', 'email' => $email, 'password' => 'Fixture-password-123!', 'password_confirm' => 'Fixture-password-123!'];
        $controller = new RegistrationFixtureController(); $controller->registarSubmit(); return $controller;
    };
    foreach (['rejected', 'configuration', 'false', 'success'] as $mode) {
        $controller = $submit($mode . '@example.test', $mode);
        $verify($controller->failure === null && http_response_code() === 302 && Helpers::$destination === '/login', $mode . ': redirects to login without 500');
        $row = $db->query("SELECT * FROM utilizadores WHERE email = '" . $mode . "@example.test'")->fetch(PDO::FETCH_ASSOC);
        $verify($row && (int)$row['ativo'] === 0 && $row['aprovado_em'] === null, $mode . ': account persists pending approval');
        $verify($row && password_verify('Fixture-password-123!', $row['password']), $mode . ': password is hashed');
        $verify(str_contains(Sessao::peek('sucesso') ?? '', 'Conta criada'), $mode . ': account creation reported truthfully');
        $verify(($mode === 'success') === (Sessao::peek('aviso') === null), $mode . ': email warning only when delivery fails');
        $visible = json_encode(Sessao::all());
        $verify(!str_contains($visible, 'internal-fixture-detail') && !str_contains($visible, 'Private SMTP'), $mode . ': SMTP diagnostics not exposed');
    }
    $count = EmailService::$calls;
    $controller = $submit('rejected@example.test', 'success');
    $verify($controller->failure === null && Helpers::$destination === '/registar', 'Retry of existing account returns validation error, not 500');
    $verify((int)$db->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn() === 4 && EmailService::$calls === $count, 'Retry neither duplicates account nor sends another notification');
    $verify(str_contains(Sessao::peek('erro') ?? '', 'já está registado'), 'Existing account is identified');

    $twig = new \Twig\Environment(new \Twig\Loader\FilesystemLoader(dirname(__DIR__) . '/App/Views'), ['autoescape' => 'html']);
    $_SESSION = [];
    Sessao::flash('sucesso', 'Conta criada'); Sessao::flash('aviso', '<script>fixture</script>');
    $html = $twig->render('site/partials/_flash.twig', ['sessao' => new Sessao()]);
    $verify(str_contains($html, 'alert-success') && str_contains($html, 'alert-warning'), 'Login partial displays both creation result and delivery warning');
    $verify(!str_contains($html, '<script>fixture</script>') && str_contains($html, '&lt;script&gt;'), 'Warning content is HTML escaped');
    $verify(Sessao::peek('aviso') === null, 'Warning is consumed after display');

    $db->exec('DROP TABLE utilizadores');
    $controller = $submit('database-failure@example.test', 'success');
    $verify($controller->failure !== null && $controller->failure[0] === 500, 'Real database failure still reports an error');
    $verify(!str_contains($controller->failure[1] ?? '', 'SQLSTATE'), 'Database diagnostic is not displayed publicly');
    $connection->setValue(null, null); session_abort();
    echo json_encode(['checks' => $checks, 'failures' => $failures, 'real_emails_sent' => 0, 'production_database_used' => false], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
    exit($failures ? 1 : 0);
}
