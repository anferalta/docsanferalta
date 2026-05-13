<?php

namespace App\Config;

use App\Core\Conexao;

class SincronizadorPermissoes
{
    public static function sincronizar(): bool
    {
        $db = Conexao::getInstancia();

        // Carregar ficheiro de permissões
        $permissoes = require __DIR__ . '/permissoes.php';

        // Apagar permissões antigas
        $db->exec("DELETE FROM permissoes");

        // Inserir permissões novas
        $stmt = $db->prepare("INSERT INTO permissoes (codigo, descricao) VALUES (?, ?)");

        foreach ($permissoes as $p) {
            $stmt->execute([$p['codigo'], $p['descricao']]);
        }

        return true;
    }
}