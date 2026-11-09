<?php

namespace App\Services;

use Closure;
use RuntimeException;

class RelatorioSLAEmailService
{
    private RelatorioSLAService $relatorios;
    private Closure $enviar;

    public function __construct(?RelatorioSLAService $relatorios = null, ?Closure $enviar = null)
    {
        $this->relatorios = $relatorios ?? new RelatorioSLAService();
        $this->enviar = $enviar ?? Closure::fromCallable([EmailService::class, 'enviar']);
    }

    public function gerarPDF(): string
    {
        $conteudo = (new RelatorioSLAExportService())->pdf($this->relatorios->gerar());
        $diretorio = dirname(__DIR__, 2) . '/storage/temp/relatorios';
        if (!is_dir($diretorio) && !mkdir($diretorio, 0770, true) && !is_dir($diretorio)) {
            throw new RuntimeException('Não foi possível criar o diretório dos relatórios SLA.');
        }
        $path = $diretorio . '/relatorio_sla_' . date('Y-m-d_H-i-s') . '_' . bin2hex(random_bytes(8)) . '.pdf';
        if (file_put_contents($path, $conteudo, LOCK_EX) !== strlen($conteudo)) {
            if (is_file($path)) {
                unlink($path);
            }
            throw new RuntimeException('Não foi possível guardar o relatório SLA.');
        }
        return $path;
    }

    public function enviarEmail(string $periodo = 'diário'): void
    {
        $pdf = $this->gerarPDF();
        try {
            $enviado = ($this->enviar)(
                'geral@anferalta.com',
                'Relatório SLA - ' . $periodo . ' - ' . date('d/m/Y'),
                'relatorio_sla',
                ['periodo' => $periodo],
                [$pdf]
            );
            if (!$enviado) {
                throw new RuntimeException('O envio do relatório SLA não foi confirmado.');
            }
        } finally {
            // Remove apenas o anexo temporário criado nesta execução.
            if (is_file($pdf)) {
                unlink($pdf);
            }
        }
    }
}
