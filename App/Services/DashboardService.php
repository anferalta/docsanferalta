<?php
namespace App\Services;

use App\Core\Conexao;
use App\Models\Utilizador;
use DateTimeImmutable;
use DomainException;
use PDO;

class DashboardService
{
    private PDO $db;
    public function __construct(?PDO $db=null){$this->db=$db??Conexao::getInstancia();}

    public function utilizadores():array
    {
        $row=$this->db->query("SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN ativo=1 THEN 1 ELSE 0 END),0) AS ativos,
            COALESCE(SUM(CASE WHEN ativo=0 AND aprovado_em IS NULL THEN 1 ELSE 0 END),0) AS pendentes,
            COALESCE(SUM(CASE WHEN ativo=0 AND aprovado_em IS NOT NULL THEN 1 ELSE 0 END),0) AS bloqueados
            FROM utilizadores")->fetch(PDO::FETCH_ASSOC);
        return array_map('intval',$row);
    }

    public function documentos(?int $autor=null):array
    {
        $sql='SELECT estado_atual,COUNT(*) AS total FROM documentos';$params=[];
        if($autor!==null){$sql.=' WHERE criado_por=?';$params[]=$autor;}
        $stmt=$this->db->prepare($sql.' GROUP BY estado_atual');$stmt->execute($params);
        $dados=['total'=>0,'novo'=>0,'pendente'=>0,'analise'=>0,'em_tramitacao'=>0,'concluido'=>0,'arquivado'=>0,'devolvido'=>0,'fluxo'=>0];
        foreach($stmt->fetchAll(PDO::FETCH_ASSOC)as$row){$total=(int)$row['total'];$dados['total']+=$total;if(array_key_exists($row['estado_atual'],$dados)&&!in_array($row['estado_atual'],['total','fluxo'],true)){$dados[$row['estado_atual']]=$total;}}
        foreach(['novo','pendente','analise','em_tramitacao','concluido']as$estado){$dados['fluxo']+=$dados[$estado];}
        return $dados;
    }

    public function catalogo(string $nome):int
    {
        $tabelas=['tipos'=>'documento_tipos','areas'=>'documento_areas','estados'=>'documento_estados'];
        if(!isset($tabelas[$nome])){throw new \InvalidArgumentException('Catálogo inválido.');}
        return (int)$this->db->query('SELECT COUNT(*) FROM '.$tabelas[$nome])->fetchColumn();
    }

    public function carregar(Utilizador $user,?DateTimeImmutable $agora=null):array
    {
        if(!$user->id||$user->ativo!==1){throw new DomainException('É necessária uma conta ativa.');}
        $agora??=new DateTimeImmutable();$admin=$user->isAdmin();
        $dados=['isAdmin'=>$admin,'geradoEm'=>$agora,'documentos'=>$this->documentos($admin?null:$user->id)];
        // Mantém o resumo pessoal dos utilizadores que não são Administradores.
        if(!$admin){return $dados;}
        $dados['utilizadores']=$this->utilizadores();
        $sla=['ok'=>0,'alerta'=>0,'atrasado'=>0,'indefinido'=>0];$ranking=[];$calculo=new RelatorioSLAService($this->db);
        $stmt=$this->db->query("SELECT d.area_atual_desde,a.prazo_resposta,a.id AS area_id,a.nome AS area_nome
            FROM documentos d LEFT JOIN documento_areas a ON a.id=d.area_atual_id
            WHERE d.estado_atual IN ('novo','pendente','analise','em_tramitacao','concluido')");
        while($doc=$stmt->fetch(PDO::FETCH_ASSOC)){
            $doc=$calculo->calcularDocumento($doc,$agora);$classe=$doc['sla'];$sla[$classe]++;
            $chave=$doc['area_id']??'sem_area';
            if(!isset($ranking[$chave])){$ranking[$chave]=['area'=>$doc['area_nome']??'Sem área','total'=>0,'ok'=>0,'alerta'=>0,'atrasado'=>0,'indefinido'=>0];}
            $ranking[$chave]['total']++;$ranking[$chave][$classe]++;
        }
        $ranking=array_values($ranking);usort($ranking,static fn($a,$b)=>($b['atrasado']<=>$a['atrasado'])?:($b['alerta']<=>$a['alerta'])?:strcmp($a['area'],$b['area']));
        $dados['sla']=$sla;$dados['rankingAreas']=$ranking;
        $dados['ultimosDocs']=$this->db->query('SELECT id,titulo,criado_em FROM documentos ORDER BY criado_em DESC,id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $dados['ultimosUsers']=$this->db->query('SELECT id,nome,email,criado_em FROM utilizadores ORDER BY criado_em DESC,id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $dados['ultimosLogs']=$this->db->query('SELECT id,acao,criado_em FROM auditoria ORDER BY criado_em DESC,id DESC LIMIT 10')->fetchAll(PDO::FETCH_ASSOC);
        $inicio=$agora->modify('first day of this month')->setTime(0,0)->modify('-11 months');$fim=$agora->modify('first day of next month')->setTime(0,0);
        $stmt=$this->db->prepare('SELECT SUBSTR(criado_em,1,7) AS mes,COUNT(*) AS total FROM utilizadores WHERE criado_em>=? AND criado_em<? GROUP BY SUBSTR(criado_em,1,7) ORDER BY mes');$stmt->execute([$inicio->format('Y-m-d H:i:s'),$fim->format('Y-m-d H:i:s')]);
        $map=array_column($stmt->fetchAll(PDO::FETCH_ASSOC),'total','mes');$meses=[];
        for($i=0;$i<12;$i++){$data=$inicio->modify("+{$i} months");$meses[]=['mes'=>$data->format('m/Y'),'total'=>(int)($map[$data->format('Y-m')]??0)];}
        $dados['meses']=$meses;$dados['maxRegistos']=max([1,...array_column($meses,'total')]);
        return $dados;
    }
}
