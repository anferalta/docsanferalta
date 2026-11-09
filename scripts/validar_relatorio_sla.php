<?php
// Executar: php scripts/validar_relatorio_sla.php
// Dados apenas em SQLite em memória; não liga ao MySQL nem envia emails.
require dirname(__DIR__) . '/vendor/autoload.php';

use App\Services\RelatorioSLAService;

error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});
date_default_timezone_set('Europe/Lisbon');
$checks = 0;
$falhas = [];
function verificar(bool $condicao, string $nome): void
{
    global $checks, $falhas;
    $checks++;
    if (!$condicao) { $falhas[] = $nome; }
}
function rejeita(callable $acao, string $nome): void
{
    try { $acao(); verificar(false, $nome); }
    catch (InvalidArgumentException $e) { verificar(true, $nome); }
    catch (Throwable $e) { verificar(false, $nome . ': ' . get_class($e)); }
}
$service = new RelatorioSLAService();
$agora = new DateTimeImmutable('2026-11-05 12:00:00');
foreach ([0 => 'ok', 4 => 'ok', 5 => 'ok', 6 => 'alerta', 7 => 'alerta', 8 => 'atrasado'] as $dias => $sla) {
    $doc = $service->calcularDocumento(['prazo_resposta' => 5, 'area_atual_desde' => $agora->modify("-{$dias} days")->format('Y-m-d H:i:s')], $agora);
    verificar($doc['sla'] === $sla && $doc['dias_parado'] === $dias, "Limite SLA: {$dias} dias");
}
foreach ([null, '', 'inválida', '2026-02-30 12:00:00', '2026-11-06 12:00:00', "2026-11-01\0 12:00:00"] as $data) {
    try {
        $doc = $service->calcularDocumento(['prazo_resposta' => 5, 'area_atual_desde' => $data], $agora);
        verificar($doc['sla'] === 'indefinido' && $doc['dias_parado'] === null, 'Data inconsistente fica indefinida: ' . json_encode($data));
    } catch (Throwable $e) { verificar(false, 'Data inconsistente lança ' . get_class($e)); }
}
foreach ([null, '', 0, -1, 'abc', '2.5'] as $prazo) {
    $doc = $service->calcularDocumento(['prazo_resposta' => $prazo, 'area_atual_desde' => '2026-11-01 12:00:00'], $agora);
    verificar($doc['sla'] === 'indefinido', 'Prazo inválido: ' . json_encode($prazo));
}
$filtros = $service->normalizarFiltros(['area' => ' Financeiro ', 'estado' => ' pendente ', 'data_inicio' => '2024-02-29']);
verificar($filtros['area'] === 'Financeiro' && $filtros['estado'] === 'pendente', 'Trim dos filtros');
foreach ([['area' => []], ['estado' => 'arquivado'], ['data_inicio' => '2026-02-29'], ['data_fim' => '05/11/2026'], ['data_inicio' => '2026-11-06', 'data_fim' => '2026-11-05'], ['data_inicio' => "2026-11-01\0"]] as $filtro) {
    rejeita(fn() => $service->normalizarFiltros($filtro), 'Rejeitar filtro: ' . json_encode($filtro));
}
$db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE documento_areas (id INTEGER PRIMARY KEY, nome TEXT, prazo_resposta INTEGER)');
$db->exec('CREATE TABLE documentos (id INTEGER PRIMARY KEY, titulo TEXT, estado_atual TEXT, area_atual_desde TEXT, criado_em TEXT, area_atual_id INTEGER)');
$db->exec("INSERT INTO documento_areas VALUES (1, 'Financeiro', 5), (2, 'Jurídico', 5)");
$insert = $db->prepare('INSERT INTO documentos VALUES (?, ?, ?, ?, ?, ?)');
foreach ([
    [1, '=1+1', 'pendente', '2026-10-31 12:00:00', '2026-11-05 00:00:00', 1],
    [2, '<script>teste</script>', 'analise', '2026-10-30 12:00:00', '2026-11-05 23:59:59.999999', 1],
    [3, 'Posterior', 'pendente', '2026-10-28 12:00:00', '2026-11-06 00:00:00', 1],
    [4, 'Anterior', 'pendente', '2026-10-28 12:00:00', '2026-11-04 23:59:59', 1],
    [5, 'Arquivado', 'arquivado', '2026-10-28 12:00:00', '2026-11-05 12:00:00', 1],
    [6, 'Sem área', 'concluido', null, '2026-11-05 12:00:00', null],
    [7, 'Outra área', 'pendente', '2026-10-28 12:00:00', '2026-11-05 00:00:00', 2],
] as $doc) { $insert->execute($doc); }
$service = new RelatorioSLAService($db);
$relatorio = $service->gerar(['area' => 'Financeiro', 'data_inicio' => '2026-11-05', 'data_fim' => '2026-11-05'], $agora);
verificar(array_column($relatorio['documentos'], 'id') === [2, 1], 'Intervalo inclusivo, microssegundos e ordenação');
verificar($relatorio['totais'] === ['ok' => 1, 'alerta' => 1, 'atrasado' => 0, 'indefinido' => 0], 'Totais dos resultados filtrados');
$todos = $service->gerar([], $agora);
verificar(count($todos['documentos']) === 6, 'Excluir apenas arquivados');
verificar($todos['totais']['indefinido'] === 1 && array_sum($todos['totais']) === 6, 'Contabilizar SLA indefinido');
verificar(count($service->gerar(['estado' => 'concluido'], $agora)['documentos']) === 1, 'Preservar documentos concluídos');
verificar($service->gerar(['area' => "' OR 1=1 --"], $agora)['documentos'] === [], 'Parâmetros SQL não executam conteúdo do filtro');
$vazio = $service->gerar(['area' => 'Inexistente'], $agora);
verificar($vazio['documentos'] === [] && array_sum($vazio['totais']) === 0, 'Resultados vazios');
ob_start();
echo 'conteudo anterior';
$nivel = ob_get_level();
$html = $service->gerarHTML($relatorio);
verificar(ob_get_level() === $nivel && ob_get_contents() === 'conteudo anterior', 'Preservar buffer do chamador');
ob_end_clean();
verificar(!str_contains($html, '<script>teste</script>') && str_contains($html, '&lt;script&gt;teste&lt;/script&gt;'), 'Escapar HTML dos documentos');
verificar(str_contains($html, '05/11/2026 12:00'), 'Usar o instante fixo do relatório no HTML');

// Integração: utiliza os controladores reais com ligação PDO em memória.
class RelatoriosSLAControllerTeste extends \App\Controllers\Admin\RelatoriosAdminController
{
    public array $contexto = [];
    public array $permissoes = [];
    public bool $permitido = true;
    public function __construct()
    {
        $loader = new \Twig\Loader\FilesystemLoader();
        $loader->addPath(dirname(__DIR__) . '/App/Views/admin', 'admin');
        $this->twig = new \Twig\Environment(new \Twig\Loader\ChainLoader([
            new \Twig\Loader\ArrayLoader(['@admin/layout.twig' => '<!DOCTYPE html><html lang="pt"><body>{% block content %}{% endblock %}</body></html>']),
            $loader,
        ]), ['strict_variables' => true]);
    }
    protected function authorize(string $permission)
    {
        $this->permissoes[] = $permission;
        if (!$this->permitido) { throw new RuntimeException('Sem permissão'); }
    }
    protected function render(string $template, array $data = []): void
    {
        $this->contexto = $data;
        echo $this->twig->render($template, $data);
    }
}
$instancia = new ReflectionProperty(\App\Core\Conexao::class, 'instancia');
$anterior = $instancia->getValue();
$getAnterior = $_GET;
$instancia->setValue(null, $db);
try {
    $controller = new RelatoriosSLAControllerTeste();
    $_GET = ['area' => 'Financeiro', 'data_inicio' => '2026-11-05', 'data_fim' => '2026-11-05'];
    ob_start(); $controller->index(); $pagina = ob_get_clean();
    verificar(count($controller->contexto['documentos']) === 2, 'Página aplica os filtros do serviço');
    verificar(str_contains($pagina, '&lt;script&gt;teste&lt;/script&gt;') && !str_contains($pagina, '<script>teste</script>'), 'Página escapa os títulos');
    verificar(str_contains($pagina, 'Indefinido: 0'), 'Resumo apresenta a categoria indefinido');
    verificar(str_contains($pagina, '/exportar/pdf?area=Financeiro') && str_contains($pagina, 'data_fim=2026-11-05'), 'Links de exportação preservam filtros');
    ob_start(); $controller->exportarPDF(); $pdf = ob_get_clean();
    verificar(str_starts_with($pdf, '%PDF-'), 'Controlador exporta um PDF válido');
    ob_start(); $controller->exportarExcel(); $xlsx = ob_get_clean();
    verificar(str_starts_with($xlsx, 'PK'), 'Controlador exporta um ficheiro XLSX');
    $tmpXlsx = tempnam(sys_get_temp_dir(), 'sla_xlsx_');
    try {
        file_put_contents($tmpXlsx, $xlsx);
        $livro = \PhpOffice\PhpSpreadsheet\IOFactory::load($tmpXlsx);
        $sheet = $livro->getActiveSheet();
        verificar($sheet->getHighestRow() === 3, 'Excel inclui apenas os documentos filtrados');
        verificar($sheet->getCell('B3')->getValue() === '=1+1' && $sheet->getCell('B3')->getDataType() === 's', 'Excel preserva títulos como texto sem executar fórmulas');
        verificar($sheet->getCell('B2')->getValue() === '<script>teste</script>', 'Excel preserva o texto original');
        verificar($sheet->getCell('F3')->getDataType() === 'n', 'Excel mantém dias como números');
        $livro->disconnectWorksheets();
    } finally { unlink($tmpXlsx); }
    $_GET = ['area' => 'Inexistente'];
    ob_start(); $controller->index(); $paginaVazia = ob_get_clean();
    verificar(str_contains($paginaVazia, 'Nenhum documento encontrado'), 'Página comunica resultados vazios');
    foreach (['index', 'exportarPDF', 'exportarExcel'] as $acao) {
        $_GET = ['area' => []];
        http_response_code(200);
        ob_start(); $controller->$acao(); $erro = ob_get_clean();
        verificar(http_response_code() === 422 && str_contains($erro, 'Filtros inválidos'), "{$acao}: filtros inválidos devolvem 422 sem ficheiro");
        $controller->permitido = false;
        $negado = false;
        try { $controller->$acao(); } catch (RuntimeException $e) { $negado = $e->getMessage() === 'Sem permissão'; }
        verificar($negado, "{$acao}: verifica autorização antes de processar");
        $controller->permitido = true;
    }
    verificar(array_unique($controller->permissoes) === ['admin.relatorios.ver'], 'Preservar a permissão original do módulo');
} finally {
    $instancia->setValue(null, $anterior);
    $_GET = $getAnterior;
    http_response_code(200);
}

$anexo = null;
$envios = 0;
$emailService = new \App\Services\RelatorioSLAEmailService($service, function ($para, $assunto, $template, $dados, $anexos) use (&$anexo, &$envios) {
    $envios++;
    $anexo = $anexos[0];
    verificar($para === 'geral@anferalta.com' && str_contains($assunto, 'semanal'), 'Envio preserva destinatário e período');
    verificar(is_file($anexo) && str_starts_with(file_get_contents($anexo), '%PDF-'), 'Envio recebe anexo PDF válido');
    $mensagem = \App\Core\EmailTemplate::render($template, $dados);
    verificar(str_contains($mensagem, '(semanal)'), 'Template de email existe e renderiza o período');
    return true;
});
$emailService->enviarEmail('semanal');
verificar($envios === 1 && !is_file($anexo), 'Sucesso: envio simulado e anexo removido');
foreach ([false, 'excecao'] as $falhaEnvio) {
    $anexoFalha = null;
    $emailService = new \App\Services\RelatorioSLAEmailService($service, function ($para, $assunto, $template, $dados, $anexos) use (&$anexoFalha, $falhaEnvio) {
        $anexoFalha = $anexos[0];
        if ($falhaEnvio === 'excecao') { throw new RuntimeException('SMTP simulado indisponível'); }
        return false;
    });
    $erroEnvio = false;
    try { $emailService->enviarEmail(); } catch (RuntimeException $e) { $erroEnvio = true; }
    verificar($erroEnvio && !is_file($anexoFalha), 'Falha de envio é reportada e limpa o anexo: ' . json_encode($falhaEnvio));
}

$router = new \App\Core\Router();
\App\Core\RouterSingleton::set($router);
ob_start(); require dirname(__DIR__) . '/routes/admin.php'; ob_end_clean();
$rotas = (new ReflectionProperty(\App\Core\Router::class, 'routes'))->getValue();
foreach (['/admin/relatorios', '/admin/relatorios/exportar/pdf', '/admin/relatorios/exportar/excel'] as $uri) {
    $rotasSla = array_values(array_filter($rotas, fn($rota) => $rota['uri'] === $uri && $rota['method'] === 'GET'));
    verificar(count($rotasSla) === 1 && in_array('auth', $rotasSla[0]['middleware'], true), 'Rota protegida por autenticação: ' . $uri);
}

// Casos de fronteira e exportações sem resultados ou sem área.
verificar(count($service->gerar(['data_fim' => '9999-12-31'], $agora)['documentos']) === 6, 'Data máxima não exclui documentos válidos');
$parcial = $service->calcularDocumento(['prazo_resposta' => 5, 'area_atual_desde' => '2026-10-30 12:00:01'], $agora);
verificar($parcial['sla'] === 'ok' && $parcial['dias_parado'] === 5, 'SLA conta dias completos antes da transição');
$exportador = new \App\Services\RelatorioSLAExportService();
verificar(str_starts_with($exportador->pdf($vazio), '%PDF-'), 'PDF vazio gera relatório válido');
$excelVazio = $exportador->excel($vazio);
verificar($excelVazio->getActiveSheet()->getHighestRow() === 1, 'Excel vazio mantém os cabeçalhos');
$excelVazio->disconnectWorksheets();
$excelTodos = $exportador->excel($todos);
verificar($excelTodos->getActiveSheet()->getHighestRow() === 7, 'Excel inclui documentos com SLA indefinido');
$excelTodos->disconnectWorksheets();
if (isset($argv[1])) {
    $destino = $argv[1];
    if (!is_dir($destino)) { mkdir($destino, 0770, true); }
    file_put_contents($destino . '/sla-amostra.pdf', $pdf);
    file_put_contents($destino . '/sla-amostra.xlsx', $xlsx);
    file_put_contents($destino . '/sla-amostra.html', $pagina);
    $multipagina = $relatorio;
    $multipagina['documentos'] = [];
    for ($i = 1; $i <= 65; $i++) {
        $doc = $relatorio['documentos'][$i % 2];
        $doc['id'] = $i;
        $doc['titulo'] = "Documento {$i}: revisão de tramitação e condições de resposta do relatório SLA com título longo";
        $multipagina['documentos'][] = $doc;
    }
    $multipagina['totais'] = array_replace(['ok' => 0, 'alerta' => 0, 'atrasado' => 0, 'indefinido' => 0], array_count_values(array_column($multipagina['documentos'], 'sla')));
    file_put_contents($destino . '/sla-multipagina.pdf', (new \App\Services\RelatorioSLAExportService())->pdf($multipagina));
}
echo "Verificações: {$checks}; falhas: " . count($falhas) . PHP_EOL;
foreach ($falhas as $falha) { echo "FALHA: {$falha}" . PHP_EOL; }
exit($falhas === [] ? 0 : 1);
