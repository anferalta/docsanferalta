<?php

namespace App\Services;

use App\Services\PathGuardService;
use App\Services\BackupLogger;

class DocumentoValidator
{
    public static function validarCriacao(array $post, array $files): ?string
    {
        // Inicializar proteção global
        PathGuardService::init();

        // Proteger a pasta base dos documentos
        $root = realpath(dirname(__DIR__, 2) . '/storage/documentos');
        if ($root !== false) {
            PathGuardService::proteger($root);
        }

        // ============================
        // Validações normais
        // ============================
        $titulo = trim($post['titulo'] ?? '');
        if ($titulo === '') {
            return 'O título é obrigatório.';
        }

        if (empty($post['tipo_id'])) {
            return 'Selecione o tipo de documento.';
        }

        if (!isset($files['ficheiros']) || empty($files['ficheiros']['name'][0])) {
            return 'Nenhum ficheiro enviado.';
        }

        if (count($files['ficheiros']['name']) > 10) {
            return 'Máximo de 10 ficheiros por envio.';
        }

        return null;
    }

    // ============================================================
    //  VALIDAÇÃO DA EDIÇÃO DO DOCUMENTO
    // ============================================================
    public static function validarEdicao(array $post): ?string
    {
        // Inicializar proteção global
        PathGuardService::init();

        // Proteger a pasta base dos documentos
        $root = realpath(dirname(__DIR__, 2) . '/storage/documentos');
        if ($root !== false) {
            PathGuardService::proteger($root);
        }

        // ============================
        // Validações normais
        // ============================
        $titulo = trim($post['titulo'] ?? '');
        if ($titulo === '') {
            return 'O título é obrigatório.';
        }

        if (empty($post['tipo_id'])) {
            return 'Selecione o tipo de documento.';
        }

        return null;
    }
}
