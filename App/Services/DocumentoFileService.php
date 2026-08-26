<?php

namespace App\Services;

class DocumentoFileService
{
    public static function resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro)
    {
        $root = dirname(__DIR__, 2) . '/storage/documentos';

        // Sanitização
        $ano = preg_replace('/[^0-9]/', '', $ano);
        $mes = preg_replace('/[^0-9]/', '', $mes);
        $dia = preg_replace('/[^0-9]/', '', $dia);
        $ficheiro = basename($ficheiro);

        if (!$ano || !$mes || !$dia) {
            return null; // nunca lançar exceção
        }

        // Normalizar caminho
        $path = rtrim("$root/$ano/$mes/$dia", '/');
        $full = $path . '/' . $ficheiro;

        // Se não existir, não lançar exceção
        if (!file_exists($full)) {
            return null;
        }

        return $full;
    }
}
