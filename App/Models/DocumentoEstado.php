<?php

namespace App\Models;

use App\Core\Conexao;

class DocumentoEstado
{
    public static function all()
    {
        $db = Conexao::getInstancia();
        $stmt = $db->query("SELECT * FROM documento_estados ORDER BY nome ASC");
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT * FROM documento_estados WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
