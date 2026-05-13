<?php

namespace App\Services;

class DocumentoFileService
{
    public static function resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro)
    {
        $root = dirname(__DIR__, 2) . '/storage/documentos';

        // Sanitização segura
        $ano = preg_replace('/[^0-9]/', '', $ano);
        $mes = preg_replace('/[^0-9]/', '', $mes);
        $dia = preg_replace('/[^0-9]/', '', $dia);
        $ficheiro = basename($ficheiro);

        if (!$ano || !$mes || !$dia) {
            throw new \Exception("Caminho inválido.");
        }

        $path = "$root/$ano/$mes/$dia/$ficheiro";

        if (!file_exists($path)) {
            throw new \Exception("Ficheiro não encontrado.");
        }

        return $path;
    }
}
