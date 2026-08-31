<?php

namespace App\Services;

use App\Models\Documento;

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

        return $doc;
    }
}
