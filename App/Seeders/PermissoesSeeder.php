<?php

use PDO;

class PermissoesSeeder
{
    public function run(PDO $db): void
    {
        $permissoes = [
            ['chave' => 'admin.perfis.ver',    'descricao' => 'Ver lista de perfis'],
            ['chave' => 'admin.perfis.criar',  'descricao' => 'Criar novos perfis'],
            ['chave' => 'admin.perfis.editar', 'descricao' => 'Editar perfis existentes'],
        ];

        $stmt = $db->prepare("
            INSERT IGNORE INTO permissoes (chave, descricao)
            VALUES (:chave, :descricao)
        ");

        foreach ($permissoes as $perm) {
            $stmt->execute([
                ':chave' => $perm['chave'],
                ':descricao' => $perm['descricao'],
            ]);
        }
    }
}