<?php

namespace App\Services;

use App\Models\DocumentoEstado;

class EstadoService
{
    /**
     * Lista de estados permitidos (códigos reais da BD)
     */
    private static array $permitidos = [
        'novo',
        'pendente',
        'analise',
        'em_tramitacao',
        'concluido',
        'devolvido',
        'arquivado'
    ];

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
        $estado = preg_replace('/[^a-z_]/', '', $estado);

        return $estado;
    }

    /**
     * Valida o estado e devolve o código correto
     */
    public static function validarOuFalhar(string $estado): string
    {
        $estado = self::normalizar($estado);

        // Verificar se está na lista permitida
        if (!in_array($estado, self::$permitidos, true)) {
            throw new \Exception("Estado inválido: {$estado}");
        }

        return $estado;
    }

    /**
     * Verifica se o estado existe na BD (opcional)
     */
    public static function existeNaBD(string $estado): bool
    {
        $estado = self::normalizar($estado);

        $registo = DocumentoEstado::findByCodigo($estado);

        return $registo !== null;
    }
}
