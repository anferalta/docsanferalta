<?php

namespace App\Controllers;

use App\Models\DocumentoFicheiro;
use App\Models\Documento;
use App\Models\DocumentosArquivados;

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

        $candidatos = [];
        if (!empty($anexo->ficheiro)) {
            $candidatos[] = rtrim(STORAGE_DOCUMENTOS, '/') . '/' . trim((string) $anexo->caminho, '/') . '/' . basename($anexo->ficheiro);
        }

        // A recuperação pode atualizar o documento e deixar um anexo antigo
        // com caminho desatualizado. Tentar também a versão atual do documento.
        $documento = Documento::find((int) $anexo->documento_id);
        if ($documento && !empty($documento->ficheiro)) {
            $candidatos[] = rtrim(STORAGE_DOCUMENTOS, '/') . '/' . trim((string) $documento->caminho, '/') . '/' . basename($documento->ficheiro);
        }

        $real = null;
        foreach ($candidatos as $candidato) {
            $resolvido = realpath($candidato);
            if ($resolvido !== false && is_file($resolvido)) {
                $real = $resolvido;
                break;
            }
        }

        // Também permitir a leitura do ficheiro no arquivo para documentos
        // que ainda estejam arquivados e não tenham cópia na pasta ativa.
        if (!$real) {
            $arquivo = DocumentosArquivados::porDocumento((int) $anexo->documento_id);
            if ($arquivo) {
                $resolvido = realpath($arquivo->caminhoAbsoluto());
                if ($resolvido !== false && is_file($resolvido)) {
                    $real = $resolvido;
                }
            }
        }

        if (!$real) {
            http_response_code(404);
            exit("Ficheiro não encontrado.");
        }

        $mimePorExtensao = [
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'pdf' => 'application/pdf',
        ];
        $extensao = strtolower(pathinfo($real, PATHINFO_EXTENSION));
        $mime = $mimePorExtensao[$extensao] ?? (mime_content_type($real) ?: 'application/octet-stream');

        header('X-Content-Type-Options: nosniff');
        header("Content-Type: $mime");
        header("Content-Disposition: inline; filename=\"" . basename($real) . "\"");
        header("Content-Length: " . filesize($real));

        readfile($real);
        exit;
    }

}
