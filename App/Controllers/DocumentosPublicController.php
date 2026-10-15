<?php

namespace App\Controllers;

use App\Models\DocumentoFicheiro;

class DocumentosPublicController
{
    public function abrirPublico($id)
    {
        // Não usar PathGuardService aqui
        // Não verificar sessão
        // Não redirecionar para login

        $anexo = DocumentoFicheiro::find($id);

        if (!$anexo) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $real = rtrim(STORAGE_DOCUMENTOS, '/') . '/' . trim($anexo->caminho, '/') . '/' . $anexo->ficheiro;

        if (!file_exists($real)) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $mime = mime_content_type($real);

        header("Content-Type: $mime");
        header("Content-Disposition: inline; filename=\"" . $anexo->ficheiro . "\"");
        header("Content-Length: " . filesize($real));

        readfile($real);
        exit;
    }
}
