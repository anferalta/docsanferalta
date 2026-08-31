<?php

namespace App\Services;

class DocumentoFileService
{
    public static function resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro)
    {
        // Caminho base protegido
        $root = realpath(dirname(__DIR__, 2) . '/storage/documentos');

        if ($root === false) {
            return null;
        }

        // Sanitização forte
        $ano = preg_replace('/[^0-9]/', '', $ano);
        $mes = preg_replace('/[^0-9]/', '', $mes);
        $dia = preg_replace('/[^0-9]/', '', $dia);
        $ficheiro = basename($ficheiro);

        if (!$ano || !$mes || !$dia) {
            return null;
        }

        // Construção do caminho
        $path = $root . DIRECTORY_SEPARATOR . $ano . DIRECTORY_SEPARATOR . $mes . DIRECTORY_SEPARATOR . $dia;
        $full = $path . DIRECTORY_SEPARATOR . $ficheiro;

        // Normalizar caminho real
        $real = realpath($full);

        // Se não existir, devolver null
        if ($real === false) {
            return null;
        }

        // 🔒 PROTEÇÃO ABSOLUTA: impedir acesso fora da pasta documentos
        // Garante que o caminho real começa dentro de storage/documentos
        if (!str_starts_with($real, $root)) {
            throw new \Exception("Tentativa bloqueada: acesso fora da pasta documentos ({$real})");
        }

        return $real;
    }
}
