<?php

namespace App\Services;

use App\Services\SLAService;
use App\Services\EmailService;
use Dompdf\Dompdf;

class RelatorioSLAEmailService
{
    private string $emailSistema = 'geral@anferalta.com';

    public function gerarPDF(): string
    {
        $dados = SLAService::gerarRelatorio();

        $html = view('admin/relatorios/pdf_template', [
            'dados' => $dados,
            'gerado_em' => date('d/m/Y H:i')
        ]);

        $dompdf = new Dompdf();
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'relatorio_sla_' . date('Y-m-d_H-i') . '.pdf';
        $path = dirname(__DIR__, 2) . '/storage/temp/relatorios/' . $filename;

        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $dompdf->output());

        return $path;
    }

    public function enviarEmail(string $periodo = 'diário'): void
    {
        $pdf = $this->gerarPDF();

        // Lista de supervisores (podes puxar da BD mais tarde)
        $destinatarios = [
            'geral@anferalta.com'
        ];

        $assunto = "Relatório SLA - $periodo - " . date('d/m/Y');
        $mensagem = "Segue em anexo o relatório SLA ($periodo).";

        foreach ($destinatarios as $email) {
            EmailService::enviarComAnexo(
                $email,
                $assunto,
                $mensagem,
                $pdf,
                $this->emailSistema // remetente oficial
            );
        }
    }
}
