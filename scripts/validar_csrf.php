<?php
// php scripts/validar_csrf.php — apenas sessão temporária deste processo.
require dirname(__DIR__).'/vendor/autoload.php';
use App\Core\CSRF;use App\Core\Sessao;
error_reporting(E_ALL);set_error_handler(static function($s,$m,$f,$l){throw new ErrorException($m,0,$s,$f,$l);});session_start();
$checks=0;$falhas=[];function verificar(bool $ok,string $nome):void{global$checks,$falhas;$checks++;if(!$ok){$falhas[]=$nome;}}
try{
    $_SERVER['REQUEST_METHOD']='POST';$token=CSRF::token();
    verificar(Sessao::csrfToken()===$token,'Sessao e CSRF usam o mesmo token');
    verificar(Sessao::validarCsrf($token),'Helper de sessão valida o token canónico');
    foreach(['_csrf','csrf']as$campo){$_POST=[$campo=>$token];verificar(CSRF::validateFromRequest(),'Aceita campo compatível: '.$campo);}
    $_POST=[];$_SERVER['HTTP_X_CSRF_TOKEN']=$token;verificar(CSRF::validateFromRequest(),'Aceita cabeçalho de pedidos AJAX');unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    foreach([null,'',[],['x'],true,123,'errado']as$valor){$_POST=['_csrf'=>$valor];verificar(!CSRF::validateFromRequest(),'Recusa token inválido sem erro: '.gettype($valor));}
    $_POST=['_csrf'=>'errado','csrf'=>$token];verificar(!CSRF::validateFromRequest(),'Não contorna token canónico inválido com campo alternativo');
    $_POST=['_csrf'=>$token];$_SESSION['_csrf']=[];verificar(!CSRF::validateFromRequest(),'Recusa token de sessão malformado');$_SESSION['_csrf']=$token;
    $novo=CSRF::regenerate();verificar($novo!==$token,'Regeneração muda o token');$_POST=['_csrf'=>$token];verificar(!CSRF::validateFromRequest(),'Token antigo deixa de ser aceite');$_POST=['_csrf'=>$novo];verificar(CSRF::validateFromRequest(),'Token novo é aceite');
    $_SERVER['REQUEST_METHOD']='GET';$_POST=[];verificar(CSRF::validateFromRequest(),'GET de navegação não exige token');
    $_SERVER['REQUEST_METHOD']='POST';$_SESSION['user_id']=1;$_SESSION['_last_activity']=time()-3600;$antigo=CSRF::token();Sessao::start();$_POST=['_csrf'=>$antigo];verificar(!CSRF::validateFromRequest()&&!isset($_SESSION['user_id']),'Sessão expirada não valida formulário antigo');
}finally{$_SESSION=[];session_destroy();}
echo "Verificações CSRF: {$checks}; falhas: ".count($falhas).PHP_EOL;foreach($falhas as$falha){echo 'FALHA: '.$falha.PHP_EOL;}exit($falhas===[]?0:1);
