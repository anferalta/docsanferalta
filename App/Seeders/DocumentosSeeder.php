<?php

namespace App\Seeders;

use App\Core\Database;

class DocumentosSeeder
{
    public static function run()
    {
        $permissoes = [
            'admin.documentos.ver',
            'admin.documentos.criar',
            'admin.documentos.editar',
            'admin.documentos.eliminar'
        ];

        foreach ($permissoes as $p) {
            Database::insert('permissoes', [
                'nome' => $p,
                'descricao' => 'Permissão para ' . $p
            ]);
        }
    }
}