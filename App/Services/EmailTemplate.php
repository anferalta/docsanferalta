<?php

namespace App\Services;

class EmailTemplate
{
    public static function render(string $ficheiro, array $dados = []): string
    {
        $caminho = __DIR__ . '/../../resources/emails/' . $ficheiro . '.html';

        if (!file_exists($caminho)) {
            return "Template não encontrado: {$ficheiro}";
        }

        $html = file_get_contents($caminho);

        foreach ($dados as $chave => $valor) {
            $html = str_replace('{{' . $chave . '}}', $valor, $html);
        }

        return $html;
    }
}