<?php
namespace App\Services;

use App\Core\Conexao;
use App\Models\Auditoria;
use App\Models\Utilizador;
use Closure;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

class UtilizadoresService
{
    private PDO $db;
    private Closure $notificar;
    public const PERMISSOES = ['criar'=>'admin.utilizadores.criar','editar'=>'admin.utilizadores.editar','aprovar'=>'admin.utilizadores.aprovar','bloquear'=>'admin.utilizadores.editar','desbloquear'=>'admin.utilizadores.editar','eliminar'=>'admin.utilizadores.apagar','rejeitar'=>'admin.utilizadores.apagar'];

    public function __construct(?Closure $notificar = null)
    {
        $this->db = Conexao::getInstancia();
        $this->notificar = $notificar ?? static function(string $acao, array $dados): bool {
            $user = new Utilizador($dados);
            if (in_array($acao,['criar','aprovar'],true)) {
                return UserEmailService::enviarLinkPassword($user,$acao === 'criar' ? 'utilizador_criado' : 'utilizador_aprovado',$acao === 'criar' ? 'A sua conta foi criada' : 'A sua conta foi aprovada');
            }
            $template = ['bloquear'=>'conta_bloqueada','desbloquear'=>'conta_desbloqueada','rejeitar'=>'utilizador_rejeitado'][$acao];
            return EmailService::enviar($dados['email'],'Estado da sua conta',$template,['nome'=>$dados['nome']]);
        };
    }

    public function id(mixed $id): int
    {
        if ((!is_int($id) && !is_string($id)) || !preg_match('/\A[1-9][0-9]*\z/',(string)$id) || filter_var($id,FILTER_VALIDATE_INT) === false) { throw new InvalidArgumentException('Identificador inválido.'); }
        return (int)$id;
    }

    public function filtros(array $entrada): array
    {
        $dados=[];
        foreach(['nome'=>100,'email'=>120,'ativo'=>1] as $campo=>$limite) {
            $valor=$entrada[$campo]??'';
            if (!is_string($valor) || !mb_check_encoding($valor,'UTF-8') || str_contains($valor,"\0")) { throw new InvalidArgumentException('Os filtros devem conter texto válido.'); }
            $valor=trim($valor);
            if(mb_strlen($valor,'UTF-8')>$limite){throw new InvalidArgumentException('O filtro excede o tamanho permitido.');}
            $dados[$campo]=$valor;
        }
        if(!in_array($dados['ativo'],['','0','1'],true)){throw new InvalidArgumentException('Estado inválido.');}
        $pagina=$entrada['page']??1;
        if(!is_string($pagina)&&!is_int($pagina)){throw new InvalidArgumentException('Página inválida.');}
        return ['filtros'=>$dados,'pagina'=>filter_var($pagina,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]])?:1];
    }

    private function condicao(array $filtros,string $grupo):array
    {
        if(!in_array($grupo,['todos','ativos','pendentes','bloqueados'],true)){throw new InvalidArgumentException('Lista de utilizadores inválida.');}
        $where=' WHERE 1=1';$params=[];
        foreach(['nome','email'] as $campo){if($filtros[$campo]!==''){$where.=" AND u.{$campo} LIKE ?";$params[]='%'.$filtros[$campo].'%';}}
        if($grupo==='todos'&&$filtros['ativo']!==''){$where.=' AND u.ativo = ?';$params[]=(int)$filtros['ativo'];}
        elseif($grupo==='ativos'){$where.=' AND u.ativo = 1';}
        elseif($grupo==='pendentes'){$where.=' AND u.ativo = 0 AND u.aprovado_em IS NULL';}
        elseif($grupo==='bloqueados'){$where.=' AND u.ativo = 0 AND u.aprovado_em IS NOT NULL';}
        return [$where,$params];
    }

    public function listar(array $entrada=[],string $grupo='todos'):array
    {
        $opcoes=$this->filtros($entrada);[$where,$params]=$this->condicao($opcoes['filtros'],$grupo);
        $stmt=$this->db->prepare('SELECT COUNT(*) FROM utilizadores u'.$where);$stmt->execute($params);$total=(int)$stmt->fetchColumn();
        $paginas=max(1,(int)ceil($total/10));$pagina=min($opcoes['pagina'],$paginas);$offset=($pagina-1)*10;
        $stmt=$this->db->prepare('SELECT u.id,u.nome,u.email,u.perfil_id,u.ativo,u.aprovado_em,u.aprovado_por,u.criado_em,p.nome AS perfil_nome FROM utilizadores u LEFT JOIN perfis p ON p.id=u.perfil_id'.$where." ORDER BY u.id DESC LIMIT 10 OFFSET {$offset}");$stmt->execute($params);
        return array_replace($opcoes,['utilizadores'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'total_paginas'=>$paginas,'pagina'=>$pagina,'grupo'=>$grupo]);
    }

    public function obter(mixed $id):array
    {
        $stmt=$this->db->prepare('SELECT * FROM utilizadores WHERE id=?');$stmt->execute([$this->id($id)]);$user=$stmt->fetch(PDO::FETCH_ASSOC);
        if(!$user){throw new DomainException('Utilizador não encontrado.',404);}return $user;
    }

    public function autor(int $id,string $permissao):array
    {
        $user=$this->obter($id);
        if((int)$user['ativo']!==1){throw new DomainException('A conta não está ativa.');}
        if((int)$user['perfil_id']!==1){
            $stmt=$this->db->prepare('SELECT COUNT(*) FROM perfis_permissoes pp JOIN permissoes p ON p.id=pp.permissao_id WHERE pp.perfil_id=? AND p.codigo=?');$stmt->execute([$user['perfil_id'],$permissao]);
            if(!(int)$stmt->fetchColumn()){throw new DomainException('Não tem permissão para esta alteração.');}
        }
        unset($user['password']);return $user;
    }

    public function perfis(int $autor):array
    {
        $user=$this->obter($autor);$sql='SELECT id,nome FROM perfis';
        if((int)$user['perfil_id']!==1){$sql.=' WHERE id <> 1';}
        return $this->db->query($sql.' ORDER BY nome,id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function editavel(mixed $id,int $autor):array
    {
        $user=$this->obter($id);$actor=$this->autor($autor,'admin.utilizadores.editar');
        if((int)$user['perfil_id']===1&&(int)$actor['perfil_id']!==1){throw new DomainException('Apenas um Administrador pode alterar uma conta de Administrador.');}
        unset($user['password']);return $user;
    }

    private function validar(array $entrada,bool $criar):array
    {
        $dados=[];
        foreach(['nome'=>100,'email'=>120] as $campo=>$max){
            $valor=$entrada[$campo]??'';
            if(!is_string($valor)||!mb_check_encoding($valor,'UTF-8')||str_contains($valor,"\0")){throw new InvalidArgumentException('Nome e email devem ser texto válido.');}
            $valor=trim($valor);if($campo==='email'){$valor=Utilizador::normalizeEmail($valor);}
            if($valor===''||mb_strlen($valor)>$max){throw new InvalidArgumentException('Preencha nome e email dentro dos limites indicados.');}$dados[$campo]=$valor;
        }
        if(!filter_var($dados['email'],FILTER_VALIDATE_EMAIL)){throw new InvalidArgumentException('Introduza um email válido.');}
        $dados['perfil_id']=$this->id($entrada['perfil_id']??null);
        $ativo=$entrada['ativo']??'1';if(!in_array($ativo,['0','1',0,1],true)){throw new InvalidArgumentException('Estado inválido.');}$dados['ativo']=(int)$ativo;
        $senha=$entrada['password']??'';$confirmacao=$entrada['password_confirmation']??'';
        if(!is_string($senha)||!is_string($confirmacao)||str_contains($senha,"\0")||!mb_check_encoding($senha,'UTF-8')){throw new InvalidArgumentException('Palavra-passe inválida.');}
        if($criar||$senha!==''||$confirmacao!==''){
            if(mb_strlen($senha)<6||strlen($senha)>72){throw new InvalidArgumentException('A palavra-passe deve ter pelo menos 6 caracteres e não exceder 72 bytes.');}
            if($senha!==$confirmacao){throw new InvalidArgumentException('As palavras-passe não coincidem.');}
            $dados['password']=password_hash($senha,PASSWORD_DEFAULT);
        }
        return $dados;
    }

    public function alterar(string $acao,mixed $id,array $entrada,int $autor):array
    {
        if(!isset(self::PERMISSOES[$acao])){throw new InvalidArgumentException('Ação inválida.');}
        $dados=in_array($acao,['criar','editar'],true)?$this->validar($entrada,$acao==='criar'):[];
        $this->db->beginTransaction();
        try{
            $actor=$this->autor($autor,self::PERMISSOES[$acao]);
            // Serializa alterações que possam remover o último Administrador ativo.
            if($this->db->getAttribute(PDO::ATTR_DRIVER_NAME)==='mysql'){$this->db->query('SELECT id FROM utilizadores WHERE perfil_id=1 AND ativo=1 ORDER BY id FOR UPDATE')->fetchAll();}
            $user=$acao==='criar'?null:$this->obter($id);
            if($user&&(int)$user['perfil_id']===1&&(int)$actor['perfil_id']!==1){throw new DomainException('Apenas um Administrador pode alterar uma conta de Administrador.');}
            if(in_array($acao,['criar','editar','aprovar'],true)){
                $perfil=$acao==='aprovar'?$this->id($entrada['perfil_id']??null):$dados['perfil_id'];
                $stmt=$this->db->prepare('SELECT id FROM perfis WHERE id=?');$stmt->execute([$perfil]);if(!$stmt->fetchColumn()){throw new InvalidArgumentException('O perfil selecionado não existe.');}
                if($perfil===1&&(int)$actor['perfil_id']!==1){throw new DomainException('Não pode atribuir o perfil Administrador.');}
            }
            if($user&&(int)$user['id']===$autor&&($acao==='bloquear'||in_array($acao,['eliminar','rejeitar'],true)||($acao==='editar'&&($dados['ativo']!==1||$dados['perfil_id']!==(int)$user['perfil_id'])))){throw new DomainException('Não pode bloquear, apagar ou retirar o seu próprio perfil de acesso.');}
            $pendente=$user&&(int)$user['ativo']===0&&$user['aprovado_em']===null;
            if(in_array($acao,['aprovar','rejeitar'],true)&&!$pendente){throw new DomainException('Esta operação só se aplica a utilizadores pendentes.');}
            if($acao==='editar'&&$pendente&&$dados['ativo']===1){throw new DomainException('Use a aprovação para ativar um utilizador pendente.');}
            if($acao==='bloquear'&&(int)$user['ativo']!==1){throw new DomainException('O utilizador já não está ativo.');}
            if($acao==='desbloquear'&&((int)$user['ativo']!==0||$user['aprovado_em']===null)){throw new DomainException('Apenas contas bloqueadas podem ser desbloqueadas.');}
            $removeAdmin=$user&&(int)$user['perfil_id']===1&&(int)$user['ativo']===1&&(in_array($acao,['bloquear','eliminar','rejeitar'],true)||($acao==='editar'&&($dados['perfil_id']!==1||$dados['ativo']!==1)));
            if($removeAdmin&&(int)$this->db->query('SELECT COUNT(*) FROM utilizadores WHERE perfil_id=1 AND ativo=1')->fetchColumn()<=1){throw new DomainException('É necessário manter pelo menos um Administrador ativo.');}
            if(in_array($acao,['criar','editar'],true)){
                $stmt=$this->db->prepare('SELECT id,email FROM utilizadores WHERE id<>?');$stmt->execute([$user['id']??0]);
                while($existente=$stmt->fetch(PDO::FETCH_ASSOC)){if(Utilizador::normalizeEmail($existente['email'])===$dados['email']){throw new InvalidArgumentException('Já existe um utilizador com este email.');}}
            }
            $agora=date('Y-m-d H:i:s');
            if($acao==='criar'){
                $stmt=$this->db->prepare('INSERT INTO utilizadores (nome,email,password,perfil_id,ativo,aprovado_por,aprovado_em) VALUES (?,?,?,?,?,?,?)');$stmt->execute([$dados['nome'],$dados['email'],$dados['password'],$dados['perfil_id'],$dados['ativo'],$autor,$agora]);$id=(int)$this->db->lastInsertId();
            }elseif($acao==='editar'){
                $sql='UPDATE utilizadores SET nome=?,email=?,perfil_id=?,ativo=?';$params=[$dados['nome'],$dados['email'],$dados['perfil_id'],$dados['ativo']];
                if(isset($dados['password'])){$sql.=',password=?';$params[]=$dados['password'];}
                $sql.=' WHERE id=?';$params[]=$user['id'];$this->db->prepare($sql)->execute($params);
                if(isset($dados['password'])||$dados['email']!==$user['email']){$this->limparTokens($user['email']);$this->limparTokens($dados['email']);}
            }elseif($acao==='aprovar'){
                $this->db->prepare('UPDATE utilizadores SET ativo=1,perfil_id=?,aprovado_por=?,aprovado_em=? WHERE id=?')->execute([$perfil,$autor,$agora,$user['id']]);
            }elseif(in_array($acao,['bloquear','desbloquear'],true)){
                $sql=$acao==='bloquear'?'UPDATE utilizadores SET ativo=0,aprovado_em=COALESCE(aprovado_em,?),aprovado_por=COALESCE(aprovado_por,?) WHERE id=?':'UPDATE utilizadores SET ativo=1,tentativas_falhadas=0 WHERE id=?';
                $this->db->prepare($sql)->execute($acao==='bloquear'?[$agora,$autor,$user['id']]:[$user['id']]);
                if($acao==='bloquear'){$this->limparTokens($user['email']);}
            }else{
                $snapshot=$user;unset($snapshot['password']);
                $this->db->prepare('INSERT INTO auditoria_eliminacoes (tabela,registo_id,dados,apagado_por,ip) VALUES (?,?,?,?,?)')->execute(['utilizadores',$user['id'],json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),$autor,$_SERVER['REMOTE_ADDR']??null]);
                $this->db->prepare('DELETE FROM utilizadores_permissoes WHERE utilizador_id=?')->execute([$user['id']]);$this->limparTokens($user['email']);
                $this->db->prepare('DELETE FROM utilizadores WHERE id=?')->execute([$user['id']]);
            }
            $id=$user['id']??$id;
            Auditoria::registar('Gestão de utilizadores: '.$acao.' ID '.$id,$autor);
            $resultado=in_array($acao,['eliminar','rejeitar'],true)?$user:$this->obter($id);unset($resultado['password']);
            $this->db->commit();
        }catch(Throwable $e){if($this->db->inTransaction()){$this->db->rollBack();}throw $e;}
        $notificado=true;
        if(in_array($acao,['criar','aprovar','bloquear','desbloquear','rejeitar'],true)){
            try{$notificado=(bool)($this->notificar)($acao,$resultado);}catch(Throwable $e){$notificado=false;}
        }
        return ['utilizador'=>$resultado,'notificado'=>$notificado];
    }

    private function limparTokens(string $email):void
    {
        $this->db->prepare('DELETE FROM password_resets WHERE email=? OR email=?')->execute([$email,Utilizador::normalizeEmail($email)]);
    }

    public function exportar(array $entrada=[],string $grupo='todos'):\Generator
    {
        $opcoes=$this->filtros($entrada);[$where,$params]=$this->condicao($opcoes['filtros'],$grupo);
        $stmt=$this->db->prepare('SELECT MAX(u.id) FROM utilizadores u'.$where);$stmt->execute($params);$max=(int)$stmt->fetchColumn();
        return (function()use($where,$params,$max){$ultimo=null;do{
            $stmt=$this->db->prepare('SELECT u.id,u.nome,u.email,u.ativo,u.criado_em FROM utilizadores u'.$where.($ultimo===null?' AND u.id <= ?':' AND u.id < ?').' ORDER BY u.id DESC LIMIT 500');$stmt->execute([...$params,$ultimo??$max]);$lote=$stmt->fetchAll(PDO::FETCH_ASSOC);
            foreach($lote as $row){$ultimo=(int)$row['id'];yield $row;}
        }while(count($lote)===500);})();
    }

    public static function escreverCSV($stream,iterable $rows):void
    {
        fwrite($stream,"\xEF\xBB\xBF");fputcsv($stream,['ID','Nome','Email','Ativo','Criado em'],',','"','');
        foreach($rows as $row){$valores=[];foreach(['id','nome','email','ativo','criado_em']as$campo){$valor=(string)($row[$campo]??'');if(preg_match('/\A(?:[\x00-\x20]*[=+\-@]|[\t\r\n])/', $valor)){$valor="'".$valor;}$valores[]=$valor;}fputcsv($stream,$valores,',','"','');}
    }
}
