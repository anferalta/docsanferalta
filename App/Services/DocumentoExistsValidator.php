<?php

namespace App\Services;

use App\Models\Documento;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class DocumentoExistsValidator
{
    public static function validarOuFalhar(int $id): Documento
    {
        // ID inválido
        if ($id <= 0) {
            throw new \InvalidArgumentException("ID de documento inválido.");
        }

        // Procurar documento
        $doc = Documento::find($id);

        if (!$doc) {
            throw new \InvalidArgumentException("Documento não encontrado.");
        }

        // Inicializar proteção
        PathGuardService::init();

        // Proteger caminho base dos documentos
        $root = realpath(dirname(__DIR__, 2) . '/storage/documentos');

        if ($root !== false) {
            PathGuardService::proteger($root);
        }

        // Validar integridade mínima
        if (empty($doc->titulo)) {
            BackupLogger::registar('DOCUMENTO', $id, false, "Documento sem título: {$id}");
        }

        if (empty($doc->estado_atual)) {
            BackupLogger::registar('DOCUMENTO', $id, false, "Documento sem estado: {$id}");
        }

        // Documento válido e protegido
        return $doc;
    }
}
