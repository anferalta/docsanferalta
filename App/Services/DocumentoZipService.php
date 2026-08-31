<?php

namespace App\Services;

use App\Models\Documento;
use ZipArchive;
use App\Services\PathGuardService;
use App\Services\BackupLogger;

class DocumentoZipService
{
    public static function criarZip(array $ids, $user): string
    {
        if (empty($ids)) {
            throw new \Exception('Nenhum documento selecionado.');
        }

        // Inicializar proteção global
        PathGuardService::init();

        $root = dirname(__DIR__, 2);

        // Proteger pasta base de documentos
        $docRoot = realpath($root . '/storage/documentos');
        if ($docRoot !== false) {
            PathGuardService::proteger($docRoot);
        }

        // Proteger pasta temporária
        $tmpDir = $root . '/storage/tmp/';
        PathGuardService::proteger($tmpDir);

        if (!is_dir($tmpDir)) {
            mkdir($tmpDir, 0777, true);
            file_put_contents($tmpDir . '/.keep', 'sentinel');
        }

        $zipName = 'documentos_' . $user->id . '_' . date('Ymd_His') . '.zip';
        $zipPath = $tmpDir . $zipName;

        // Proteger o ZIP antes de criar
        PathGuardService::proteger($zipPath);

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new \Exception('Não foi possível criar o ficheiro ZIP.');
        }

        foreach ($ids as $id) {

            $doc = Documento::find($id);

            if (!$doc) {
                BackupLogger::registar('ZIP', $id, false, "Documento inexistente no ZIP");
                continue;
            }

            // caminho tipo "YYYY/MM/DD/"
            $caminho = trim($doc->caminho, '/');
            [$ano, $mes, $dia] = explode('/', $caminho);

            // Caminho seguro do ficheiro
            $path = DocumentoFileService::resolverCaminhoSeguro($ano, $mes, $dia, $doc->ficheiro);

            if (!$path) {
                BackupLogger::registar('ZIP', $id, false, "Ficheiro não encontrado no ZIP");
                continue;
            }

            // Blindagem absoluta antes de adicionar ao ZIP
            PathGuardService::proteger($path);

            // Verificar integridade
            if (!file_exists($path) || filesize($path) === 0) {
                BackupLogger::registar('ZIP', $id, false, "Ficheiro corrompido no ZIP");
                continue;
            }

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
