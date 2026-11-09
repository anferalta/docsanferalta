<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

class RelatorioSLAExportService
{
    public function pdf(array $relatorio): string
    {
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml((new RelatorioSLAService())->gerarHTML($relatorio), 'UTF-8');
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        return $dompdf->output();
    }

    public function excel(array $relatorio): Spreadsheet
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Relatório SLA');
        $sheet->fromArray(['ID', 'Título', 'Área', 'Estado', 'SLA', 'Dias Parado', 'Criado em'], null, 'A1');
        $row = 2;
        foreach ($relatorio['documentos'] as $doc) {
            $sheet->setCellValue("A{$row}", (int) $doc['id']);
            // Conteúdo dos documentos é texto, mesmo quando começa por '='.
            foreach (['B' => 'titulo', 'C' => 'area_nome', 'D' => 'estado_atual', 'E' => 'sla', 'G' => 'criado_em'] as $coluna => $campo) {
                $sheet->setCellValueExplicit("{$coluna}{$row}", (string) ($doc[$campo] ?? ''), DataType::TYPE_STRING);
            }
            $sheet->setCellValue("F{$row}", $doc['dias_parado']);
            $row++;
        }
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
        $sheet->freezePane('A2');
        $sheet->setAutoFilter('A1:G' . max(1, $row - 1));
        foreach (['A' => 10, 'B' => 60, 'C' => 25, 'D' => 22, 'E' => 16, 'F' => 16, 'G' => 22] as $coluna => $largura) {
            $sheet->getColumnDimension($coluna)->setWidth($largura);
        }
        $sheet->getStyle('B1:D' . max(1, $row - 1))->getAlignment()->setWrapText(true);
        return $spreadsheet;
    }
}
