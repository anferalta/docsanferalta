<?php

namespace App\Services;

use App\Models\DocumentoFicheiro;

class DocumentoPreviewService
{
    public static function caminhoPdf(DocumentoFicheiro $anexo, string $origem): string
    {
        $versao = (string) filesize($origem) . ':' . (string) filemtime($origem);
        $diretorio = rtrim(STORAGE_TEMP, '/\\') . DIRECTORY_SEPARATOR
            . 'documentos_preview' . DIRECTORY_SEPARATOR
            . (int) $anexo->id . '-' . hash('sha256', $versao);

        return $diretorio . DIRECTORY_SEPARATOR
            . pathinfo($origem, PATHINFO_FILENAME) . '.pdf';
    }

    public static function converterParaPdf(string $origem, string $pdfDestino): bool
    {
        if (is_file($pdfDestino) && filesize($pdfDestino) > 0) {
            return true;
        }

        $diretorio = dirname($pdfDestino);
        if (!is_dir($diretorio) && !mkdir($diretorio, 0777, true) && !is_dir($diretorio)) {
            return false;
        }

        $origemConversao = $origem;
        $origemTemporaria = null;
        $pdfGerado = $pdfDestino;

        if (strtolower(pathinfo($origem, PATHINFO_EXTENSION)) === 'docx') {
            $cabecalho = file_get_contents($origem, false, null, 0, 16);
            $posAssinatura = $cabecalho === false ? false : strpos($cabecalho, "PK\x03\x04");
            if ($posAssinatura !== false && $posAssinatura > 0) {
                $prefixo = substr($cabecalho, 0, $posAssinatura);
                if (trim($prefixo, " \t\r\n\0") === '') {
                    $conteudo = file_get_contents($origem);
                    if ($conteudo === false) {
                        return false;
                    }

                    $origemTemporaria = $diretorio . DIRECTORY_SEPARATOR
                        . pathinfo($origem, PATHINFO_FILENAME) . '.normalized.docx';
                    if (file_put_contents($origemTemporaria, substr($conteudo, $posAssinatura)) === false) {
                        return false;
                    }
                    $origemConversao = $origemTemporaria;
                    $pdfGerado = $diretorio . DIRECTORY_SEPARATOR
                        . pathinfo($origemTemporaria, PATHINFO_FILENAME) . '.pdf';
                }
            }
        }

        $soffice = '"C:\\Program Files\\LibreOffice\\program\\soffice.com"';
        $comando = $soffice
            . ' --headless --nologo --nofirststartwizard --convert-to pdf'
            . ' "' . $origemConversao . '" --outdir "' . $diretorio . '"';

        $saida = [];
        $codigo = 1;
        exec($comando . ' 2>&1', $saida, $codigo);

        if ($origemTemporaria && is_file($origemTemporaria)) {
            @unlink($origemTemporaria);
        }
        if ($pdfGerado !== $pdfDestino && is_file($pdfGerado)) {
            @rename($pdfGerado, $pdfDestino);
        }

        return is_file($pdfDestino) && filesize($pdfDestino) > 0;
    }
}
