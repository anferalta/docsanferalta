<?php
// Executar: php scripts/validar_perfis.php
// Usa SQLite em memória e sessões de teste; não carrega .env nem altera dados reais.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Core\Auth;
use App\Core\Conexao;
use App\Core\CSRF;
use App\Core\Router;
use App\Core\RouterSingleton;
use App\Services\PerfisService;

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
session_start();
$checks = 0;
$falhas = [];
function verificar(bool $condicao, string $nome): void {
    global $checks, $falhas;
    $checks++;
    if (!$condicao) { $falhas[] = $nome; }
}
function rejeita(callable $acao, string $classe, string $nome): void {
    try { $acao(); verificar(false, $nome); }
    catch (Throwable $e) { verificar($e instanceof $classe, $nome . ' (' . get_class($e) . ')'); }
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA foreign_keys = ON');
$db->exec('CREATE TABLE perfis (id INTEGER PRIMARY KEY AUTOINCREMENT, nome TEXT NOT NULL COLLATE NOCASE UNIQUE, descricao TEXT, ativo INTEGER DEFAULT 1, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE permissoes (id INTEGER PRIMARY KEY, codigo TEXT, descricao TEXT)');
$db->exec('CREATE TABLE perfis_permissoes (perfil_id INTEGER REFERENCES perfis(id) ON DELETE CASCADE, permissao_id INTEGER REFERENCES permissoes(id), PRIMARY KEY(perfil_id, permissao_id))');
$db->exec('CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, nome TEXT, perfil_id INTEGER, ativo INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE auditoria (id INTEGER PRIMARY KEY AUTOINCREMENT, utilizador_id INTEGER, acao TEXT, detalhes TEXT, ip TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO perfis (id,nome,descricao) VALUES (1,'admin','Administrador'),(2,'Leitor','Consulta'),(3,'Gestor','Gestão')");
$db->exec("INSERT INTO utilizadores (id,nome,perfil_id) VALUES (1,'Admin de teste',1),(2,'Leitor de teste',2)");
$insert = $db->prepare('INSERT INTO permissoes VALUES (?, ?, ?)');
foreach (['ver','criar','editar','apagar','permissoes'] as $i => $acao) {
    $insert->execute([$i + 1, 'admin.perfis.' . $acao, ucfirst($acao) . ' perfis']);
}
$db->exec('INSERT INTO perfis_permissoes VALUES (2,1),(3,1),(3,2)');
$instancia = new ReflectionProperty(Conexao::class, 'instancia');
$anterior = $instancia->getValue();
$instancia->setValue(null, $db);
$_SESSION['user_id'] = 1;
$service = new PerfisService();
try {
    foreach ([null, [], true, 1.5, 0, -1, '0', '1 OR 1=1', '01', '99999999999999999999999'] as $id) {
        rejeita(fn() => $service->obter($id), InvalidArgumentException::class, 'Rejeitar ID inválido: ' . json_encode($id));
    }
    rejeita(fn() => $service->obter(999), DomainException::class, 'Perfil inexistente');
    foreach ([[], ['nome' => []], ['nome' => ' '], ['nome' => str_repeat('á', 51)], ['nome' => 'Nome', 'descricao' => str_repeat('ç', 256)], ['nome' => "N\0ome"]] as $dados) {
        rejeita(fn() => $service->criar($dados, 1), InvalidArgumentException::class, 'Rejeitar dados inválidos');
    }
    $id = $service->criar(['nome' => '  Operação  ', 'descricao' => ' Descrição livre '], 1);
    verificar($service->obter($id)['nome'] === 'Operação' && $service->obter($id)['descricao'] === 'Descrição livre', 'Criar com normalização');
    verificar($service->permissoesAtuais($id) === [], 'Criar não atribui permissões implicitamente');
    rejeita(fn() => $service->criar(['nome' => 'Gestor'], 1), InvalidArgumentException::class, 'Nome duplicado na criação');
    rejeita(fn() => $service->atualizar($id, ['nome' => 'Gestor'], 1), InvalidArgumentException::class, 'Nome duplicado na edição');
    $service->atualizar($id, ['nome' => 'Operador', 'descricao' => 'Revisto'], 1);
    verificar($service->obter($id)['nome'] === 'Operador', 'Editar perfil');
    $limite = $service->criar(['nome' => str_repeat('á', 50), 'descricao' => str_repeat('ç', 255)], 1);
    verificar(mb_strlen($service->obter($limite)['descricao']) === 255, 'Aceitar limites Unicode da base de dados');
    foreach ([fn() => $service->atualizar(1, ['nome' => 'Alterado'], 1), fn() => $service->apagar(1, 1), fn() => $service->guardarPermissoes(1, [1], 1)] as $acao) {
        rejeita($acao, DomainException::class, 'Administrador protegido');
    }
    verificar($service->obter(1)['nome'] === 'admin', 'Administrador mantém os dados originais');
    rejeita(fn() => $service->apagar(2, 1), DomainException::class, 'Não apagar perfil associado a utilizadores');
    verificar($service->permissoesAtuais(2) === [1], 'Perfil em utilização mantém permissões');
    $service->guardarPermissoes($id, ['1', 1, '2'], 1);
    verificar($service->permissoesAtuais($id) === [1, 2], 'Permissões duplicadas são normalizadas');
    foreach (['1', [999], [[1]], ['2abc'], [0], [true]] as $permissoes) {
        rejeita(fn() => $service->guardarPermissoes($id, $permissoes, 1), InvalidArgumentException::class, 'Rejeitar lista inválida de permissões');
        verificar($service->permissoesAtuais($id) === [1, 2], 'Lista inválida não remove permissões anteriores');
    }
    $db->exec("CREATE TRIGGER falhar_permissao BEFORE INSERT ON perfis_permissoes WHEN NEW.permissao_id = 3 BEGIN SELECT RAISE(ABORT, 'Falha simulada'); END");
    $antesAuditoria = (int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
    rejeita(fn() => $service->guardarPermissoes($id, [1,3], 1), PDOException::class, 'Falha a meio da gravação é detetada');
    verificar($service->permissoesAtuais($id) === [1,2] && !$db->inTransaction(), 'Rollback repõe todas as permissões');
    verificar((int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn() === $antesAuditoria, 'Sem auditoria de sucesso quando há falha');
    $db->exec('DROP TRIGGER falhar_permissao');
    $db->exec("CREATE TRIGGER falhar_auditoria BEFORE INSERT ON auditoria BEGIN SELECT RAISE(ABORT, 'Falha de auditoria'); END");
    rejeita(fn() => $service->criar(['nome' => 'Não deve existir'], 1), PDOException::class, 'Falha de auditoria reverte criação');
    verificar((int) $db->query("SELECT COUNT(*) FROM perfis WHERE nome = 'Não deve existir'")->fetchColumn() === 0, 'Criação revertida integralmente');
    rejeita(fn() => $service->atualizar($id, ['nome' => 'Não deve mudar'], 1), PDOException::class, 'Falha de auditoria reverte edição');
    verificar($service->obter($id)['nome'] === 'Operador', 'Edição revertida integralmente');
    rejeita(fn() => $service->apagar($id, 1), PDOException::class, 'Falha de auditoria reverte eliminação');
    verificar($service->obter($id)['nome'] === 'Operador' && $service->permissoesAtuais($id) === [1,2], 'Eliminação revertida com permissões intactas');
    $db->exec('DROP TRIGGER falhar_auditoria');
    $service->guardarPermissoes($id, [], 1);
    verificar($service->permissoesAtuais($id) === [], 'Desmarcar tudo remove todas as permissões');
    $service->apagar($limite, 1);
    verificar((int) $db->query("SELECT COUNT(*) FROM perfis WHERE id = {$limite}")->fetchColumn() === 0, 'Apagar perfil sem utilizadores');
    $lista = array_column($service->listar(), null, 'id');
    verificar((int) $lista[2]['total_utilizadores'] === 1 && (int) $lista[3]['total_permissoes'] === 2, 'Contagens de utilizadores e permissões');

    class PerfisControllerTeste extends \App\Controllers\Admin\PerfisAdminController {
        public array $contexto = [];
        public ?string $destino = null;
        public function __construct() {
            $loader = new \Twig\Loader\FilesystemLoader();
            $loader->addPath(dirname(__DIR__) . '/App/Views/admin', 'admin');
            $this->twig = new \Twig\Environment(new \Twig\Loader\ChainLoader([
                new \Twig\Loader\ArrayLoader(['@admin/layout.twig' => '<html><body>{% block content %}{% endblock %}</body></html>']), $loader,
            ]), ['strict_variables' => true]);
            $this->twig->addFunction(new \Twig\TwigFunction('csrf_field', fn() => CSRF::field(), ['is_safe' => ['html']]));
            $this->twig->addFunction(new \Twig\TwigFunction('isGranted', fn($p) => Auth::user()?->hasPermissao($p) ?? false));
        }
        protected function render(string $template, array $data = []): void { $this->contexto = $data; echo $this->twig->render($template, $data); }
        protected function redirect(string $url): void { $this->destino = $url; }
        protected function acessoNegado(?string $permissao = null) { throw new RuntimeException('Acesso negado'); }
    }
    function executar(PerfisControllerTeste $controller, string $acao, array $args = []): string {
        $controller->destino = null;
        http_response_code(200);
        ob_start();
        try { $controller->$acao(...$args); return (string) ob_get_contents(); }
        finally { ob_end_clean(); }
    }
    $controller = new PerfisControllerTeste();
    foreach (['editar', 'permissoes'] as $acao) {
        executar($controller, $acao, [999]);
        verificar($controller->destino === '/admin/perfis', "{$acao}: perfil inexistente não causa erro fatal");
        executar($controller, $acao, [1]);
        verificar($controller->destino === '/admin/perfis', "{$acao}: Administrador protegido no controlador");
    }
    $_POST = ['nome' => '', 'descricao' => '<script>teste</script>'];
    $html = executar($controller, 'criarSubmit');
    verificar(http_response_code() === 422 && str_contains($html, 'obrigatório'), 'Criação inválida apresenta erro 422');
    verificar(str_contains($html, '&lt;script&gt;teste&lt;/script&gt;') && !str_contains($html, '<script>teste</script>'), 'Formulário preserva e escapa valores recebidos');
    $_POST = ['nome' => 'Perfil do controlador', 'descricao' => 'Descrição própria'];
    executar($controller, 'criarSubmit');
    verificar($controller->destino === '/admin/perfis', 'Controlador cria perfil e redireciona');
    $novoId = (int) $db->query("SELECT id FROM perfis WHERE nome = 'Perfil do controlador'")->fetchColumn();
    $_POST = ['nome' => 'Perfil alterado', 'descricao' => 'Revisão'];
    executar($controller, 'editarSubmit', [$novoId]);
    verificar($service->obter($novoId)['nome'] === 'Perfil alterado', 'Controlador atualiza o perfil');
    $_POST = ['permissoes' => ['1','2']];
    executar($controller, 'permissoesSubmit', [$novoId]);
    verificar($service->permissoesAtuais($novoId) === [1,2], 'Controlador grava permissões');
    $html = executar($controller, 'permissoes', [$novoId]);
    verificar(substr_count($html, ' checked') === 2 && str_contains($html, 'name="_csrf"'), 'Formulário marca permissões atuais e contém CSRF');
    $_POST = ['permissoes' => [999]];
    $html = executar($controller, 'permissoesSubmit', [$novoId]);
    verificar(http_response_code() === 422 && $service->permissoesAtuais($novoId) === [1,2], 'Permissão inexistente mantém seleção gravada');
    $html = executar($controller, 'index');
    verificar(str_contains($html, 'Administrador protegido') && !str_contains($html, '/perfis/apagar/1"'), 'Listagem não oferece ações sobre Administrador');
    verificar(!str_contains($html, '/perfis/apagar/2"') && str_contains($html, 'Em utilização'), 'Listagem explica perfil em utilização');
    executar($controller, 'apagar', [$novoId]);
    verificar((int) $db->query("SELECT COUNT(*) FROM perfis_permissoes WHERE perfil_id = {$novoId}")->fetchColumn() === 0, 'Controlador elimina perfil e associações');

    $_SESSION['user_id'] = 2;
    $html = executar($controller, 'index');
    verificar(!str_contains($html, 'Novo perfil') && !str_contains($html, 'Gerir permissões'), 'Utilizador de consulta não recebe botões de escrita');
    $acoes = ['criar' => [], 'criarSubmit' => [], 'editar' => [3], 'editarSubmit' => [3], 'permissoes' => [3], 'permissoesSubmit' => [3], 'apagar' => [3]];
    $antes = (int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn();
    foreach ($acoes as $acao => $args) {
        rejeita(fn() => executar($controller, $acao, $args), RuntimeException::class, "{$acao}: autorização real recusa utilizador de consulta");
    }
    verificar((int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn() === $antes, 'Sem alterações em pedidos não autorizados');
    unset($_SESSION['user_id']);
    rejeita(fn() => executar($controller, 'index'), RuntimeException::class, 'Utilizador anónimo não consulta perfis');

    RouterSingleton::set(new Router());
    ob_start(); require dirname(__DIR__) . '/routes/admin.php'; ob_end_clean();
    $rotas = (new ReflectionProperty(Router::class, 'routes'))->getValue();
    $rotas = array_values(array_filter($rotas, fn($r) => str_starts_with($r['uri'], '/admin/perfis')));
    verificar(count($rotas) === 8, 'Oito rotas do módulo registadas');
    foreach ($rotas as $rota) {
        [$classe, $metodo] = explode('@', $rota['action']);
        verificar(method_exists('App\\Controllers\\' . $classe, $metodo), 'Rota aponta para método existente: ' . $rota['uri']);
        verificar(in_array('auth', $rota['middleware'], true) && ($rota['method'] !== 'POST' || in_array('csrf', $rota['middleware'], true)), 'Autenticação e CSRF preservados: ' . $rota['uri']);
    }
    verificar(Router::route('admin.roles.edit', ['id' => 3]) === '/admin/perfis/editar/3', 'URL de edição gerada sem placeholder por resolver');
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = ['_csrf' => 'invalido'];
    verificar(!CSRF::validateFromRequest(), 'CSRF real recusa token inválido');
    $_POST = ['_csrf' => CSRF::token()];
    verificar(CSRF::validateFromRequest(), 'CSRF real aceita token da sessão');
} finally {
    $instancia->setValue(null, $anterior);
    $_SESSION = [];
    session_destroy();
}
echo "Verificações: {$checks}; falhas: " . count($falhas) . PHP_EOL;
foreach ($falhas as $falha) { echo "FALHA: {$falha}" . PHP_EOL; }
exit($falhas === [] ? 0 : 1);
