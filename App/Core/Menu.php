<?php
namespace App\Core;

use App\Services\DashboardService;

class Menu
{
    public function getMenu():array
    {
        return [
            // ============================
            // GERAL
            // ============================
            ['header' => 'GERAL'],
            [
                'titulo' => 'Dashboard',
                'icone' => 'bi-speedometer2',
                'url' => '/admin/dashboard',
                'permissao' => 'admin.dashboard.ver',
                'principal' => true
            ],
            // ============================
            // UTILIZADORES
            // ============================
            ['header' => 'UTILIZADORES'],
            [
                'titulo' => 'Utilizadores',
                'icone' => 'bi-people',
                'url' => '/admin/utilizadores',
                'permissao' => 'admin.utilizadores.ver',
                'contador' => 'users.total',
                'principal' => true
            ],
            [
                'titulo' => 'Pendentes',
                'icone' => 'bi-hourglass-split',
                'url' => '/admin/utilizadores/pendentes',
                'permissao' => 'admin.utilizadores.ver',
                'contador' => 'users.pendentes'
            ],
            [
                'titulo' => 'Ativos',
                'icone' => 'bi-person-check',
                'url' => '/admin/utilizadores/ativos',
                'permissao' => 'admin.utilizadores.ver',
                'contador' => 'users.ativos'
            ],
            [
                'titulo' => 'Bloqueados',
                'icone' => 'bi-person-x',
                'url' => '/admin/utilizadores/bloqueados',
                'permissao' => 'admin.utilizadores.ver',
                'contador' => 'users.bloqueados'
            ],
            [
                'titulo' => 'Criar Utilizador',
                'icone' => 'bi-person-plus',
                'url' => '/admin/utilizadores/criar',
                'permissao' => 'admin.utilizadores.criar'
            ],
            // ============================
            // DOCUMENTOS
            // ============================
            ['header' => 'GESTÃO DE DOCUMENTOS'],
            [
                'titulo' => 'Documentos',
                'icone' => 'bi-folder',
                'url' => '/admin/documentos',
                'permissao' => 'admin.documentos.ver',
                'contador' => 'docs.total',
                'principal' => true
            ],
            [
                'titulo' => 'Tipo de Documentos',
                'icone' => 'bi-diagram-2',
                'url' => '/admin/documento-tipos',
                'permissao' => 'admin.documento-tipos.ver',
                'contador' => 'catalogo.tipos'
            ],
            [
                'titulo' => 'Arquivados',
                'icone' => 'bi-archive',
                'url' => '/admin/documentos/arquivados',
                'permissao' => 'admin.documentos.arquivados.ver',
                'contador' => 'docs.arquivado'
            ],
            // ============================
            // TRAMITAÇÃO
            // ============================
            ['header' => 'TRAMITAÇÃO'],
            [
                'titulo' => 'Dashboard de Tramitação',
                'icone' => 'bi-graph-up',
                'url' => '/admin/tramitacao/dashboard',
                'permissao' => 'admin.tramitacao.dashboard'
            ],
            [
                'titulo' => 'Tramitação',
                'icone' => 'bi-diagram-3',
                'url' => '/admin/tramitacao',
                'permissao' => 'admin.tramitacao.ver',
                'contador' => 'docs.fluxo',
                'principal' => true
            ],
            [
                'titulo' => 'Áreas de Tramitação',
                'icone' => 'bi-diagram-2',
                'url' => '/admin/documento-areas',
                'permissao' => 'admin.tramitacao.areas.ver',
                'contador' => 'catalogo.areas'
            ],
            // ⭐ NOVO ITEM — DOCUMENTO ESTADOS
            [
                'titulo' => 'Estado dos Documentos',
                'icone' => 'bi-flag',
                'url' => '/admin/documento-estados',
                'permissao' => 'admin.tramitacao.estados.ver',
                'contador' => 'catalogo.estados'
            ],
            // ============================
            // SISTEMA
            // ============================
            ['header' => 'SISTEMA'],
            [
                'titulo' => 'Relatórios SLA',
                'icone' => 'bi-file-earmark-bar-graph',
                'url' => '/admin/relatorios',
                'permissao' => 'admin.relatorios.ver'
            ],
            [
                'titulo' => 'Perfis',
                'icone' => 'bi-person-badge',
                'url' => '/admin/perfis',
                'permissao' => 'admin.perfis.ver'
            ],
            [
                'titulo' => 'Permissões',
                'icone' => 'bi-shield-lock',
                'url' => '/admin/permissoes',
                'permissao' => 'admin.permissoes.ver'
            ],
            [
                'titulo' => 'Auditoria',
                'icone' => 'bi-search',
                'url' => '/admin/auditoria',
                'permissao' => 'admin.auditoria.ver'
            ],
            [
                'titulo' => 'Log Sistema',
                'icone' => 'bi-terminal',
                'url' => '/admin/logs',
                'permissao' => 'admin.logs.ver'
            ],
            [
                'titulo' => 'Backups',
                'icone' => 'bi-hdd-stack',
                'url' => '/admin/backups',
                'permissoes' => ['admin.backups.bd.ver','admin.backups.files.ver','admin.backups.agendamentos.ver']
            ],
        ];
    }

    public function filtrarMenu(array $menu):array
    {
        $user=Auth::user();if(!$user){return [];}
        $permissoes=$user->isAdmin()?[]:$user->permissoes();
        $permitido=static function(array $item)use($user,$permissoes):bool{
            if($user->isAdmin()){return true;}
            $necessarias=$item['permissoes']??(isset($item['permissao'])?[$item['permissao']]:[]);
            return (bool)array_intersect($necessarias,$permissoes);
        };
        $filtrar=function(array $itens)use(&$filtrar,$permitido):array{
            $resultado=[];$cabecalho=null;
            foreach($itens as$item){
                if(isset($item['header'])){$cabecalho=$item;continue;}
                if(!$permitido($item)){continue;}
                if(isset($item['sub'])){$item['sub']=$filtrar($item['sub']);}
                if($cabecalho!==null){$resultado[]=$cabecalho;$cabecalho=null;}
                $resultado[]=$item;
            }
            return $resultado;
        };
        $resultado=$filtrar($menu);$service=new DashboardService();$cache=[];
        $perfil=strtolower(trim($user->perfil->nome??''));
        $todos=$user->isAdmin()||in_array($perfil,['admin','gestor','supervisor'],true)||in_array('admin.documentos.ver_todos',$permissoes,true);
        $contar=function(array $itens)use(&$contar,&$cache,$service,$user,$todos):array{
            foreach($itens as&$item){
                if(isset($item['contador'])){
                    [$grupo,$chave]=explode('.',$item['contador'],2);
                    if($grupo==='users'){$cache['users']??=$service->utilizadores();$item['badge']=$cache['users'][$chave];}
                    elseif($grupo==='docs'){$cache['docs']??=$service->documentos($todos?null:$user->id);$item['badge']=$cache['docs'][$chave];}
                    else{$cache['catalogo'][$chave]??=$service->catalogo($chave);$item['badge']=$cache['catalogo'][$chave];}
                }
                if(isset($item['sub'])){$item['sub']=$contar($item['sub']);}
            }
            unset($item);return $itens;
        };
        return $this->marcarAtivo($contar($resultado),$_SERVER['REQUEST_URI']??'/');
    }

    public function marcarAtivo(array $menu,string $uri):array
    {
        $path=rtrim(parse_url($uri,PHP_URL_PATH)?:'/','/')?:'/';if($path==='/admin'){$path='/admin/dashboard';}
        $melhor=null;$tamanho=-1;
        $procurar=function(array $itens)use(&$procurar,$path,&$melhor,&$tamanho):void{
            foreach($itens as$item){if(isset($item['url'])){$url=rtrim($item['url'],'/');if(($path===$url||str_starts_with($path,$url.'/'))&&strlen($url)>$tamanho){$melhor=$item['url'];$tamanho=strlen($url);}}if(isset($item['sub'])){$procurar($item['sub']);}}
        };$procurar($menu);
        $marcar=function(array $itens)use(&$marcar,$melhor):array{
            foreach($itens as&$item){if(isset($item['url'])){$item['ativo']=$item['url']===$melhor;}if(isset($item['sub'])){$item['sub']=$marcar($item['sub']);$item['aberto']=(bool)array_filter($item['sub'],static fn($sub)=>$sub['ativo']??false);}}unset($item);return $itens;
        };return $marcar($menu);
    }
}
