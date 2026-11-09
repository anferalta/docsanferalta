<?php
// php scripts/validar_auditoria.php
// Usa apenas SQLite em memória; não carrega .env nem altera registos reais.
require dirname(__DIR__) . '/vendor/autoload.php';
use App\Core\Auth;
use App\Core\Conexao;
use App\Core\Router;
use App\Core\RouterSingleton;
use App\Services\AuditoriaConsultaService;

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
session_start();
$checks = 0; $falhas = [];
function verificar(bool $condicao, string $nome): void { global $checks, $falhas; $checks++; if (!$condicao) { $falhas[] = $nome; } }
function rejeita(callable $acao, string $classe, string $nome): void {
    try { $acao(); verificar(false, $nome); } catch (Throwable $e) { verificar($e instanceof $classe, $nome . ': ' . get_class($e)); }
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->sqliteCreateFunction('CONCAT', static fn(...$valores) => implode('', $valores));
$db->exec('CREATE TABLE auditoria (id INTEGER PRIMARY KEY AUTOINCREMENT, utilizador_id INTEGER, acao TEXT, ip TEXT, detalhes TEXT, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE auditoria_eliminacoes (id INTEGER PRIMARY KEY, apagado_por INTEGER, tabela TEXT, dados TEXT, ip TEXT, criado_em TEXT)');
$db->exec('CREATE TABLE perfis (id INTEGER PRIMARY KEY, nome TEXT, descricao TEXT)');
$db->exec('CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, nome TEXT, perfil_id INTEGER, ativo INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE permissoes (id INTEGER PRIMARY KEY, codigo TEXT, descricao TEXT)');
$db->exec('CREATE TABLE perfis_permissoes (perfil_id INTEGER, permissao_id INTEGER)');
$db->exec("INSERT INTO perfis VALUES (1,'Admin','Admin'), (2,'Auditor','Auditor'), (3,'Sem acesso','Sem acesso')");
$db->exec("INSERT INTO utilizadores (id,nome,perfil_id) VALUES (1,'Administrador',1), (2,'Auditor',2), (3,'Sem acesso',3), (4,'Ana <script>alert(1)</script>',3)");
$db->exec("INSERT INTO permissoes VALUES (1,'admin.auditoria.ver','Ver auditoria')");
$db->exec('INSERT INTO perfis_permissoes VALUES (2,1)');
$stmt = $db->prepare('INSERT INTO auditoria (id, utilizador_id, acao, ip, detalhes, criado_em) VALUES (?, ?, ?, ?, ?, ?)');
for ($id = 1; $id <= 45; $id++) {
    $data = match ($id) { 43 => '2026-11-06 23:59:59', 44 => '2026-11-08 00:00:00', 45 => '2026-11-07 23:59:59.999999', default => '2026-11-07 12:00:00' };
    $acao = match ($id) { 1 => 'Criou documento', 2 => '=1+1', 3 => '  @SUM(1;2)', default => 'Editou documento' };
    $stmt->execute([$id, $id === 5 ? 999 : 4, $acao, '127.0.0.1', "<script>detalhes</script>\nSegunda linha; com separador", $data]);
}
$stmt = $db->prepare('INSERT INTO auditoria_eliminacoes VALUES (?, ?, ?, ?, ?, ?)');
$stmt->execute([1,4,'utilizadores','{"id":1,"nome":"Registo eliminado"}','127.0.0.1','2026-11-07 12:00:00']);
$stmt->execute([2,999,'documentos','{"id":2}','127.0.0.1','2026-11-07 23:59:59.999999']);
$stmt->execute([3,4,'perfis','{"id":3}','127.0.0.1','2026-11-06 12:00:00']);
$instancia = new ReflectionProperty(Conexao::class, 'instancia');
$anterior = $instancia->getValue(); $instancia->setValue(null, $db);
$service = new AuditoriaConsultaService($db);
try {
    $lista = $service->listar();
    verificar($lista['total'] === 48 && $lista['paginas'] === 3 && count($lista['logs']) === 20, 'Total calculado antes da paginação');
    $segunda = $service->listar(['p' => 2]);
    $terceira = $service->listar(['p' => 3]);
    verificar(count($segunda['logs']) === 20 && count($terceira['logs']) === 8, 'Três páginas com 20, 20 e 8 registos');
    $todos = [...$lista['logs'], ...$segunda['logs'], ...$terceira['logs']];
    $chaves = array_map(fn($log) => $log['origem'] . ':' . $log['id'], $todos);
    verificar(count(array_unique($chaves)) === 48, 'Paginação não repete nem omite registos');
    verificar($lista['logs'] === $service->listar()['logs'], 'Ordenação determinística em datas iguais');
    verificar($service->listar(['p' => (string) PHP_INT_MAX])['pagina'] === 3, 'Página excessiva é limitada ao total sem overflow');
    verificar($service->listar(['p' => '-1'])['pagina'] === 1, 'Página negativa regressa à primeira');
    $vazio = $service->listar(['acao' => 'Não existe']);
    verificar($vazio['total'] === 0 && $vazio['paginas'] === 1 && $vazio['logs'] === [], 'Resultado vazio tem paginação válida');
    verificar($service->normalizar(['data_inicio' => ' 2026-11-07 '])['filtros']['data_inicio'] === '2026-11-07', 'Normalizar espaços antes de validar datas');
    $dia = ['data_inicio' => '2026-11-07', 'data_fim' => '2026-11-07'];
    verificar($service->listar($dia)['total'] === 45, 'Intervalo inclui o último dia e microssegundos, em ambas as origens');
    verificar($service->listar(['data_fim' => '9999-12-31'])['total'] === 48, 'Data máxima do MySQL não gera ano 10000');
    verificar($service->listar(['origem' => 'auditoria'])['total'] === 45, 'Filtro de origem geral');
    verificar($service->listar(['origem' => 'eliminacao'])['total'] === 3, 'Filtro de origem eliminação');
    verificar($service->listar(['acao' => 'Eliminou'])['total'] === 3, 'Pesquisa nas ações de eliminação');
    verificar($service->listar(['ip' => '127.0'])['total'] === 48, 'Pesquisa parcial por IP');
    verificar($service->listar(['utilizador' => 'Ana'])['total'] === 46, 'Pesquisa por utilizador exclui autores inexistentes');
    verificar($service->listar(['acao' => "' OR 1=1 --"])['total'] === 0, 'Filtro SQL permanece um parâmetro');
    $injetado = $service->listar(['ordem' => 'id; DROP TABLE auditoria', 'dir' => 'DESC; DROP TABLE utilizadores']);
    verificar($injetado['ordem'] === 'criado_em' && $injetado['dir'] === 'DESC' && $injetado['total'] === 48, 'Ordenação SQL rejeita valores fora da lista permitida');
    foreach (AuditoriaConsultaService::ORDEM as $ordem) {
        verificar(count($service->listar(['ordem' => $ordem, 'dir' => 'ASC'])['logs']) === 20, 'Ordenação permitida: ' . $ordem);
    }
    foreach ([['utilizador'=>[]], ['ip'=>[]], ['acao'=>[]], ['data_inicio'=>'2026-02-29'], ['data_inicio'=>'2026-11-08','data_fim'=>'2026-11-07'], ['origem'=>'outra'], ['origem'=>[]], ['ordem'=>[]], ['dir'=>[]], ['p'=>[]], ['acao'=>"a\0b"], ['acao'=>str_repeat('a',256)]] as $filtro) {
        rejeita(fn() => $service->listar($filtro), InvalidArgumentException::class, 'Rejeitar filtro inválido: ' . json_encode($filtro));
    }
    verificar($service->obter(1, 'auditoria')['acao'] === 'Criou documento', 'Detalhe geral respeita origem');
    verificar($service->obter(1, 'eliminacao')['acao'] === 'Eliminou utilizadores', 'Mesmo ID na eliminação não mostra registo geral');
    verificar($service->obter(1)['origem'] === 'auditoria', 'Link antigo sem origem mantém precedência geral');
    verificar($service->obter(45, 'eliminacao') === null, 'Origem explícita não pesquisa outra tabela');
    verificar($service->obter(999) === null, 'ID inexistente não devolve registo');
    foreach (['0', '-1', '1 OR 1=1', [], '99999999999999999999999'] as $id) {
        rejeita(fn() => $service->obter($id), InvalidArgumentException::class, 'Identificador inválido');
    }
    $dashboard = $service->dashboard();
    verificar((int) $dashboard['resumo']['total'] === 48 && (int) $dashboard['resumo']['gerais'] === 45 && (int) $dashboard['resumo']['eliminacoes'] === 3, 'Dashboard agrega as duas origens');
    verificar(array_sum(array_column($dashboard['porDia'], 'total')) === 48, 'Totais por dia são consistentes');
    verificar((int) $service->dashboard($dia)['resumo']['total'] === 45, 'Dashboard aplica os mesmos filtros');

    class AuditoriaControllerTeste extends \App\Controllers\Admin\AuditoriaAdminController {
        public array $contexto = [];
        public function __construct() {
            $loader = new \Twig\Loader\FilesystemLoader(); $loader->addPath(dirname(__DIR__) . '/App/Views/admin', 'admin');
            $this->twig = new \Twig\Environment(new \Twig\Loader\ChainLoader([
                new \Twig\Loader\ArrayLoader(['@admin/layout.twig' => '<html><body>{% block content %}{% endblock %}{% block scripts %}{% endblock %}</body></html>']), $loader,
            ]), ['strict_variables' => true]);
        }
        protected function render(string $template, array $data = []): void { $this->contexto = $data; echo $this->twig->render($template, $data); }
        protected function acessoNegado(?string $permissao = null) { throw new RuntimeException('Acesso negado'); }
    }
    function executar(AuditoriaControllerTeste $controller, string $acao, array $args = []): string {
        http_response_code(200); ob_start();
        try { $controller->$acao(...$args); return (string) ob_get_contents(); } finally { ob_end_clean(); }
    }
    $_SESSION['user_id'] = 2; // Permissão real admin.auditoria.ver.
    $controller = new AuditoriaControllerTeste();
    $_GET = ['utilizador'=>'Ana','data_inicio'=>'2026-11-07','p'=>'2'];
    $html = executar($controller, 'index');
    verificar(str_contains($html, 'utilizador=Ana') && str_contains($html, 'data_inicio=2026-11-07') && str_contains($html, 'p=1'), 'Ordenação e paginação preservam filtros');
    verificar(str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;') && !str_contains($html, '<script>alert(1)</script>'), 'Listagem escapa nomes do utilizador');
    $_GET = ['origem'=>'eliminacao'];
    $html = executar($controller, 'detalhes', [1]);
    verificar(str_contains($html, 'Registo eliminado') && str_contains($html, 'Eliminou utilizadores'), 'Controlador mostra detalhe da origem selecionada');
    $_GET = ['origem'=>'auditoria'];
    $html = executar($controller, 'detalhes', [1]);
    verificar(str_contains($html, '&lt;script&gt;detalhes&lt;/script&gt;') && !str_contains($html, '<script>detalhes</script>'), 'Detalhes não executam HTML guardado');
    verificar(str_contains($html, 'Ana &lt;script&gt;'), 'Detalhes mostram o autor do registo, não o utilizador autenticado');
    executar($controller, 'detalhes', [999]);
    verificar(http_response_code() === 404, 'Detalhe inexistente devolve 404');
    $_GET = ['acao'=>'Não existe'];
    $html = executar($controller, 'index');
    verificar(str_contains($html, 'Nenhum registo encontrado') && !str_contains($html, 'p=0'), 'Página vazia não gera navegação inválida');
    $html = executar($controller, 'dashboardAuditoria');
    verificar(str_contains($html, 'Nenhum registo encontrado') && !str_contains($html, 'new Chart'), 'Dashboard vazio funciona sem biblioteca externa');
    $_GET = [];
    $html = executar($controller, 'dashboardAuditoria');
    verificar(str_contains($html, '20 ações mais frequentes') && str_contains($html, '<meter'), 'Dashboard renderiza indicadores acessíveis');
    $_GET = ['p'=>'3'];
    $csv = executar($controller, 'exportar');
    verificar(str_starts_with($csv, "\xEF\xBB\xBF"), 'CSV inclui BOM UTF-8 para Excel');
    $stream = fopen('php://memory', 'w+'); fwrite($stream, substr($csv,3)); rewind($stream);
    $linhas = []; while (($linha = fgetcsv($stream, null, ';', '"', '')) !== false) { $linhas[] = $linha; } fclose($stream);
    verificar(count($linhas) === 49, 'CSV exporta todos os resultados, não apenas a página atual');
    $formula = array_values(array_filter(array_slice($linhas,1), fn($r) => $r[0] === '2' && $r[6] === 'auditoria'))[0];
    verificar($formula[3] === "'=1+1", 'CSV neutraliza fórmulas');
    $formulaEspacos = array_values(array_filter(array_slice($linhas,1), fn($r) => $r[0] === '3' && $r[6] === 'auditoria'))[0];
    verificar(str_starts_with($formulaEspacos[3], "'"), 'CSV neutraliza fórmulas precedidas de espaços');
    verificar(str_contains($formula[7], "\nSegunda linha;"), 'CSV preserva detalhes multilinha e separadores');
    $_GET = ['origem'=>'eliminacao'];
    $csvFiltrado = executar($controller, 'exportar');
    verificar(!str_contains($csvFiltrado, 'Criou documento') && str_contains($csvFiltrado, 'Eliminou utilizadores'), 'CSV aplica filtros de origem');
    foreach (['index'=>[], 'exportar'=>[], 'dashboardAuditoria'=>[], 'detalhes'=>[1]] as $acao=>$args) {
        $_GET = $acao === 'detalhes' ? ['origem'=>[]] : ['acao'=>[]];
        $erro = executar($controller, $acao, $args);
        verificar(http_response_code() === 422 && str_contains($erro, 'Não foi possível'), "{$acao}: entrada inválida devolve 422");
        $_SESSION['user_id'] = 3;
        rejeita(fn() => executar($controller,$acao,$args), RuntimeException::class, "{$acao}: autorização real recusa utilizador sem permissão");
        unset($_SESSION['user_id']);
        rejeita(fn() => executar($controller,$acao,$args), RuntimeException::class, "{$acao}: autorização real recusa anónimo");
        $_SESSION['user_id'] = 2;
    }
    verificar((int) $db->query('SELECT COUNT(*) FROM auditoria')->fetchColumn() === 45 && (int) $db->query('SELECT COUNT(*) FROM auditoria_eliminacoes')->fetchColumn() === 3, 'Consulta, dashboard e exportação não alteram registos');
    RouterSingleton::set(new Router()); ob_start(); require dirname(__DIR__).'/routes/admin.php'; ob_end_clean();
    $rotas = (new ReflectionProperty(Router::class,'routes'))->getValue();
    $rotas = array_values(array_filter($rotas,fn($r)=>str_starts_with($r['uri'],'/admin/auditoria')));
    verificar(count($rotas) === 4, 'Quatro rotas de auditoria registadas');
    foreach ($rotas as $rota) {
        [$classe,$metodo] = explode('@',$rota['action']);
        verificar(method_exists('App\\Controllers\\'.$classe,$metodo) && in_array('auth',$rota['middleware'],true), 'Rota protegida aponta para método existente: '.$rota['uri']);
    }
    verificar(Router::route('admin.auditoria.details',['id'=>1]) === '/admin/auditoria/1', 'URL de detalhe resolve o ID');
    \App\Models\Auditoria::registar('Teste UTF-8',1,str_repeat('á',999).'€'.'final');
    $detalhes = $db->query('SELECT detalhes FROM auditoria ORDER BY id DESC LIMIT 1')->fetchColumn();
    verificar(mb_check_encoding($detalhes,'UTF-8') && str_ends_with($detalhes,'[TRUNCADO]'), 'Truncar detalhes preserva UTF-8');
    verificar(count(\App\Models\Auditoria::graficoPorAcao()) > 0 && count(\App\Models\Auditoria::graficoPorDia()) > 0, 'Métodos de agregação do modelo usam ligação existente');
} finally {
    $instancia->setValue(null,$anterior); $_SESSION=[]; session_destroy();
}
echo "Verificações: {$checks}; falhas: ".count($falhas).PHP_EOL;
foreach($falhas as $falha){echo 'FALHA: '.$falha.PHP_EOL;}
exit($falhas === [] ? 0 : 1);
