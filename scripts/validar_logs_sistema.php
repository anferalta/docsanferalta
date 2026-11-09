<?php
// php scripts/validar_logs_sistema.php
// Só SQLite em memória; não carrega .env, não envia emails e não limpa logs reais.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Auth;
use App\Core\Conexao;
use App\Core\Router;
use App\Core\RouterSingleton;
use App\Models\LogSistema;
use App\Services\LogsSistemaConsultaService;
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
session_start();
$checks=0;$falhas=[];
function verificar(bool $condicao,string $nome):void{global $checks,$falhas;$checks++;if(!$condicao){$falhas[]=$nome;}}
function rejeita(callable $acao,string $classe,string $nome):void{try{$acao();verificar(false,$nome);}catch(Throwable $e){verificar($e instanceof $classe,$nome.': '.get_class($e));}}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE logs_sistema (id INTEGER PRIMARY KEY AUTOINCREMENT, tipo TEXT NOT NULL, mensagem TEXT NOT NULL, ficheiro TEXT, linha INTEGER, detalhes TEXT, ip TEXT, user_agent TEXT, url TEXT, metodo TEXT, utilizador_id INTEGER, criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE perfis (id INTEGER PRIMARY KEY, nome TEXT, descricao TEXT)');
$db->exec('CREATE TABLE utilizadores (id INTEGER PRIMARY KEY, nome TEXT, perfil_id INTEGER, ativo INTEGER DEFAULT 1)');
$db->exec('CREATE TABLE permissoes (id INTEGER PRIMARY KEY, codigo TEXT, descricao TEXT)');
$db->exec('CREATE TABLE perfis_permissoes (perfil_id INTEGER, permissao_id INTEGER)');
$db->exec("INSERT INTO perfis VALUES (1,'Admin','Admin'),(2,'Consulta','Consulta'),(3,'Exportação','Exportação'),(4,'Sem acesso','Sem acesso')");
$db->exec("INSERT INTO utilizadores (id,nome,perfil_id) VALUES (1,'Admin',1),(2,'Leitor',2),(3,'Exportador',3),(4,'Sem acesso',4)");
$db->exec("INSERT INTO permissoes VALUES (1,'admin.logs.ver','Ver logs'),(2,'admin.logs.exportar','Exportar logs')");
$db->exec('INSERT INTO perfis_permissoes VALUES (2,1),(3,2)');
$stmt=$db->prepare('INSERT INTO logs_sistema (id,tipo,mensagem,ficheiro,linha,detalhes,ip,user_agent,url,metodo,utilizador_id,criado_em) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
for($id=1;$id<=625;$id++){
    $tipo=match($id){5=>'backup_erro',6=>'backup_sucesso',default=>$id%2?'erro':'info'};
    $mensagem=match($id){1=>'<script>alert(1)</script>',2=>'=1+1',3=>'  @SUM(1,2)',4=>"primeira,linha\nsegunda \"aspas\"",5=>'0',9=>'</mark><img src=x onerror=1>',default=>"Mensagem teste {$id}"};
    $data=match($id){1=>'2026-11-07 00:00:00',2=>'2026-11-07 23:59:59.999999',3=>'2026-11-06 23:59:59',4=>'2026-11-08 00:00:00',default=>'2026-11-07 12:00:00'};
    $stmt->execute([$id,$tipo,$mensagem,'ficheiro.php',12,'<img src=x onerror=1>',$id===7?'2001:db8::1':'127.0.0.1','Agente de teste','/teste','GET',1,$data]);
}
$instancia=new ReflectionProperty(Conexao::class,'instancia');$anterior=$instancia->getValue();$instancia->setValue(null,$db);
$service=new LogsSistemaConsultaService($db);
try{
    $lista=$service->listar();
    verificar($lista['total']===625&&$lista['paginas']===13&&count($lista['logs'])===50,'Total e paginação de 50 registos');
    $filtrada=$service->listar(['tipo'=>'erro']);
    verificar($filtrada['total']===312&&$filtrada['paginas']===7&&count($filtrada['logs'])===50,'Contagem respeita os filtros');
    verificar(array_unique(array_column($filtrada['logs'],'tipo'))===['erro'],'Linhas e contagem usam o mesmo filtro');
    verificar($service->listar(['p'=>(string)PHP_INT_MAX])['pagina']===13,'Página excessiva limitada sem overflow');
    verificar(count($service->listar(['p'=>13])['logs'])===25,'Última página tem os registos restantes');
    $primeira=array_column($lista['logs'],'id');$segunda=array_column($service->listar(['p'=>2])['logs'],'id');
    verificar(array_intersect($primeira,$segunda)===[],'Páginas não repetem registos');
    verificar($primeira[0]===625&&end($segunda)===526,'Ordenação decrescente por ID preservada');
    verificar(in_array('backup_erro',$lista['tipos'],true)&&in_array('backup_sucesso',$lista['tipos'],true),'Tipos de backup disponíveis no filtro');
    verificar($service->listar(['tipo'=>'backup_erro'])['total']===1,'Filtro aceita tipos personalizados');
    verificar($service->listar(['ip'=>'2001:db8::1'])['total']===1,'Filtro IPv6 exato');
    verificar($service->listar(['ip'=>'127.0.0.1'])['total']===624,'Filtro IPv4 exato');
    verificar($service->listar(['mensagem'=>'0','tipo'=>'backup_erro'])['total']===1,'Pesquisa por zero não é ignorada');
    verificar($service->listar(['data_inicio'=>'2026-11-07','data_fim'=>'2026-11-07'])['total']===623,'Datas incluem último dia e microssegundos');
    verificar($service->listar(['data_fim'=>'9999-12-31'])['total']===625,'Data máxima não gera ano 10000');
    verificar($service->listar(['mensagem'=>"' OR 1=1 --"])['total']===0,'Pesquisa SQL é parametrizada');
    $vazio=$service->listar(['tipo'=>'inexistente']);
    verificar($vazio['total']===0&&$vazio['paginas']===1&&$vazio['logs']===[],'Resultado vazio tem paginação válida');
    foreach([['tipo'=>[]],['ip'=>'inválido'],['ip'=>[]],['mensagem'=>[]],['mensagem'=>"a\0b"],['mensagem'=>str_repeat('a',501)],['data_inicio'=>'2026-02-29'],['data_inicio'=>'2026-11-08','data_fim'=>'2026-11-07'],['p'=>[]],['id'=>'-1']] as $entrada){
        rejeita(fn()=>$service->listar($entrada),InvalidArgumentException::class,'Rejeitar filtros inválidos: '.json_encode($entrada));
    }
    foreach([0,-1,[],true,'1 OR 1=1','9999999999999999999999999'] as $id){rejeita(fn()=>$service->obter($id),InvalidArgumentException::class,'Rejeitar ID inválido');}
    verificar($service->obter(9999)===null,'ID inexistente devolve resultado vazio');
    $gerador=$service->exportar(); // O máximo já foi fixado antes de aparecer novo registo.
    $db->exec("INSERT INTO logs_sistema (id,tipo,mensagem) VALUES (1000,'info','Registo concorrente')");
    $exportados=iterator_to_array($gerador,false);
    verificar(count($exportados)===625&&count(array_unique(array_column($exportados,'id')))===625,'Exportação percorre mais de um lote sem perdas nem duplicados');
    verificar(!in_array(1000,array_column($exportados,'id'),true),'Novos IDs não entram numa exportação já iniciada');
    $db->exec('DELETE FROM logs_sistema WHERE id=1000');
    verificar(count(iterator_to_array($service->exportar(['id'=>2]),false))===1,'Exportação por ID inclui apenas um log');
    rejeita(fn()=>$service->exportar(['ip'=>'inválido']),InvalidArgumentException::class,'Exportação valida antes de devolver gerador');

    class LogsControllerTeste extends \App\Controllers\Admin\LogsSistemaAdminController{
        public array $contexto=[];
        public function __construct(){
            $loader=new \Twig\Loader\FilesystemLoader();$loader->addPath(dirname(__DIR__).'/App/Views/admin','admin');
            $this->twig=new \Twig\Environment(new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader(['@admin/layout.twig'=>'<html><body>{% block content %}{% endblock %}</body></html>']),$loader]),['strict_variables'=>true]);
            $this->twig->addFunction(new \Twig\TwigFunction('isGranted',fn($p)=>Auth::user()?->hasPermissao($p)??false));
        }
        protected function render(string $template,array $data=[]):void{$this->contexto=$data;echo $this->twig->render($template,$data);}
        protected function acessoNegado(?string $permissao=null){throw new RuntimeException('Acesso negado');}
    }
    function executar(LogsControllerTeste $c,string $acao,array $args=[]):string{
        http_response_code(200);ob_start();try{$c->$acao(...$args);return (string)ob_get_contents();}finally{ob_end_clean();}
    }
    function lerCSV(string $csv):array{
        $stream=fopen('php://memory','w+');fwrite($stream,substr($csv,3));rewind($stream);$linhas=[];
        while(($linha=fgetcsv($stream,null,',','"',''))!==false){$linhas[]=$linha;}fclose($stream);return $linhas;
    }
    $controller=new LogsControllerTeste();$_SESSION['user_id']=1;
    $_GET=['mensagem'=>'<script>'];$html=executar($controller,'index');
    verificar(!str_contains($html,'<script>')&&str_contains($html,'<mark>&lt;script&gt;</mark>'),'Destaque da pesquisa não executa HTML guardado');
    $_GET=['mensagem'=>'<img'];$html=executar($controller,'index');
    verificar(!str_contains($html,'<img src=x')&&str_contains($html,'<mark>&lt;img</mark>'),'Destaque escapa também o termo pesquisado');
    $_GET=['tipo'=>'erro','p'=>'2'];$html=executar($controller,'index');
    verificar(str_contains($html,'tipo=erro')&&str_contains($html,'p=1')&&str_contains($html,'Exportar CSV'),'Navegação e exportação preservam filtros');
    $detalhe=executar($controller,'detalhes',[1]);
    verificar(!str_contains($detalhe,'<script>')&&!str_contains($detalhe,'<img src=x'),'Detalhes escapam mensagem e dados');
    verificar(str_contains($detalhe,'tipo=erro')&&str_contains($detalhe,'p=2')&&str_contains($detalhe,'exportar?id=1'),'Detalhe preserva retorno e oferece exportação de um registo');
    executar($controller,'detalhes',[9999]);verificar(http_response_code()===404,'Detalhe inexistente devolve 404');
    $_GET=['tipo'=>'inexistente'];$html=executar($controller,'index');
    verificar(str_contains($html,'Nenhum registo encontrado')&&!str_contains($html,'p=0'),'Página vazia sem navegação inválida');
    $_GET=['p'=>'13'];$csv=executar($controller,'exportar');$linhas=lerCSV($csv);
    verificar(str_starts_with($csv,"\xEF\xBB\xBF")&&count($linhas)===626,'CSV UTF-8 exporta todos os registos, não apenas a página');
    verificar($linhas[0]===['ID','Tipo','Mensagem','Ficheiro','Linha','IP','Criado em'],'CSV mantém as sete colunas e o separador originais');
    $porId=array_column(array_slice($linhas,1),null,0);
    verificar($porId[2][2]==="'=1+1"&&str_starts_with($porId[3][2],"'"),'CSV neutraliza fórmulas, incluindo espaços iniciais');
    verificar($porId[4][2]==="primeira,linha\nsegunda \"aspas\"",'CSV preserva vírgulas, aspas e quebras de linha');
    $_GET=['tipo'=>'backup_erro'];verificar(count(lerCSV(executar($controller,'exportar')))===2,'CSV aplica filtros da listagem');
    $_GET=['id'=>'2'];$unico=lerCSV(executar($controller,'exportar'));
    verificar(count($unico)===2&&$unico[1][0]==='2','Botão exportar este log não exporta o sistema inteiro');
    foreach(['index'=>[],'exportar'=>[],'detalhes'=>[1]] as $acao=>$args){
        $_GET=['ip'=>'invalido'];$erro=executar($controller,$acao,$args);
        verificar(http_response_code()===422&&str_contains($erro,'IPv4'),'Filtro inválido devolve erro em '.$acao);
        $_SESSION['user_id']=4;rejeita(fn()=>executar($controller,$acao,$args),RuntimeException::class,'Autorização real recusa acesso a '.$acao);
        unset($_SESSION['user_id']);rejeita(fn()=>executar($controller,$acao,$args),RuntimeException::class,'Anónimo não acede a '.$acao);$_SESSION['user_id']=1;
    }
    $_SESSION['user_id']=2;$_GET=[];$html=executar($controller,'index');
    verificar(!str_contains($html,'Exportar CSV'),'Leitor sem permissão não recebe botão de exportação');
    rejeita(fn()=>executar($controller,'exportar'),RuntimeException::class,'Permissão de consulta não concede exportação');
    $_SESSION['user_id']=3;verificar(count(lerCSV(executar($controller,'exportar')))===626,'Permissão separada de exportação é respeitada');
    rejeita(fn()=>executar($controller,'index'),RuntimeException::class,'Permissão de exportação não altera permissão de consulta');
    verificar((int)$db->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn()===625,'Consultar e exportar não alteram os logs');
    RouterSingleton::set(new Router());ob_start();require dirname(__DIR__).'/routes/admin.php';ob_end_clean();
    $rotas=(new ReflectionProperty(Router::class,'routes'))->getValue();$rotas=array_values(array_filter($rotas,fn($r)=>str_starts_with($r['uri'],'/admin/logs')));
    verificar(count($rotas)===3&&$rotas[1]['uri']==='/admin/logs/exportar','Rota estática de exportação precede detalhe por ID');
    foreach($rotas as $rota){[$classe,$metodo]=explode('@',$rota['action']);verificar(method_exists('App\\Controllers\\'.$classe,$metodo)&&in_array('auth',$rota['middleware'],true),'Rota protegida e válida: '.$rota['uri']);}
    verificar(Router::route('admin.logs.details',['id'=>1])==='/admin/logs/1','URL de detalhes resolve o ID');
    $config=require dirname(__DIR__).'/App/Config/permissoes.php';
    verificar(count(array_filter($config,fn($p)=>$p['codigo']==='admin.logs.exportar'))===1,'Permissão de exportação definida uma única vez');
    $migracaoDb=new PDO('sqlite::memory:');$migracaoDb->exec('CREATE TABLE permissoes (id INTEGER PRIMARY KEY AUTOINCREMENT, codigo TEXT UNIQUE, descricao TEXT)');
    $migracaoDb->exec("INSERT INTO permissoes (codigo,descricao) VALUES ('existente','Preservar')");
    $sql=file_get_contents(dirname(__DIR__).'/scripts/migrations/2026-11-07_logs_exportar_permissao.sql');$migracaoDb->exec($sql);$migracaoDb->exec($sql);
    verificar((int)$migracaoDb->query('SELECT COUNT(*) FROM permissoes')->fetchColumn()===2,'Migração de permissão é aditiva e idempotente');
    verificar($migracaoDb->query('SELECT codigo FROM permissoes WHERE id=1')->fetchColumn()==='existente','Migração preserva IDs e permissões anteriores');

    $_SESSION['user_id']=1;
    $_SERVER['REMOTE_ADDR']='2001:db8::1';$_SERVER['HTTP_USER_AGENT']=str_repeat('á',127).'€';$_SERVER['REQUEST_URI']=str_repeat('á',249).'€';$_SERVER['REQUEST_METHOD']='CUSTOM-LONG-METHOD';
    $id=LogSistema::registar(str_repeat('á',24).'€',str_repeat('á',249).'€',str_repeat('á',127).'€',12,str_repeat('á',2499).'€');
    $registo=$service->obter($id);
    foreach(['tipo','mensagem','ficheiro','detalhes','user_agent','url','metodo'] as $campo){verificar(mb_check_encoding($registo[$campo],'UTF-8'),'Truncagem UTF-8 válida: '.$campo);}
    verificar(strlen($registo['tipo'])<=50&&strlen($registo['mensagem'])<=500&&strlen($registo['ficheiro'])<=255&&strlen($registo['user_agent'])<=255&&strlen($registo['url'])<=500&&strlen($registo['metodo'])<=10,'Contexto respeita limites dos campos');
    verificar(str_ends_with($registo['detalhes'],'[TRUNCADO]')&&(int)$registo['utilizador_id']===1,'Gravação preserva autor e indicação de truncagem');
    $emoji="\u{1F642}";
    $emojiId=LogSistema::registar('info',str_repeat('a',499).$emoji,null,null,str_repeat('a',4999).$emoji);
    $emojiLog=$service->obter($emojiId);
    verificar(mb_check_encoding($emojiLog['mensagem'],'UTF-8')&&mb_check_encoding($emojiLog['detalhes'],'UTF-8'),'Truncagem não corta caracteres Unicode de quatro bytes');
    // Cenário de retenção isolado, depois de terminadas as consultas da amostra.
    $db->exec('DELETE FROM logs_sistema');$stmt=$db->prepare('INSERT INTO logs_sistema (tipo,mensagem,criado_em) VALUES (?,?,?)');
    $agora=new DateTimeImmutable();foreach([91,100,2] as $dias){$stmt->execute(['info','Retenção de teste',$agora->modify("-{$dias} days")->format('Y-m-d H:i:s')]);}
    $stmt->execute(['info','Data ausente',null]);
    rejeita(fn()=>LogSistema::limparAntigos(0),InvalidArgumentException::class,'Retenção zero não remove dados');
    rejeita(fn()=>LogSistema::limparAntigos(-1),InvalidArgumentException::class,'Retenção negativa não remove dados');
    verificar(LogSistema::limparAntigos(90)===2,'Retenção remove apenas logs anteriores ao prazo');
    verificar((int)$db->query('SELECT COUNT(*) FROM logs_sistema')->fetchColumn()===2,'Retenção preserva logs recentes e datas ausentes');
}finally{$instancia->setValue(null,$anterior);$_SESSION=[];session_destroy();}
echo "Verificações: {$checks}; falhas: ".count($falhas).PHP_EOL;foreach($falhas as $falha){echo 'FALHA: '.$falha.PHP_EOL;}exit($falhas===[]?0:1);
