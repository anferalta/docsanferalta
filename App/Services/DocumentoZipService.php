<?php

namespace App\Services;

use App\Models\Documento;
use ZipArchive;

class DocumentoZipService
{
    public static function criarZip(array $ids, $user): string
    {
        if (empty($ids)) {
            throw new \Exception('Nenhum documento selecionado.');
        }

        $root = dirname(__DIR__, 2);
        $tmpDir = $root . '/storage/tmp/';

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
        }

        $zipName = 'documentos_' . $user->id . '_' . date('Ymd_His') . '.zip';
        $zipPath = $tmpDir . $zipName;

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Não foi possível criar o ficheiro ZIP.');
        }

        foreach ($ids as $id) {
            $doc = Documento::find($id);

            if (!$doc) {
                continue;
            }

            // (Opcional) restringir por utilizador:
            // if ($doc->criado_por !== $user->id) continue;

            // caminho tipo "YYYY/MM/DD/"
            $caminho = trim($doc->caminho, '/');
            [$ano, $mes, $dia] = explode('/', $caminho);

            $path = DocumentoFileService::resolverCaminhoSeguro($ano, $mes, $dia, $doc->ficheiro);

            $nomeNoZip = $doc->ficheiro_original ?: $doc->ficheiro;
            $zip->addFile($path, $nomeNoZip);
        }

        $zip->close();

        if (!file_exists($zipPath)) {
            throw new \Exception('Erro ao gerar o ZIP.');
        }

        return $zipPath;
    }
}
