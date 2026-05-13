<?php

namespace App\Models;

use App\Core\Conexao;

class DocumentoTramitacaoAnexo
{
    public static function create(array $data)
    {
        $db = Conexao::getInstancia();

        $sql = "INSERT INTO documento_tramitacao_anexos 
                (tramitacao_id, ficheiro, nome_original, criado_em)
                VALUES (?, ?, ?, NOW())";

        $stmt = $db->prepare($sql);
        return $stmt->execute([
            $data['tramitacao_id'],
            $data['ficheiro'],
            $data['nome_original']
        ]);
    }

    public static function where($campo, $valor)
    {
        $db = Conexao::getInstancia();

        $sql = "SELECT * FROM documento_tramitacao_anexos 
                WHERE $campo = ?
                ORDER BY criado_em ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$valor]);
        return $stmt->fetchAll();
    }

    public static function find($id)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT * FROM documento_tramitacao_anexos WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }
}
