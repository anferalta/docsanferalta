<?php

namespace App\Models;

use App\Core\Conexao;

class DocumentoTramitacao
{

    /**
     * Registo direto (usado em partes antigas do sistema)
     */
    public static function registarAntigo($doc_id, $area_id, $user_id, $acao, $estado, $comentario)
    {
        $db = Conexao::getInstancia();

        $sql = "INSERT INTO documento_tramitacao 
                (documento_id, area_id, utilizador_id, acao, estado, comentario, criado_em)
                VALUES (?, ?, ?, ?, ?, ?, NOW())";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $doc_id,
            $area_id ?: null,
            $user_id,
            $acao,
            $estado,
            $comentario
        ]);

        return $db->lastInsertId();
    }

    /**
     * Método create() — compatível com o controller
     */
    public static function create(array $data)
    {
        $db = Conexao::getInstancia();

        file_put_contents(__DIR__ . '/debug_tramitacao.log', print_r($data, true), FILE_APPEND);

        $data['acao'] = !empty($data['acao']) ? $data['acao'] : 'DESCONHECIDO';
        $data['estado'] = $data['estado'] ?? 'indefinido';
        $data['comentario'] = $data['comentario'] ?? null;
        $data['criado_em'] = $data['criado_em'] ?? date('Y-m-d H:i:s');

        $sql = "INSERT INTO documento_tramitacao
            (documento_id, area_id, utilizador_id, acao, estado, comentario, criado_em)
            VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        $stmt->execute([
            $data['documento_id'],
            $data['area_id'] ?? null,
            $data['utilizador_id'],
            $data['acao'],
            $data['estado'],
            $data['comentario'],
            $data['criado_em']
        ]);

        return (object) ['id' => $db->lastInsertId()];
    }

    public static function find($id)
    {
        $db = Conexao::getInstancia();

        $sql = "SELECT * FROM documento_tramitacao WHERE id = ? LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);

        return $stmt->fetch();
    }

    public static function ultimoId()
    {
        $db = Conexao::getInstancia();
        return $db->lastInsertId();
    }

    public static function porDocumento($id)
    {
        $db = Conexao::getInstancia();

        $sql = "SELECT t.*, u.nome AS utilizador_nome, a.nome AS area_nome
                FROM documento_tramitacao t
                LEFT JOIN utilizadores u ON u.id = t.utilizador_id
                LEFT JOIN documento_areas a ON a.id = t.area_id
                WHERE t.documento_id = ?
                ORDER BY t.id ASC";

        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        return $stmt->fetchAll();
    }
}
