<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoFicheiro;
use App\Services\PathGuardService;

class DocumentoUploader
{
    public static function processarUpload(array $post, array $files, $user): Documento
    {
        PathGuardService::init();

        $titulo  = trim($post['titulo']);
        $tipo_id = intval($post['tipo_id']);

        $ficheiros = $files['ficheiros'];

        if (!isset($ficheiros['name']) || !is_array($ficheiros['name'])) {
            throw new \Exception("Nenhum ficheiro enviado.");
        }

        $validos = [];
        foreach ($ficheiros['name'] as $i => $nome) {
            if (!empty($nome) && $ficheiros['error'][$i] === UPLOAD_ERR_OK) {
                $validos[] = $i;
            }
        }

        if (count($validos) < 1) {
            throw new \Exception("Nenhum ficheiro válido enviado.");
        }

        $documentoId = Documento::create([
            'titulo'       => $titulo,
            'tipo_id'      => $tipo_id,
            'criado_por'   => $user->id,
            'estado_atual' => 'novo',
            'criado_em'    => date('Y-m-d H:i:s')
        ]);

        $documento = Documento::find($documentoId);
        if (!$documento) {
            throw new \Exception("Falha ao criar documento.");
        }

        $extPermitidas = [
            'pdf',
            'doc',
            'docx',
            'xls',
            'xlsx',
            'ppt',
            'pptx',
            'txt',
            'jpg',
            'jpeg',
            'png',
            'gif',
            'webp',
            'zip',
            'rar',
            '7z'
        ];

        $extPerigosas = [
            'php',
            'php3',
            'php4',
            'php5',
            'phtml',
            'exe',
            'msi',
            'bat',
            'cmd',
            'sh',
            'ps1',
            'js',
            'html',
            'htm',
            'svg',
            'dll',
            'sys',
            'com'
        ];

        $limitesPorExt = [
            'txt' => 2 * 1024 * 1024,
            'pdf' => 20 * 1024 * 1024,
            'jpg' => 10 * 1024 * 1024,
            'jpeg' => 10 * 1024 * 1024,
            'png' => 10 * 1024 * 1024,
            'gif' => 5 * 1024 * 1024,
            'webp' => 10 * 1024 * 1024,
            'zip' => 50 * 1024 * 1024,
            'rar' => 50 * 1024 * 1024,
            '7z' => 50 * 1024 * 1024,
            'doc' => 10 * 1024 * 1024,
            'docx' => 10 * 1024 * 1024,
            'xls' => 10 * 1024 * 1024,
            'xlsx' => 10 * 1024 * 1024,
            'ppt' => 20 * 1024 * 1024,
            'pptx' => 20 * 1024 * 1024
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        $baseRoot = STORAGE_DOCUMENTOS;
        PathGuardService::proteger($baseRoot);

        // Caminho normalizado (sem barra final)
        $subpasta = trim(date('Y/m/d'), '/');
        $base     = rtrim($baseRoot, '/') . '/' . $subpasta;

        PathGuardService::proteger($base);

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
            file_put_contents($base . '/.keep', 'sentinel');
        }

        foreach ($validos as $i) {

            $nomeOriginal = trim($ficheiros['name'][$i]);
            $tmp          = $ficheiros['tmp_name'][$i];
            $tamanho      = $ficheiros['size'][$i];

            if (!is_uploaded_file($tmp)) {
                continue;
            }

            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

            if (!in_array($ext, $extPermitidas, true)) {
                throw new \Exception("Tipo não permitido: {$nomeOriginal}");
            }

            if ($ext === 'txt') {
                $partes      = explode('.', $nomeOriginal);
                $extAnterior = strtolower($partes[count($partes) - 2] ?? '');
                if (in_array($extAnterior, $extPerigosas, true)) {
                    throw new \Exception("Tipo não permitido: {$nomeOriginal}");
                }
            }

            $limite = $limitesPorExt[$ext] ?? (10 * 1024 * 1024);
            if ($tamanho > $limite) {
                throw new \Exception("{$nomeOriginal} excede o limite permitido.");
            }

            $mimeReal = $finfo->file($tmp) ?: 'application/octet-stream';

            $nomeSeguro   = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
            $nomeGuardado = uniqid() . '_' . $nomeSeguro;

            $destino = $base . '/' . $nomeGuardado;
            PathGuardService::proteger($destino);

            if (!move_uploaded_file($tmp, $destino)) {
                throw new \Exception("Erro ao guardar o ficheiro: {$nomeOriginal}");
            }

            $hash = hash_file('sha256', $destino);

            $existe = DocumentoFicheiro::query()
                ->where('documento_id', '=', $documento->id)
                ->where('hash', '=', $hash)
                ->first();

            if ($existe) {
                unlink($destino);
                throw new \Exception("Este ficheiro já foi enviado anteriormente: {$nomeOriginal}");
            }

            DocumentoFicheiro::create([
                'documento_id'      => $documento->id,
                'ficheiro'          => $nomeGuardado,
                'ficheiro_original' => $nomeOriginal,
                'caminho'           => $subpasta, // sem barra final
                'tamanho'           => $tamanho,
                'mime_type'         => $mimeReal,
                'hash'              => $hash,
                'criado_em'         => date('Y-m-d H:i:s')
            ]);
        }

        return $documento;
    }
}
