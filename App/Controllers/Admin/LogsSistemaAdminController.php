<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Services\LogsSistemaConsultaService;
use InvalidArgumentException;

class LogsSistemaAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.logs.ver');
        try { $dados = (new LogsSistemaConsultaService())->listar($_GET); }
        catch (InvalidArgumentException $e) { $this->erroFiltros($e); return; }
        $this->render('@admin/logs/index.twig',$dados);
    }

    public function exportar()
    {
        $this->authorize('admin.logs.exportar');
        try { $logs = (new LogsSistemaConsultaService())->exportar($_GET); }
        catch (InvalidArgumentException $e) { $this->erroFiltros($e); return; }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="logs_sistema.csv"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output','wb');
        try { LogsSistemaConsultaService::escreverCSV($stream,$logs); }
        finally { fclose($stream); }
    }

    public function detalhes($id)
    {
        $this->authorize('admin.logs.ver');
        $service = new LogsSistemaConsultaService();
        try { $log = $service->obter($id); $opcoes = $service->normalizar($_GET); }
        catch (InvalidArgumentException $e) { $this->erroFiltros($e); return; }
        if ($log === null) {
            http_response_code(404);
            $this->render('@admin/logs/erro.twig',['mensagem'=>'Registo de log não encontrado.']); return;
        }
        $this->render('@admin/logs/detalhes.twig',['log'=>$log,'filtros'=>$opcoes['filtros'],'pagina'=>$opcoes['pagina']]);
    }

    private function erroFiltros(InvalidArgumentException $erro): void
    {
        http_response_code(422);
        $this->render('@admin/logs/erro.twig',['mensagem'=>$erro->getMessage()]);
    }
}
