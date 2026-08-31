<?php

namespace App\Services;

use App\Services\PathGuardService;
use App\Services\BackupLogger;

class TramitacaoFileService
{
    /**
     * Guarda anexos de um movimento de tramitação.
     */
    public static function guardarAnexos(int $historicoId, array $files): array
    {
        $guardados = [];

        if (empty($files['name'][0])) {
            return $guardados;
        }

        PathGuardService::init();

        $root = dirname(__DIR__, 2);
        $baseRoot = $root . "/storage/tramitacao";

        // 🔒 Proteger a pasta base
        PathGuardService::proteger($baseRoot);

        // Criar pasta base se não existir
        if (!is_dir($baseRoot)) {
            mkdir($baseRoot, 0777, true);
            file_put_contents($baseRoot . '/.keep', 'sentinel');
        }

        // Caminho do histórico
        $base = $baseRoot . "/$historicoId";

        // 🔒 Proteger a pasta do histórico (mesmo antes de existir)
        PathGuardService::proteger($base);

        // Criar pasta do histórico
        if (!is_dir($base)) {
            mkdir($base, 0777, true);
            file_put_contents($base . '/.keep', 'sentinel');
        }

        $total = count($files['name']);

        for ($i = 0; $i < $total; $i++) {

            $nomeOriginal = trim($files['name'][$i]);
            $tmp = $files['tmp_name'][$i];
            $erro = $files['error'][$i];

            if ($erro !== UPLOAD_ERR_OK) {
                throw new \Exception("Erro ao enviar o ficheiro: {$nomeOriginal}");
            }

            if (!is_uploaded_file($tmp)) {
                throw new \Exception("Ficheiro inválido: {$nomeOriginal}");
            }

            // Nome seguro
            $nomeSeguro = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
            $nomeGuardado = uniqid() . '_' . $nomeSeguro;

            $destino = $base . '/' . $nomeGuardado;

            // 🔒 Proteger o destino antes de escrever
            PathGuardService::proteger($destino);

            if (!move_uploaded_file($tmp, $destino)) {
                throw new \Exception("Erro ao guardar o ficheiro: {$nomeOriginal}");
            }

            $guardados[] = [
                'ficheiro' => $nomeGuardado,
                'nome_original' => $nomeOriginal,
                'mime_type' => mime_content_type($destino),
                'tamanho' => filesize($destino)
            ];
        }

        return $guardados;
    }

    /**
     * Resolve caminho seguro para abrir um anexo.
     */
    public static function resolverCaminhoSeguro(int $historicoId, string $ficheiro): string
    {
        PathGuardService::init();

        $root = realpath(dirname(__DIR__, 2) . '/storage/tramitacao');

        if ($root === false) {
            throw new \Exception("Pasta base de tramitação não encontrada.");
        }

        // 🔒 Proteger a pasta base
        PathGuardService::proteger($root);

        $historicoId = intval($historicoId);
        $ficheiro = basename($ficheiro);

        if ($historicoId <= 0) {
            throw new \Exception("ID inválido.");
        }

        $path = $root . DIRECTORY_SEPARATOR . $historicoId . DIRECTORY_SEPARATOR . $ficheiro;

        $real = realpath($path);

        if ($real === false) {
            throw new \Exception("Ficheiro não encontrado.");
        }

        // 🔒 Blindagem contra traversal
        if (!str_starts_with($real, $root)) {
            throw new \Exception("Tentativa bloqueada: acesso fora da pasta de tramitação ({$real})");
        }

        return $real;
    }
}
