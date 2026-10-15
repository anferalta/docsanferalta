<?php

namespace App\Services;

use App\Services\PathGuardService;

class DocumentoValidator
{
    /**
     * Validação da criação de documentos
     */
    public static function validarCriacao(array $post, array $files): ?string
    {
        PathGuardService::init();
        PathGuardService::proteger(STORAGE_DOCUMENTOS);

        // -----------------------------
        // Título
        // -----------------------------
        $titulo = trim($post['titulo'] ?? '');
        if ($titulo === '') {
            return 'O título é obrigatório.';
        }

        // -----------------------------
        // Tipo de documento
        // -----------------------------
        if (empty($post['tipo_id']) || intval($post['tipo_id']) <= 0) {
            return 'Selecione o tipo de documento.';
        }

        // -----------------------------
        // Ficheiros
        // -----------------------------
        if (!isset($files['ficheiros']) || !isset($files['ficheiros']['name'])) {
            return 'Nenhum ficheiro enviado.';
        }

        $nomes = $files['ficheiros']['name'];

        // Garantir que é array
        if (!is_array($nomes)) {
            return 'Nenhum ficheiro enviado.';
        }

        // Verificar se existe pelo menos 1 ficheiro válido
        $temFicheiroValido = false;

        foreach ($nomes as $nome) {
            if (!empty($nome)) {
                $temFicheiroValido = true;
                break;
            }
        }

        if (!$temFicheiroValido) {
            return 'Nenhum ficheiro enviado.';
        }

        // Limite máximo
        if (count($nomes) > 10) {
            return 'Máximo de 10 ficheiros por envio.';
        }

        return null;
    }

    /**
     * Validação da edição de documentos
     */
    public static function validarEdicao(array $post): ?string
    {
        PathGuardService::init();

        PathGuardService::proteger(STORAGE_DOCUMENTOS);

        // -----------------------------
        // Título
        // -----------------------------
        $titulo = trim($post['titulo'] ?? '');
        if ($titulo === '') {
            return 'O título é obrigatório.';
        }

        // -----------------------------
        // Tipo de documento
        // -----------------------------
        if (empty($post['tipo_id']) || intval($post['tipo_id']) <= 0) {
            return 'Selecione o tipo de documento.';
        }

        return null;
    }
}
