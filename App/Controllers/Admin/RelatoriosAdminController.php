<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Services\RelatorioSLAExportService;
use App\Services\RelatorioSLAService;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RelatoriosAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.relatorios.ver');
        $relatorio = $this->carregarRelatorio();
        if ($relatorio === null) {
            return;
        }
        $areas = Conexao::getInstancia()->query('SELECT nome FROM documento_areas ORDER BY nome')
            ->fetchAll(\PDO::FETCH_COLUMN);
        $this->render('@admin/relatorios/index.twig', $relatorio + [
            'areas' => $areas,
            'estados' => RelatorioSLAService::ESTADOS,
        ]);
    }

    public function exportarPDF()
    {
        $this->authorize('admin.relatorios.ver');
        $relatorio = $this->carregarRelatorio();
        if ($relatorio === null) {
            return;
        }
        $pdf = (new RelatorioSLAExportService())->pdf($relatorio);
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="relatorio_sla.pdf"');
        header('Cache-Control: private, no-store');
        echo $pdf;
    }

    public function exportarExcel()
    {
        $this->authorize('admin.relatorios.ver');
        $relatorio = $this->carregarRelatorio();
        if ($relatorio === null) {
            return;
        }
        $spreadsheet = (new RelatorioSLAExportService())->excel($relatorio);
        try {
            header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
            header('Content-Disposition: attachment; filename="relatorio_sla.xlsx"');
            header('Cache-Control: private, no-store');
            (new Xlsx($spreadsheet))->save('php://output');
        } finally {
            $spreadsheet->disconnectWorksheets();
        }
    }

    private function carregarRelatorio(): ?array
    {
        try {
            return (new RelatorioSLAService())->gerar($_GET);
        } catch (InvalidArgumentException $e) {
            http_response_code(422);
            $this->render('@admin/relatorios/erro.twig', ['mensagem' => $e->getMessage()]);
            return null;
        }
    }
}
