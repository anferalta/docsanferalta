<?php
// php scripts/validar_utilizadores.php — só SQLite em memória e notificações simuladas.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\Auth;
use App\Core\Conexao;
use App\Core\CSRF;
use App\Core\Router;
use App\Core\RouterSingleton;
use App\Models\Utilizador;
use App\Services\UtilizadoresService;
error_reporting(E_ALL);set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});session_start();
$checks=0;$falhas=[];
function verificar(bool $ok,string $nome):void{global $checks,$falhas;$checks++;if(!$ok){$falhas[]=$nome;}}
function rejeita(callable $f,string $classe,string $nome):void{try{$f();verificar(false,$nome);}catch(Throwable $e){verificar($e instanceof $classe,$nome.': '.get_class($e));}}
$db=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->sqliteCreateFunction('NOW',fn()=>date('Y-m-d H:i:s'));
$db->exec('CREATE TABLE utilizadores (id INTEGER PRIMARY KEY AUTOINCREMENT,nome TEXT,email TEXT UNIQUE COLLATE NOCASE,password TEXT,perfil_id INTEGER,ativo INTEGER DEFAULT 1,aprovado_por INTEGER,aprovado_em TEXT,ultimo_login TEXT,tentativas_falhadas INTEGER DEFAULT 0,criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE perfis (id INTEGER PRIMARY KEY,nome TEXT,descricao TEXT)');$db->exec('CREATE TABLE permissoes (id INTEGER PRIMARY KEY,codigo TEXT UNIQUE,descricao TEXT)');$db->exec('CREATE TABLE perfis_permissoes (perfil_id INTEGER,permissao_id INTEGER)');$db->exec('CREATE TABLE utilizadores_permissoes (utilizador_id INTEGER,permissao_id INTEGER)');
$db->exec('CREATE TABLE password_resets (email TEXT PRIMARY KEY,token TEXT,criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE auditoria (id INTEGER PRIMARY KEY AUTOINCREMENT,utilizador_id INTEGER,acao TEXT,detalhes TEXT,ip TEXT,criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec('CREATE TABLE auditoria_eliminacoes (id INTEGER PRIMARY KEY AUTOINCREMENT,tabela TEXT,registo_id INTEGER,dados TEXT,apagado_por INTEGER,ip TEXT,criado_em TEXT DEFAULT CURRENT_TIMESTAMP)');
$db->exec("INSERT INTO perfis VALUES (1,'Admin','Admin'),(2,'Gestor','Gestor'),(3,'Aprovador','Aprovador'),(4,'Leitor','Leitor'),(5,'Utilizador','Utilizador')");
$insert=$db->prepare('INSERT INTO permissoes VALUES (?,?,?)');foreach(['ver','criar','editar','apagar','aprovar']as$i=>$acao){$insert->execute([$i+1,'admin.utilizadores.'.$acao,$acao]);}
$db->exec('INSERT INTO perfis_permissoes VALUES (2,1),(2,2),(2,3),(2,4),(3,1),(3,5),(4,1)');
$hash=password_hash('SenhaTeste123',PASSWORD_DEFAULT);$insert=$db->prepare('INSERT INTO utilizadores (id,nome,email,password,perfil_id,ativo,aprovado_em) VALUES (?,?,?,?,?,?,?)');
for($i=1;$i<=535;$i++){
    $perfil=match($i){1,2,10=>1,3=>2,4=>3,5=>4,default=>5};$ativo=in_array($i,[8,9,10],true)?0:1;$aprovado=in_array($i,[8,10],true)?null:'2026-11-07 10:00:00';
    $insert->execute([$i,$i===11?'=1+1':'Pessoa '.$i,'u'.$i.'@example.test',$hash,$perfil,$ativo,$aprovado]);
}
$instancia=new ReflectionProperty(Conexao::class,'instancia');$anterior=$instancia->getValue();$instancia->setValue(null,$db);$_SESSION['user_id']=1;
$notificacoes=[];$notificar=function(string $acao,array $user)use(&$notificacoes,$db):bool{
    verificar(!$db->inTransaction(),'Notificação só depois do commit');verificar(!array_key_exists('password',$user),'Notificação não recebe palavra-passe ou hash');$notificacoes[]=[$acao,$user];return true;
};$service=new UtilizadoresService($notificar);
function dados(string $nome='Novo',string $email='novo@example.test',int $perfil=5,int $ativo=1):array{return ['nome'=>$nome,'email'=>$email,'perfil_id'=>(string)$perfil,'ativo'=>(string)$ativo,'password'=>'SenhaTeste123','password_confirmation'=>'SenhaTeste123'];}
try{
    $lista=$service->listar();verificar($lista['total']===535&&$lista['total_paginas']===54&&count($lista['utilizadores'])===10,'Paginação geral de 10 contas');
    verificar($service->listar([], 'pendentes')['total']===2,'Pendente: inativo e sem aprovação');verificar($service->listar([],'bloqueados')['total']===1,'Bloqueado: inativo e aprovado');verificar($service->listar([],'ativos')['total']===532,'Lista de ativos');
    verificar($service->listar(['page'=>(string)PHP_INT_MAX])['pagina']===54,'Página excessiva limitada sem overflow');
    verificar(!array_key_exists('password',$lista['utilizadores'][0]),'Listagem não carrega hashes');
    foreach([['ativo'=>'2'],['nome'=>[]],['email'=>[]],['page'=>[]],['nome'=>"a\0b"]]as$f){rejeita(fn()=>$service->listar($f),InvalidArgumentException::class,'Filtros inválidos');}
    foreach([0,-1,[],true,'1 OR 1=1']as$id){rejeita(fn()=>$service->obter($id),InvalidArgumentException::class,'ID inválido');}
    verificar(Utilizador::findByEmail(' U7@EXAMPLE.TEST ')->id===7,'Pesquisa habitual por email normalizado');
    $db->exec("UPDATE utilizadores SET email=' u7 @example.test ' WHERE id=7");verificar(Utilizador::findByEmail('u7@example.test')->id===7,'Compatibilidade com email antigo contendo espaços');$db->exec("UPDATE utilizadores SET email='u7@example.test' WHERE id=7");
    verificar(Auth::attempt('u7@example.test','SenhaTeste123') instanceof Utilizador,'Login ativo permanece funcional');$_SESSION['user_id']=1;
    verificar(Auth::attempt('u8@example.test','SenhaTeste123')===null,'Login de pendente mantém sinalização existente');
    foreach([['nome'=>[]],['nome'=>''],['email'=>'inválido'],['perfil_id'=>999],['ativo'=>'2'],['password'=>"a\0bcdef"],['password'=>str_repeat('a',73),'password_confirmation'=>str_repeat('a',73)],['password_confirmation'=>'diferente']]as$alteracoes){
        rejeita(fn()=>$service->alterar('criar',null,array_replace(dados(),$alteracoes),1),InvalidArgumentException::class,'Criação inválida não altera contas');
    }
    rejeita(fn()=>$service->alterar('criar',null,dados('Duplicado',' U7@example.test '),1),InvalidArgumentException::class,'Duplicação normalizada de email recusada');
    foreach(['criar','editar']as$acao){rejeita(fn()=>$service->alterar($acao,$acao==='editar'?7:null,dados('Escalada','escalada@example.test',1),3),DomainException::class,'Gestor não atribui Administrador em '.$acao);}
    foreach(['editar','eliminar','bloquear']as$acao){rejeita(fn()=>$service->alterar($acao,2,dados('Admin','u2@example.test',1),3),DomainException::class,'Gestor não altera Administrador em '.$acao);}
    rejeita(fn()=>$service->alterar('aprovar',8,['perfil_id'=>'5'],3),DomainException::class,'Aprovação exige permissão própria');
    rejeita(fn()=>$service->alterar('aprovar',10,['perfil_id'=>'5'],4),DomainException::class,'Aprovador não altera conta de Administrador');
    foreach(['bloquear','eliminar']as$acao){rejeita(fn()=>$service->alterar($acao,1,[],1),DomainException::class,'Proteção da própria conta: '.$acao);}
    rejeita(fn()=>$service->alterar('editar',3,dados('Gestor','u3@example.test',5),3),DomainException::class,'Gestor não retira o próprio perfil');
    rejeita(fn()=>$service->alterar('editar',8,dados('Pendente','u8@example.test',5,1),3),DomainException::class,'Edição não contorna aprovação');
    rejeita(fn()=>$service->alterar('desbloquear',8,[],3),DomainException::class,'Desbloqueio não contorna aprovação');
    $criado=$service->alterar('criar',null,dados('  Novo  ',' NOVO@EXAMPLE.TEST ',5,0),1);$novo=$criado['utilizador']['id'];
    $user=$service->obter($novo);verificar($user['nome']==='Novo'&&$user['email']==='novo@example.test'&&$user['ativo']===0&&$user['aprovado_em']!==null,'Criação respeita estado e normaliza os dados');verificar(password_verify('SenhaTeste123',$user['password']),'Palavra-passe guardada como hash');
    $service->alterar('aprovar',8,['perfil_id'=>'5'],4);verificar($service->obter(8)['ativo']===1&&(int)$service->obter(8)['aprovado_por']===4,'Aprovação grava autor e estado');
    rejeita(fn()=>$service->alterar('aprovar',8,['perfil_id'=>'5'],4),DomainException::class,'Aprovação repetida não volta a alterar nem notificar');
    $editar=dados('Nome revisto','revisto@example.test');$editar['password']='NovaSenha456';$editar['password_confirmation']='NovaSenha456';
    $db->exec("INSERT INTO password_resets (email,token) VALUES ('u7@example.test','antigo')");
    $service->alterar('editar',7,$editar,3);$revisto=$service->obter(7);verificar(password_verify('NovaSenha456',$revisto['password']),'Edição aplica a nova palavra-passe');
    verificar($db->query("SELECT token FROM password_resets WHERE email='u7@example.test'")->fetchColumn()===false,'Alteração de email ou password invalida tokens antigos');
    $editar['password']='';$editar['password_confirmation']='';$service->alterar('editar',7,$editar,3);verificar($service->obter(7)['password']===$revisto['password'],'Campos vazios mantêm palavra-passe');
    $falhaEmail=new UtilizadoresService(static fn()=>false);$r=$falhaEmail->alterar('bloquear',7,[],3);verificar(!$r['notificado']&&$service->obter(7)['ativo']===0,'Falha de email não impede bloqueio');
    $_SESSION['user_id']=7;verificar(Auth::user()===null&&!isset($_SESSION['user_id']),'Bloqueio invalida sessão no pedido seguinte');verificar(Auth::attempt('revisto@example.test','NovaSenha456')===false,'Conta bloqueada não entra');$_SESSION['user_id']=1;
    $service->alterar('desbloquear',7,[],3);verificar($service->obter(7)['ativo']===1,'Desbloqueio de conta aprovada');
    $excecaoEmail=new UtilizadoresService(static function(){throw new RuntimeException('SMTP indisponível');});$r=$excecaoEmail->alterar('criar',null,dados('Sem email','sem-email@example.test'),1);verificar(!$r['notificado']&&$service->obter($r['utilizador']['id'])['ativo']===1,'Exceção de SMTP não perde conta criada');
    rejeita(fn()=>$service->alterar('rejeitar',7,[],3),DomainException::class,'Não rejeitar conta já aprovada');
    $db->exec("INSERT INTO utilizadores (id,nome,email,password,perfil_id,ativo) VALUES (900,'Pendente','pendente@example.test','hash',5,0)");
    $db->exec("INSERT INTO utilizadores_permissoes VALUES (900,1)");$db->exec("INSERT INTO password_resets (email,token) VALUES ('pendente@example.test','revogar')");
    $service->alterar('rejeitar',900,[],3);verificar((int)$db->query('SELECT COUNT(*) FROM utilizadores WHERE id=900')->fetchColumn()===0,'Rejeição remove apenas conta pendente');
    verificar((int)$db->query('SELECT COUNT(*) FROM utilizadores_permissoes WHERE utilizador_id=900')->fetchColumn()===0&&$db->query("SELECT token FROM password_resets WHERE email='pendente@example.test'")->fetchColumn()===false,'Remoção revoga permissões diretas e tokens');
    $snapshot=json_decode($db->query('SELECT dados FROM auditoria_eliminacoes ORDER BY id DESC LIMIT 1')->fetchColumn(),true);verificar(!array_key_exists('password',$snapshot),'Auditoria de eliminação não expõe hash');
    $db->exec("CREATE TRIGGER falha_auditoria BEFORE INSERT ON auditoria BEGIN SELECT RAISE(ABORT,'Falha simulada'); END");$antes=(int)$db->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn();$envios=count($notificacoes);
    rejeita(fn()=>$service->alterar('criar',null,dados('Reverter','reverter@example.test'),1),PDOException::class,'Falha de auditoria reverte criação');verificar((int)$db->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn()===$antes&&count($notificacoes)===$envios,'Rollback não deixa conta nem envia notificação');
    rejeita(fn()=>$service->alterar('eliminar',$novo,[],1),PDOException::class,'Falha de auditoria reverte eliminação');verificar($service->obter($novo)['email']==='novo@example.test','Conta e evidência preservadas após rollback');$db->exec('DROP TRIGGER falha_auditoria');

    class UtilizadoresControllerTeste extends \App\Controllers\Admin\UtilizadoresAdminController{
        public array $contexto=[];public ?string $destino=null;public UtilizadoresService $service;
        public function __construct(UtilizadoresService $s){$this->service=$s;$l=new \Twig\Loader\FilesystemLoader();$l->addPath(dirname(__DIR__).'/App/Views/admin','admin');$this->twig=new \Twig\Environment(new \Twig\Loader\ChainLoader([new \Twig\Loader\ArrayLoader(['@admin/layout.twig'=>'<html><body>{% block content %}{% endblock %}</body></html>']),$l]),['strict_variables'=>true]);$this->twig->addFunction(new \Twig\TwigFunction('isGranted',fn($p)=>Auth::user()?->hasPermissao($p)??false));$this->twig->addFunction(new \Twig\TwigFunction('csrf_field',fn()=>CSRF::field(),['is_safe'=>['html']]));}
        protected function servico():UtilizadoresService{return $this->service;}
        protected function render(string $t,array $d=[]):void{$this->contexto=$d;echo $this->twig->render($t,$d);}
        protected function redirect(string $url):void{$this->destino=$url;}
        protected function acessoNegado(?string $p=null){throw new RuntimeException('Acesso negado');}
    }
    function executar(UtilizadoresControllerTeste $c,string $acao,array $args=[]):string{http_response_code(200);$c->destino=null;ob_start();try{$c->$acao(...$args);return(string)ob_get_contents();}finally{ob_end_clean();}}
    $c=new UtilizadoresControllerTeste($service);$_SESSION['user_id']=1;$_GET=['page'=>'2','nome'=>'Pessoa'];
    foreach(['index','ativos','pendentes','bloqueados']as$acao){$html=executar($c,$acao);verificar(str_contains($html,'Exportar CSV')&&!str_contains($html,$hash),'Lista renderizada sem hashes: '.$acao);}
    $_GET=[];$html=executar($c,'criar');verificar(str_contains($html,'name="password_confirmation"')&&str_contains($html,'name="_csrf"'),'Criação inclui confirmação e CSRF');
    $_POST=['nome'=>'','email'=>'<script>email</script>','password'=>'NuncaDevolver'];$html=executar($c,'criarSubmit');verificar(http_response_code()===422&&str_contains($html,'&lt;script&gt;email&lt;/script&gt;')&&!str_contains($html,'NuncaDevolver'),'Erro mantém dados seguros e não devolve palavra-passe');
    $_POST=dados('Criado no controlador','controlador@example.test');executar($c,'criarSubmit');verificar($c->destino==='/admin/utilizadores','Criação pelo controlador redireciona após sucesso');
    $html=executar($c,'editar',[7]);verificar(!str_contains(json_encode($c->contexto),'"password"')&&!str_contains($html,$service->obter(7)['password']),'Formulário de edição não recebe hash');
    executar($c,'editar',[99999]);verificar(http_response_code()===404,'Conta inexistente devolve 404');
    $_SESSION['user_id']=3;$html=executar($c,'criar');verificar(!str_contains($html,'value="1" >Admin')&&!preg_match('/<option value="1"[^>]*>Admin/',$html),'Gestor não recebe Administrador no seletor');
    executar($c,'editar',[2]);verificar(http_response_code()===403,'Proteção do Administrador aplicada no formulário');
    $_SESSION['user_id']=5;$_GET=[];$html=executar($c,'index');verificar(!str_contains($html,'Novo utilizador')&&!str_contains($html,'>Aprovar</button>'),'Leitor não recebe ações de escrita');
    foreach(['criar'=>[],'criarSubmit'=>[],'editar'=>[7],'editarSubmit'=>[7],'eliminar'=>[7],'bloquear'=>[7],'desbloquear'=>[9],'aprovar'=>[10],'rejeitar'=>[10]]as$acao=>$args){rejeita(fn()=>executar($c,$acao,$args),RuntimeException::class,'Autorização real bloqueia leitor em '.$acao);}
    unset($_SESSION['user_id']);rejeita(fn()=>executar($c,'index'),RuntimeException::class,'Anónimo não consulta utilizadores');$_SESSION['user_id']=1;
    $_GET=['nome'=>'=1+1'];$csv=executar($c,'exportarCSV');$stream=fopen('php://memory','w+');fwrite($stream,substr($csv,3));rewind($stream);$cabecalho=fgetcsv($stream,null,',','"','');$linha=fgetcsv($stream,null,',','"','');$fim=fgetcsv($stream,null,',','"','');fclose($stream);
    verificar($cabecalho===['ID','Nome','Email','Ativo','Criado em']&&$linha[1]==="'=1+1"&&$fim===false,'CSV aplica filtro, preserva colunas e neutraliza fórmulas');
    $exportados=iterator_to_array($service->exportar(),false);verificar(count($exportados)===(int)$db->query('SELECT COUNT(*) FROM utilizadores')->fetchColumn(),'CSV percorre mais de 500 contas sem perdas');verificar(!array_key_exists('password',$exportados[0]),'Exportação não carrega hashes');
    RouterSingleton::set(new Router());ob_start();require dirname(__DIR__).'/routes/admin.php';ob_end_clean();$rotas=(new ReflectionProperty(Router::class,'routes'))->getValue();$rotas=array_values(array_filter($rotas,fn($r)=>str_starts_with($r['uri'],'/admin/utilizadores')));verificar(count($rotas)===14,'Rotas de utilizadores preservadas');
    foreach($rotas as$rota){[$classe,$metodo]=explode('@',$rota['action']);verificar(method_exists('App\\Controllers\\'.$classe,$metodo)&&in_array('auth',$rota['middleware'],true)&&($rota['method']!=='POST'||in_array('csrf',$rota['middleware'],true)),'Rota válida com autenticação e CSRF: '.$rota['uri']);}
    verificar(Router::route('admin.users.edit',['id'=>7])==='/admin/utilizadores/editar/7','URL de edição resolve ID');
    $_SERVER['REQUEST_METHOD']='POST';$_POST=['_csrf'=>'errado'];verificar(!CSRF::validateFromRequest(),'CSRF rejeita token inválido');$_POST=['_csrf'=>CSRF::token()];verificar(CSRF::validateFromRequest(),'CSRF aceita token da sessão');
    foreach(['utilizador_criado','utilizador_aprovado']as$template){$html=\App\Core\EmailTemplate::render($template,['nome'=>'<script>teste</script>','link'=>'https://example.test/reset-password/token/teste']);verificar(str_contains($html,'https://example.test/reset-password/token/teste')&&!str_contains($html,'<script>teste</script>'),'Email renderiza ligação e escapa nome: '.$template);}
    $_SESSION['user_id']=1;$_POST=dados('Inválido','invalido@example.test');
    executar($c,'editarSubmit',['id-invalido']);verificar(http_response_code()===422,'ID inválido na edição não causa erro fatal no formulário');
    $_POST=dados('Aviso','aviso@example.test');$c->service=$falhaEmail;executar($c,'criarSubmit');
    verificar(str_contains(\App\Core\Sessao::peek('aviso'),'foi guardada'),'Controlador comunica gravação mesmo quando email falha');
    $loader=new \Twig\Loader\FilesystemLoader();$loader->addPath(dirname(__DIR__).'/App/Views/admin','admin');$twig=new \Twig\Environment($loader,['strict_variables'=>true]);
    $aviso=$twig->render('@admin/flash.twig',['sessao'=>new \App\Core\Sessao()]);verificar(str_contains($aviso,'alert-warning')&&str_contains($aviso,'notificação por email falhou'),'Aviso de email é apresentado pelo layout existente');$c->service=$service;
    $service->alterar('editar',2,array_replace(dados('Pessoa 2','u2@example.test',5),['password'=>'','password_confirmation'=>'']),1);
    rejeita(fn()=>$service->alterar('editar',1,dados('Admin','u1@example.test',5),1),DomainException::class,'Último Administrador não perde o acesso');verificar($service->obter(1)['perfil_id']===1&&$service->obter(1)['ativo']===1,'Mantém Administrador ativo');
}finally{$instancia->setValue(null,$anterior);$_SESSION=[];session_destroy();}
echo "Verificações: {$checks}; falhas: ".count($falhas).PHP_EOL;foreach($falhas as$falha){echo 'FALHA: '.$falha.PHP_EOL;}exit($falhas===[]?0:1);
