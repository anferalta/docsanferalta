<?php

namespace App\Services;

class DocumentoValidator
{
    public static function validarCriacao(array $post, array $files): ?string
    {
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
