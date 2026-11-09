<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BaseController;
use App\Core\Sessao;
use App\Services\UtilizadoresService;
use DomainException;
use InvalidArgumentException;
use PDOException;

class UtilizadoresAdminController extends BaseController
{
    protected function servico():UtilizadoresService{return new UtilizadoresService();}
    public function index(){$this->listagem('todos');}
    public function ativos(){$this->listagem('ativos');}
    public function pendentes(){$this->listagem('pendentes');}
    public function bloqueados(){$this->listagem('bloqueados');}

    private function listagem(string $grupo):void
    {
        $this->authorize('admin.utilizadores.ver');
        try{$dados=$this->servico()->listar($_GET,$grupo);}catch(InvalidArgumentException $e){$this->erro($e->getMessage(),422);return;}
        $actor=Auth::user();
        $this->render('@admin/utilizadores/'.($grupo==='todos'?'index':$grupo).'.twig',$dados+['perfis'=>$this->servico()->perfis($actor->id),'actorId'=>$actor->id,'actorAdmin'=>$actor->isAdmin()]);
    }

    public function criar()
    {
        $this->authorize('admin.utilizadores.criar');$this->formulario();
    }
    public function editar($id)
    {
        $this->authorize('admin.utilizadores.editar');
        try{$user=$this->servico()->editavel($id,Auth::id());}catch(InvalidArgumentException $e){$this->erro($e->getMessage(),422);return;}catch(DomainException $e){$this->erro($e->getMessage(),$e->getCode()===404?404:403);return;}
        $this->formulario($user);
    }
    public function criarSubmit(){$this->operacao('criar');}
    public function editarSubmit($id){$this->operacao('editar',$id);}
    public function aprovar($id){$this->operacao('aprovar',$id);}
    public function rejeitar($id){$this->operacao('rejeitar',$id);}
    public function eliminar($id){$this->operacao('eliminar',$id);}
    public function bloquear($id){$this->operacao('bloquear',$id);}
    public function desbloquear($id){$this->operacao('desbloquear',$id);}
    public function reativar($id){$this->desbloquear($id);}

    private function operacao(string $acao,mixed $id=null):void
    {
        $this->authorize(UtilizadoresService::PERMISSOES[$acao]);
        $autor=Auth::id();if(!$autor){$this->acessoNegado();return;}
        try{$resultado=$this->servico()->alterar($acao,$id,$_POST,$autor);}
        catch(InvalidArgumentException $e){
            if(in_array($acao,['criar','editar'],true)){
                try{$user=$acao==='editar'?$this->servico()->editavel($id,$autor):null;}catch(DomainException|InvalidArgumentException $falha){$codigo=$falha instanceof InvalidArgumentException?422:($falha->getCode()===404?404:403);$this->erro($falha->getMessage(),$codigo);return;}
                http_response_code(422);$this->formulario($user,$e->getMessage());
            }else{$this->erro($e->getMessage(),422);}return;
        }
        catch(DomainException $e){$this->erro($e->getMessage(),$e->getCode()===404?404:409);return;}
        catch(PDOException $e){$this->erro('Não foi possível concluir a alteração. Tente novamente ou contacte o administrador.',500);return;}
        Sessao::flash('sucesso','Operação concluída com sucesso.');
        if(!$resultado['notificado']){Sessao::flash('aviso','A alteração foi guardada, mas a notificação por email falhou.');}
        $this->redirect(in_array($acao,['aprovar','rejeitar'],true)?'/admin/utilizadores/pendentes':'/admin/utilizadores');
    }

    private function formulario(?array $user=null,?string $erro=null):void
    {
        $dados=$user??['nome'=>'','email'=>'','perfil_id'=>'','ativo'=>1];
        if($erro!==null){foreach(['nome','email','perfil_id','ativo']as$campo){$dados[$campo]=is_string($_POST[$campo]??null)?$_POST[$campo]:'';}}
        unset($dados['password']);
        $actor=Auth::user();
        $this->render($user===null?'@admin/utilizadores/criar.twig':'@admin/utilizadores/editar.twig',['utilizador'=>$user,'dados'=>$dados,'erro'=>$erro,'perfis'=>$this->servico()->perfis($actor->id),'actorId'=>$actor->id]);
    }

    public function exportarCSV()
    {
        $this->authorize('admin.utilizadores.ver');
        $grupo=$_GET['grupo']??'todos';if(!is_string($grupo)){$this->erro('Lista inválida.',422);return;}
        try{$rows=$this->servico()->exportar($_GET,$grupo);}catch(InvalidArgumentException $e){$this->erro($e->getMessage(),422);return;}
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="utilizadores.csv"');header('Cache-Control: private, no-store');
        $stream=fopen('php://output','wb');try{UtilizadoresService::escreverCSV($stream,$rows);}finally{fclose($stream);}
    }
    private function erro(string $mensagem,int $codigo):void{http_response_code($codigo);$this->render('@admin/utilizadores/erro.twig',['mensagem'=>$mensagem]);}
}
