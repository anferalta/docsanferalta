<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class RelatoriosAdminController extends BaseController
{
    /**
     * Página principal dos relatórios SLA
     */
    public function index()
    {
        $this->authorize('admin.relatorios.ver');

        $db = Conexao::getInstancia();

        // ============================
        // FILTROS
        // ============================

        $area = $_GET['area'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        // ============================
        // QUERY BASE
        // ============================

        $sql = "
            SELECT 
                d.id,
                d.titulo,
                d.estado_atual,
                d.area_atual_desde,
                d.criado_em,
                a.nome AS area_nome,
                a.prazo_resposta
            FROM documentos d
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
            WHERE 1=1
        ";

        $params = [];

        if ($area !== '') {
            $sql .= " AND a.nome = ? ";
            $params[] = $area;
        }

        if ($estado !== '') {
            $sql .= " AND d.estado_atual = ? ";
            $params[] = $estado;
        }

        if ($dataInicio !== '') {
            $sql .= " AND DATE(d.criado_em) >= ? ";
            $params[] = $dataInicio;
        }

        if ($dataFim !== '') {
            $sql .= " AND DATE(d.criado_em) <= ? ";
            $params[] = $dataFim;
        }

        $sql .= " ORDER BY d.criado_em DESC ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $docs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // ============================
        // CALCULAR SLA
        // ============================

        foreach ($docs as &$d) {

            if ($d['area_atual_desde'] && $d['prazo_resposta']) {

                $inicio = new \DateTime($d['area_atual_desde']);
                $agora = new \DateTime();
                $dias = $inicio->diff($agora)->days;

                $prazo = (int) $d['prazo_resposta'];

                if ($dias <= $prazo) {
                    $d['sla'] = 'ok';
                } elseif ($dias <= $prazo + 2) {
                    $d['sla'] = 'alerta';
                } else {
                    $d['sla'] = 'atrasado';
                }

                $d['dias_parado'] = $dias;

            } else {
                $d['sla'] = 'indefinido';
                $d['dias_parado'] = null;
            }
        }

        return $this->render('@admin/relatorios/index.twig', [
            'documentos' => $docs,
        ]);
    }

    /**
     * Exportar relatório SLA em PDF
     */
    public function exportarPDF()
    {
        $this->authorize('admin.relatorios.ver');

        // Reutiliza a lógica do index()
        $html = $this->gerarHTMLRelatorio();

        // Configurações do Dompdf
        $options = new Options();
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $dompdf->stream("relatorio_sla.pdf", ["Attachment" => true]);
    }

    /**
     * Exportar relatório SLA em Excel
     */
    public function exportarExcel()
    {
        $this->authorize('admin.relatorios.ver');

        $docs = $this->getDocsFiltrados();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        // Cabeçalhos
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Título');
        $sheet->setCellValue('C1', 'Área');
        $sheet->setCellValue('D1', 'Estado');
        $sheet->setCellValue('E1', 'SLA');
        $sheet->setCellValue('F1', 'Dias Parado');
        $sheet->setCellValue('G1', 'Criado em');

        $row = 2;

        foreach ($docs as $d) {
            $sheet->setCellValue("A{$row}", $d['id']);
            $sheet->setCellValue("B{$row}", $d['titulo']);
            $sheet->setCellValue("C{$row}", $d['area_nome']);
            $sheet->setCellValue("D{$row}", $d['estado_atual']);
            $sheet->setCellValue("E{$row}", $d['sla']);
            $sheet->setCellValue("F{$row}", $d['dias_parado']);
            $sheet->setCellValue("G{$row}", $d['criado_em']);
            $row++;
        }

        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="relatorio_sla.xlsx"');

        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
    }

    /**
     * Função auxiliar — gera HTML para PDF
     */
    private function gerarHTMLRelatorio()
    {
        $docs = $this->getDocsFiltrados();

        ob_start();
        include __DIR__ . '/../../Views/admin/relatorios/pdf_template.php';
        return ob_get_clean();
    }

    /**
     * Função auxiliar — obtém documentos filtrados
     */
    private function getDocsFiltrados()
    {
        $db = Conexao::getInstancia();

        $area = $_GET['area'] ?? '';
        $estado = $_GET['estado'] ?? '';
        $dataInicio = $_GET['data_inicio'] ?? '';
        $dataFim = $_GET['data_fim'] ?? '';

        $sql = "
            SELECT 
                d.id,
                d.titulo,
                d.estado_atual,
                d.area_atual_desde,
                d.criado_em,
                a.nome AS area_nome,
                a.prazo_resposta
            FROM documentos d
            LEFT JOIN documento_areas a ON a.id = d.area_atual_id
            WHERE 1=1
        ";

        $params = [];

        if ($area !== '') {
            $sql .= " AND a.nome = ? ";
            $params[] = $area;
        }

        if ($estado !== '') {
            $sql .= " AND d.estado_atual = ? ";
            $params[] = $estado;
        }

        if ($dataInicio !== '') {
            $sql .= " AND DATE(d.criado_em) >= ? ";
            $params[] = $dataInicio;
        }

        if ($dataFim !== '') {
            $sql .= " AND DATE(d.criado_em) <= ? ";
            $params[] = $dataFim;
        }

        $sql .= " ORDER BY d.criado_em DESC ";

        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $docs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calcular SLA
        foreach ($docs as &$d) {

            if ($d['area_atual_desde'] && $d['prazo_resposta']) {

                $inicio = new \DateTime($d['area_atual_desde']);
                $agora = new \DateTime();
                $dias = $inicio->diff($agora)->days;

                $prazo = (int) $d['prazo_resposta'];

                if ($dias <= $prazo) {
                    $d['sla'] = 'ok';
                } elseif ($dias <= $prazo + 2) {
                    $d['sla'] = 'alerta';
                } else {
                    $d['sla'] = 'atrasado';
                }

                $d['dias_parado'] = $dias;

            } else {
                $d['sla'] = 'indefinido';
                $d['dias_parado'] = null;
            }
        }

        return $docs;
    }
}
