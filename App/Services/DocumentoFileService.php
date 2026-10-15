<?php

namespace App\Services;

class DocumentoFileService
{
    /**
     * Resolve o caminho real e seguro de um ficheiro dentro de:
     * BASE_PATH/storage/documentos/ANO/MES/DIA/ficheiro.ext
     *
     * BASE_PATH = /home/anferalta/www/anferaltadocs
     * STORAGE_DOCUMENTOS = BASE_PATH . '/storage/documentos'
     */
    public static function resolverCaminhoSeguro($ano, $mes, $dia, $ficheiro)
    {
        // Caminho base REAL da tua instalação (confirmado no paths.php)
        $root = STORAGE_DOCUMENTOS;

        // Garantir que a pasta existe
        if (!is_dir($root)) {
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

        // Construção do caminho real
        $path = $root . DIRECTORY_SEPARATOR . $ano . DIRECTORY_SEPARATOR . $mes . DIRECTORY_SEPARATOR . $dia;
        $full = $path . DIRECTORY_SEPARATOR . $ficheiro;

        // Verificar existência
        if (!file_exists($full)) {
            return null;
        }

        // Proteção absoluta — impedir acesso fora da pasta documentos
        if (!str_starts_with($full, $root)) {
            throw new \Exception("Tentativa bloqueada: acesso fora da pasta documentos ({$full})");
        }

        return $full;
    }
}
