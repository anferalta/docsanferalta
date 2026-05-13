<?php

namespace App\Services;

class TramitacaoFileService
{
    /**
     * Guarda anexos de um movimento de tramitação.
     * @param int $historicoId  ID do registo na tabela documento_tramitacao_historico
     * @param array $files      $_FILES['anexos']
     * @return array            Lista de ficheiros guardados (nome_guardado, nome_original)
     */
    public static function guardarAnexos(int $historicoId, array $files): array
    {
        $guardados = [];

        if (empty($files['name'][0])) {
            return $guardados;
        }

        $root = dirname(__DIR__, 2);
        $base = $root . "/storage/tramitacao/$historicoId/";

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
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

            $destino = $base . $nomeGuardado;

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
        $root = dirname(__DIR__, 2) . '/storage/tramitacao';

        $historicoId = intval($historicoId);
        $ficheiro = basename($ficheiro);

        if ($historicoId <= 0) {
            throw new \Exception("ID inválido.");
        }

        $path = "$root/$historicoId/$ficheiro";

        if (!file_exists($path)) {
            throw new \Exception("Ficheiro não encontrado.");
        }

        return $path;
    }
}
