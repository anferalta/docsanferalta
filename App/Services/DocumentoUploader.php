<?php

namespace App\Services;

use App\Models\Documento;
use App\Models\DocumentoFicheiro;

class DocumentoUploader
{
    public static function processarUpload(array $post, array $files, $user): void
    {
        $titulo = trim($post['titulo']);
        $tipo_id = $post['tipo_id'];

        $ficheiros = $files['ficheiros'];
        $total = count($ficheiros['name']);

        if ($total < 1) {
            throw new \Exception("Nenhum ficheiro enviado.");
        }

        // ============================
        // 1. Criar o DOCUMENTO (único)
        // ============================
        $documento = Documento::create([
            'titulo' => $titulo,
            'tipo_id' => $tipo_id,
            'criado_por' => $user->id,
            'estado_atual' => 'novo'
        ]);

        // ============================
        // 2. Validações e limites
        // ============================
        $extPermitidas = [
            'pdf','doc','docx','xls','xlsx','ppt','pptx','txt',
            'jpg','jpeg','png','gif','webp','zip','rar','7z'
        ];

        $extPerigosas = [
            'php','php3','php4','php5','phtml',
            'exe','msi','bat','cmd','sh','ps1',
            'js','html','htm','svg','dll','sys','com'
        ];

        $limitesPorExt = [
            'txt'=>2*1024*1024,'pdf'=>20*1024*1024,'jpg'=>10*1024*1024,
            'jpeg'=>10*1024*1024,'png'=>10*1024*1024,'gif'=>5*1024*1024,
            'webp'=>10*1024*1024,'zip'=>50*1024*1024,'rar'=>50*1024*1024,
            '7z'=>50*1024*1024,'doc'=>10*1024*1024,'docx'=>10*1024*1024,
            'xls'=>10*1024*1024,'xlsx'=>10*1024*1024,'ppt'=>20*1024*1024,
            'pptx'=>20*1024*1024
        ];

        $finfo = new \finfo(FILEINFO_MIME_TYPE);

        // ============================
        // 3. Caminho seguro
        // ============================
        $root = dirname(__DIR__, 2);

        $ano = date('Y');
        $mes = date('m');
        $dia = date('d');

        $subpasta = "$ano/$mes/$dia";
        $base = $root . "/storage/documentos/$subpasta/";

        if (!is_dir($base)) {
            mkdir($base, 0777, true);
        }

        // ============================
        // 4. Processar cada ficheiro (ANEXOS)
        // ============================
        for ($i = 0; $i < $total; $i++) {

            $nomeOriginal = trim($ficheiros['name'][$i]);
            $tmp = $ficheiros['tmp_name'][$i];
            $erro = $ficheiros['error'][$i];
            $tamanho = $ficheiros['size'][$i];

            if ($erro !== UPLOAD_ERR_OK) {
                throw new \Exception("Erro ao enviar o ficheiro: {$nomeOriginal}");
            }

            if (!is_uploaded_file($tmp)) {
                throw new \Exception("Ficheiro inválido: {$nomeOriginal}");
            }

            $ext = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));

            if (!in_array($ext, $extPermitidas, true)) {
                throw new \Exception("Tipo não permitido: {$nomeOriginal}");
            }

            if ($ext === 'txt') {
                $partes = explode('.', $nomeOriginal);
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

            $nomeSeguro = preg_replace('/[^A-Za-z0-9_\.-]/', '_', $nomeOriginal);
            $nomeGuardado = uniqid() . '_' . $nomeSeguro;

            $destino = $base . $nomeGuardado;

            if (!move_uploaded_file($tmp, $destino)) {
                throw new \Exception("Erro ao guardar o ficheiro: {$nomeOriginal}");
            }

            // ============================
            // 5. Hash anti-duplicação
            // ============================
            $hash = hash_file('sha256', $destino);

            $existe = DocumentoFicheiro::query()
                ->where('documento_id', '=', $documento->id)
                ->where('hash', '=', $hash)
                ->first();

            if ($existe) {
                unlink($destino);
                throw new \Exception("Este ficheiro já foi enviado anteriormente: {$nomeOriginal}");
            }

            // ============================
            // 6. Criar ANEXO
            // ============================
            DocumentoFicheiro::create([
                'documento_id' => $documento->id,
                'ficheiro' => $subpasta . '/' . $nomeGuardado,
                'tamanho' => $tamanho,
                'mime' => $mimeReal
            ]);
        }
    }
}
