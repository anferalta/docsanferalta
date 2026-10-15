<?php

namespace App\Services;

use App\Models\Documento;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class DocumentoExistsValidator
{
    public static function validarOuFalhar(int $id): Documento
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException("ID de documento inválido.");
        }

        $doc = Documento::find($id);

        if (!$doc) {
            throw new \InvalidArgumentException("Documento não encontrado.");
        }

        PathGuardService::init();

        // Storage pública correta
        $root = $_SERVER['DOCUMENT_ROOT'] . '/storage/documentos';
        PathGuardService::proteger($root);

        // Integridade mínima
        if (empty($doc->titulo)) {
            BackupLogger::registar('DOCUMENTO', $id, false, "Documento sem título: {$id}");
        }

        if (empty($doc->estado_atual)) {
            BackupLogger::registar('DOCUMENTO', $id, false, "Documento sem estado: {$id}");
        }

        return $doc;
    }
}
