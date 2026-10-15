<?php

namespace App\Services;

use App\Models\DocumentoEstado;

class EstadoService
{
    /**
     * Normaliza o estado recebido do POST
     */
    public static function normalizar(string $estado): string
    {
        // Remover espaços e converter para minúsculas
        $estado = strtolower(trim($estado));

        // Remover acentos
        $estado = str_replace(
            ['á','à','ã','â','é','ê','í','ó','ô','õ','ú','ç'],
            ['a','a','a','a','e','e','i','o','o','o','u','c'],
            $estado
        );

        // Remover caracteres inválidos
        $estado = preg_replace('/[^a-z0-9_]/i', '', $estado);

        return $estado;
    }

    /**
     * Valida o estado usando a BD (não lista fixa)
     */
    public static function validarOuFalhar(string $estado): string
    {
        $estado = self::normalizar($estado);

        $registo = DocumentoEstado::findByCodigo($estado);

        if (!$registo) {
            throw new \Exception("Estado inválido: {$estado}");
        }

        return $estado;
    }

    /**
     * Verifica se o estado existe na BD
     */
    public static function existeNaBD(string $estado): bool
    {
        $estado = self::normalizar($estado);

        return DocumentoEstado::findByCodigo($estado) !== null;
    }
}
