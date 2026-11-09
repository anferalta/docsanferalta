<?php
// php scripts/validar_menu_dashboard.php [diretório opcional para amostras HTML]
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Auth;use App\Core\Conexao;use App\Core\Menu;use App\Core\Router;use App\Core\RouterSingleton;use App\Models\Utilizador;use App\Services\DashboardService;
error_reporting(E_ALL);set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});session_start();
$checks=0;$falhas=[];function verificar(bool $ok,string $nome):void{global $checks,$falhas;$checks++;if(!$ok){$falhas[]=$nome;}}
class PainelPDO extends PDO{
    public array $consultas=[];
    public function query(string $query,?int $fetchMode=null,mixed ...$args):PDOStatement|false{$this->consultas[]=$query;return $fetchMode===null?parent::query($query):parent::query($query,$fetchMode,...$args);}
    public function prepare(string $query,array $options=[]):PDOStatement|false{$this->consultas[]=$query;return parent::prepare($query,$options);}
}
$db=new PainelPDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('CREATE TABLE utilizadores(id INTEGER PRIMARY KEY,nome TEXT,email TEXT,perfil_id INTEGER,ativo INTEGER,aprovado_em TEXT,criado_em TEXT)');$db->exec('CREATE TABLE perfis(id INTEGER PRIMARY KEY,nome TEXT,descricao TEXT)');$db->exec('CREATE TABLE permissoes(id INTEGER PRIMARY KEY,codigo TEXT,descricao TEXT)');$db->exec('CREATE TABLE perfis_permissoes(perfil_id INTEGER,permissao_id INTEGER)');
$db->exec('CREATE TABLE documentos(id INTEGER PRIMARY KEY,titulo TEXT,estado_atual TEXT,criado_por INTEGER,area_atual_id INTEGER,area_atual_desde TEXT,criado_em TEXT)');$db->exec('CREATE TABLE documento_areas(id INTEGER PRIMARY KEY,nome TEXT,prazo_resposta INTEGER)');$db->exec('CREATE TABLE documento_tipos(id INTEGER PRIMARY KEY)');$db->exec('CREATE TABLE documento_estados(id INTEGER PRIMARY KEY)');$db->exec('CREATE TABLE auditoria(id INTEGER PRIMARY KEY,acao TEXT,criado_em TEXT)');
$roles=[1=>'Admin',2=>'Dashboard',3=>'Utilizador',4=>'Gestão de contas',5=>'Backups',6=>'Sem acesso',7=>'Gestor'];$stmt=$db->prepare('INSERT INTO perfis VALUES (?,?,?)');foreach($roles as$id=>$nome){$stmt->execute([$id,$nome,$nome]);}
$codes=['admin.dashboard.ver','admin.documentos.ver','admin.utilizadores.ver','admin.backups.files.ver'];$stmt=$db->prepare('INSERT INTO permissoes VALUES (?,?,?)');foreach($codes as$i=>$code){$stmt->execute([$i+1,$code,$code]);}$db->exec('INSERT INTO perfis_permissoes VALUES (2,1),(3,1),(3,2),(4,1),(4,3),(5,4),(7,1),(7,2)');
$stmt=$db->prepare('INSERT INTO utilizadores VALUES (?,?,?,?,?,?,?)');for($i=1;$i<=10;$i++){$stmt->execute([$i,$i===1?'Admin <script>teste</script>':'Utilizador '.$i,'u'.$i.'@example.test',min($i,7),$i<=7?1:0,$i===8?null:'2026-11-08 10:00:00',match($i){8=>'2025-12-01 00:00:00',9=>'2025-11-30 23:59:59',10=>'2026-12-01 00:00:00',default=>'2026-11-08 12:00:00'}]);}
$db->exec("INSERT INTO documento_areas VALUES (1,'Área A',3),(2,'Área <img src=x>',3)");$db->exec('INSERT INTO documento_tipos VALUES (1),(2)');$db->exec('INSERT INTO documento_estados VALUES (1),(2),(3)');$db->exec("INSERT INTO auditoria VALUES (1,'Criou <script>auditoria</script>','2026-11-08 12:00:00')");
$agora=new DateTimeImmutable('2026-11-08 12:00:00');$stmt=$db->prepare('INSERT INTO documentos VALUES (?,?,?,?,?,?,?)');
foreach([[1,'novo',3,1,3],[2,'pendente',3,1,4],[3,'analise',2,2,6],[4,'em_tramitacao',1,2,null],[5,'concluido',7,1,5],[6,'arquivado',3,1,20],[7,'devolvido',2,2,20],[8,'pendente',2,2,-1],[9,'concluido',3,null,4]]as[$id,$estado,$autor,$area,$dias]){$stmt->execute([$id,'Documento <script>'.$id.'</script>',$estado,$autor,$area,$dias===null?null:$agora->modify("-{$dias} days")->format('Y-m-d H:i:s'),'2026-11-08 12:00:00']);}
$instancia=new ReflectionProperty(Conexao::class,'instancia');$anterior=$instancia->getValue();$instancia->setValue(null,$db);$service=new DashboardService($db);$menu=new Menu();
function itens(array $menu):array{$r=[];foreach($menu as$i){if(isset($i['url'])){$r[$i['url']]=$i;}if(isset($i['sub'])){$r+=itens($i['sub']);}}return$r;}
try{
    $db->consultas=[];$definicoes=$menu->getMenu();verificar($db->consultas===[],'Definir menu não consulta tabelas');
    unset($_SESSION['user_id']);verificar($menu->filtrarMenu($definicoes)===[],'Anónimo não recebe menu');
    $_SESSION['user_id']=8;verificar($menu->filtrarMenu($definicoes)===[]&&!isset($_SESSION['user_id']),'Conta inativa não recebe menu');
    $_SESSION['user_id']=6;verificar($menu->filtrarMenu($definicoes)===[],'Sem permissões não deixa cabeçalhos vazios');
    $_SESSION['user_id']=2;$db->consultas=[];$pessoal=$menu->filtrarMenu($definicoes);verificar(array_keys(itens($pessoal))===['/admin/dashboard'],'Dashboard sem módulos não expõe outros links');
    verificar(!(bool)array_filter($db->consultas,fn($q)=>str_contains($q,'FROM documentos')||str_contains($q,'COUNT(*) AS total')||str_contains($q,'FROM documento_')),'Itens ocultos não carregam contadores');
    $_SESSION['user_id']=4;$nav=itens($menu->filtrarMenu($definicoes));verificar(isset($nav['/admin/utilizadores/pendentes'],$nav['/admin/utilizadores/bloqueados']),'Listas de utilizadores respeitam permissão de consulta');verificar(!isset($nav['/admin/utilizadores/criar']),'Criação mantém permissão própria');
    verificar($nav['/admin/utilizadores']['badge']===10&&$nav['/admin/utilizadores/pendentes']['badge']===1&&$nav['/admin/utilizadores/bloqueados']['badge']===2,'Contadores de utilizadores coerentes');
    $_SESSION['user_id']=5;$nav=$menu->filtrarMenu($definicoes);verificar(isset(itens($nav)['/admin/backups']),'Backups de ficheiros dão acesso ao item Backups');verificar(array_column(array_filter($nav,fn($i)=>isset($i['header'])),'header')===['SISTEMA'],'Secções sem itens são removidas');
    $_SESSION['user_id']=3;$nav=itens($menu->filtrarMenu($definicoes));verificar($nav['/admin/documentos']['badge']===4,'Contador documental comum respeita documentos do autor');
    $_SESSION['user_id']=7;$nav=itens($menu->filtrarMenu($definicoes));verificar($nav['/admin/documentos']['badge']===9,'Gestor mantém âmbito global da listagem documental');
    $_SESSION['user_id']=1;$db->consultas=[];$nav=$menu->filtrarMenu($definicoes);$adminMenu=$nav;$mapeado=itens($nav);
    verificar($mapeado['/admin/documentos']['badge']===9&&$mapeado['/admin/tramitacao']['badge']===7,'Contadores administrativos e fluxo ativo');
    verificar(count(array_filter($db->consultas,fn($q)=>str_contains($q,'FROM documentos')&&str_contains($q,'GROUP BY')))===1,'Uma consulta documental agregada por menu');
    verificar(count(array_filter($db->consultas,fn($q)=>str_contains($q,'FROM utilizadores')&&str_contains($q,'COALESCE(SUM')))===1,'Uma consulta agregada para os quatro totais de utilizadores');
    foreach(['/admin/utilizadores/pendentes?x=1'=>'/admin/utilizadores/pendentes','/admin/utilizadores/editar/3'=>'/admin/utilizadores','/admin'=>'/admin/dashboard','/admin/dashboard/'=>'/admin/dashboard']as$path=>$esperado){$ativos=array_keys(array_filter(itens($menu->marcarAtivo($nav,$path)),fn($i)=>$i['ativo']));verificar($ativos===[$esperado],'Um link ativo correto: '.$path);}
    verificar(array_filter(itens($menu->marcarAtivo($nav,'/admin/utilizadores-extra')),fn($i)=>$i['ativo'])===[],'Prefixos semelhantes não ativam links errados');
    $dados=$service->carregar(Auth::user(),$agora);
    verificar($dados['documentos']['fluxo']===7&&$dados['documentos']['em_tramitacao']===1,'Fluxo ativo distinto do estado em tramitação');
    verificar($dados['sla']===['ok'=>1,'alerta'=>2,'atrasado'=>1,'indefinido'=>3],'Cartões SLA preservam limites e contam indefinidos');
    verificar(array_sum(array_column($dados['rankingAreas'],'total'))===7,'Ranking cobre todo o fluxo ativo');
    foreach(['ok','alerta','atrasado','indefinido']as$classe){verificar(array_sum(array_column($dados['rankingAreas'],$classe))===$dados['sla'][$classe],'Ranking e cartões usam o mesmo SLA: '.$classe);}
    verificar($dados['rankingAreas'][0]['area']==='Área <img src=x>','Ranking ordena por atrasos');
    verificar(count($dados['meses'])===12&&$dados['meses'][0]['mes']==='12/2025'&&$dados['meses'][11]['mes']==='11/2026','Gráfico tem exatamente 12 meses completos');
    verificar(array_sum(array_column($dados['meses'],'total'))===8&&$dados['meses'][0]['total']===1,'Série inclui início do mês e exclui registos futuros');
    verificar(!array_key_exists('password',$dados['ultimosUsers'][0]),'Resumo de contas não carrega hashes');
    $_SESSION['user_id']=2;$normal=$service->carregar(Auth::user(),$agora);verificar($normal['documentos']['total']===3&&$normal['documentos']['pendente']===1&&$normal['documentos']['concluido']===0,'Resumo pessoal só conta documentos próprios');verificar(!isset($normal['sla'],$normal['utilizadores'],$normal['ultimosUsers'],$normal['ultimosLogs']),'Resumo pessoal não carrega dados administrativos');
    $_SESSION['user_id']=7;verificar($service->carregar(Auth::user(),$agora)['documentos']['total']===1,'Gestor mantém o resumo pessoal existente');
    $inativo=Utilizador::find(8);try{$service->carregar($inativo);verificar(false,'Serviço recusa inativo');}catch(DomainException $e){verificar(true,'Serviço recusa inativo');}

    class DashboardControllerTeste extends \App\Controllers\Admin\DashboardAdminController{
        public array $contexto=[];
        public function __construct(){
            $l=new \Twig\Loader\FilesystemLoader();$l->addPath(dirname(__DIR__).'/App/Views/admin','admin');$this->twig=new \Twig\Environment($l,['strict_variables'=>true]);
            $this->twig->addGlobal('sessao',new \App\Core\Sessao());$this->twig->addFunction(new \Twig\TwigFunction('isGranted',fn($p)=>Auth::user()?->hasPermissao($p)??false));
        }
        protected function render(string $t,array $d=[]):void{
            $user=Auth::user();$nav=(new Menu())->filtrarMenu((new Menu())->getMenu());$this->contexto=$d;
            echo $this->twig->render($t,$d+['menuAdmin'=>$nav,'auth'=>(object)['user'=>$user],'app'=>(object)['request'=>new \App\Core\TwigRequestContext()]]);
        }
        protected function acessoNegado(?string $p=null){throw new RuntimeException('Acesso negado');}
        public function amostra(array $dados):string{ob_start();try{$this->render('@admin/dashboard/index.twig',$dados);return(string)ob_get_contents();}finally{ob_end_clean();}}
    }
    $c=new DashboardControllerTeste();$_SESSION['user_id']=1;$_SERVER['REQUEST_URI']='/admin/dashboard';$html=$c->amostra($dados);
    verificar(!str_contains($html,'<script>teste</script>')&&!str_contains($html,'<img src=x>'),'Layout e dashboard escapam nomes e áreas');
    verificar(str_contains($html,'Indefinido')&&str_contains($html,'Novos utilizadores')&&!str_contains($html,'new Chart'),'Dashboard completo sem dependência de gráficos externa');
    verificar(substr_count($html,'aria-current="page"')===1,'Layout marca apenas um destino ativo');
    verificar(substr_count($html,'id="btnToggleSidebar"')===1&&str_contains($html,'aria-controls="sidebar"'),'Menu tem controlo acessível único');
    verificar(str_contains($html,'name="viewport" content="width=device-width, initial-scale=1"'),'Layout declara a largura do ecrã móvel');
    verificar(!str_contains($html,'id="btnDashboardMenu"'),'Dashboard não duplica o título com um segundo botão');
    verificar(!str_contains($html,'sidebarWrapper.classList.toggle')&&!str_contains($html,'const toggleBtn'),'Layout não duplica handlers do menu móvel');
    $_SESSION['user_id']=2;$pessoalHtml=$c->amostra($normal);verificar(str_contains($pessoalHtml,'O seu dashboard')&&!str_contains($pessoalHtml,'Prazos SLA')&&!str_contains($pessoalHtml,'Novos utilizadores'),'Layout pessoal não renderiza indicadores administrativos');
    $_SESSION['user_id']=5;$negado=false;try{$c->index();}catch(RuntimeException $e){$negado=true;}verificar($negado,'Controlador usa autorização real do dashboard');
    unset($_SESSION['user_id']);$negado=false;try{$c->index();}catch(RuntimeException $e){$negado=true;}verificar($negado,'Anónimo não abre dashboard');
    RouterSingleton::set(new Router());ob_start();require dirname(__DIR__).'/routes/admin.php';ob_end_clean();$rotas=(new ReflectionProperty(Router::class,'routes'))->getValue();
    foreach($mapeado as$url=>$item){$r=array_values(array_filter($rotas,fn($r)=>$r['method']==='GET'&&$r['uri']===$url));verificar(count($r)===1,'Menu aponta para uma rota GET existente: '.$url);if($r){[$classe,$metodo]=explode('@',$r[0]['action']);verificar(method_exists('App\\Controllers\\'.$classe,$metodo),'Destino do menu tem método existente: '.$url);}}
    if(isset($argv[1])){if(!is_dir($argv[1])){mkdir($argv[1],0770,true);}file_put_contents($argv[1].'/admin.html',$html);file_put_contents($argv[1].'/pessoal.html',$pessoalHtml);}
    $db->exec('DELETE FROM documentos');$db->exec('DELETE FROM auditoria');$_SESSION['user_id']=1;$vazio=$service->carregar(Auth::user(),$agora);$vazioHtml=$c->amostra($vazio);
    verificar(array_sum($vazio['sla'])===0&&str_contains($vazioHtml,'Sem documentos no fluxo ativo.'),'Dashboard vazio mantém indicadores e mensagem válidos');
}finally{$instancia->setValue(null,$anterior);$_SESSION=[];session_destroy();}
echo "Verificações: {$checks}; falhas: ".count($falhas).PHP_EOL;foreach($falhas as$falha){echo 'FALHA: '.$falha.PHP_EOL;}exit($falhas===[]?0:1);
