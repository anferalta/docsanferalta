<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Services\AuditoriaConsultaService;
use InvalidArgumentException;

class AuditoriaAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.auditoria.ver');
        try {
            $dados = (new AuditoriaConsultaService())->listar($_GET);
        } catch (InvalidArgumentException $e) {
            $this->erroFiltros($e);
            return;
        }
        $this->render('@admin/auditoria/index.twig', $dados);
    }

    public function detalhes($id)
    {
        $this->authorize('admin.auditoria.ver');
        try {
            $log = (new AuditoriaConsultaService())->obter($id, $_GET['origem'] ?? '');
        } catch (InvalidArgumentException $e) {
            $this->erroFiltros($e);
            return;
        }
        if ($log === null) {
            http_response_code(404);
            $this->render('@admin/auditoria/erro.twig', ['mensagem' => 'Registo de auditoria não encontrado.']);
            return;
        }
        $this->render('@admin/auditoria/detalhes.twig', ['log' => $log]);
    }

    public function exportar()
    {
        $this->authorize('admin.auditoria.ver');
        try {
            $registos = (new AuditoriaConsultaService())->exportar($_GET);
        } catch (InvalidArgumentException $e) {
            $this->erroFiltros($e);
            return;
        }
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="auditoria.csv"');
        header('Cache-Control: private, no-store');
        $stream = fopen('php://output', 'wb');
        try {
            AuditoriaConsultaService::escreverCSV($stream, $registos);
        } finally {
            fclose($stream);
            $registos->closeCursor();
        }
    }

    public function dashboardAuditoria()
    {
        $this->authorize('admin.auditoria.ver');
        try {
            $dados = (new AuditoriaConsultaService())->dashboard($_GET);
        } catch (InvalidArgumentException $e) {
            $this->erroFiltros($e);
            return;
        }
        $this->render('@admin/auditoria/dashboard.twig', $dados);
    }

    private function erroFiltros(InvalidArgumentException $erro): void
    {
        http_response_code(422);
        $this->render('@admin/auditoria/erro.twig', ['mensagem' => $erro->getMessage()]);
    }
}
