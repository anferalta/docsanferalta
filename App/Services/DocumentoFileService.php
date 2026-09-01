<?php

namespace App\Services;

class DocumentoFileService
{

    public static function resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro)
    {
        $root = realpath(dirname(__DIR__, 2) . '/storage/documentos');

        if ($root === false) {
            return null;
        }

        $ano = preg_replace('/[^0-9]/', '', $ano);
        $mes = preg_replace('/[^0-9]/', '', $mes);
        $dia = preg_replace('/[^0-9]/', '', $dia);
        $ficheiro = basename($ficheiro);

        if (!$ano || !$mes || !$dia) {
            return null;
        }

        $path = $root . DIRECTORY_SEPARATOR . $ano . DIRECTORY_SEPARATOR . $mes . DIRECTORY_SEPARATOR . $dia;
        $full = $path . DIRECTORY_SEPARATOR . $ficheiro;

        // NÃO usar realpath — causa apagamento de pastas
        if (!file_exists($full)) {
            return null;
        }

        // Proteção absoluta
        if (!str_starts_with($full, $root)) {
            throw new \Exception("Tentativa bloqueada: acesso fora da pasta documentos ({$full})");
        }

        return $full;
    }
}
