<?php

namespace App\Services;

use App\Models\DocumentoEstado;

class EstadoService
{
    public static function normalizar(string $codigo): ?string
    {
        $codigo = trim($codigo);

        // Lista branca — só estes são válidos
        $permitidos = [
            'pendente',
            'em_tramitacao',
            'em_analise',
            'concluido',
            'devolvido',
            'arquivado',
            'novo',
        ];

        return in_array($codigo, $permitidos, true) ? $codigo : null;
    }

    public static function validarOuFalhar(string $codigo): string
    {
        $codigoNormalizado = self::normalizar($codigo);

        if ($codigoNormalizado === null) {
            throw new \InvalidArgumentException("Estado inválido: {$codigo}");
        }

        return $codigoNormalizado;
    }
}
