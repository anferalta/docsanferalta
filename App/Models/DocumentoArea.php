<?php

namespace App\Models;

use App\Core\Conexao;

class DocumentoArea {
    
    public static function all()
    {
        $db = Conexao::getInstancia();
        $stmt = $db->query("SELECT * FROM documento_areas ORDER BY nome ASC");
        return $stmt->fetchAll();
    }
    
    public static function todas() {
        $db = Conexao::getInstancia();
        return $db->query("SELECT * FROM documento_areas ORDER BY nome ASC")->fetchAll();
    }

    public static function ativas() {
        $db = Conexao::getInstancia();
        return $db->query("SELECT * FROM documento_areas WHERE ativo = 1 ORDER BY nome ASC")->fetchAll();
    }

    public static function find($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT * FROM documento_areas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetch();
    }

    public static function criar($nome, $codigo, $descricao, $ativo) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            INSERT INTO documento_areas (nome, codigo, descricao, ativo, criado_em)
            VALUES (?, ?, ?, ?, NOW())
        ");
        return $stmt->execute([$nome, $codigo, $descricao, $ativo]);
    }

    public static function atualizar($id, $nome, $codigo, $descricao, $ativo) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            UPDATE documento_areas
            SET nome = ?, codigo = ?, descricao = ?, ativo = ?, atualizado_em = NOW()
            WHERE id = ?
        ");
        return $stmt->execute([$nome, $codigo, $descricao, $ativo, $id]);
    }

    public static function apagar($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("DELETE FROM documento_areas WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public static function existe($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT COUNT(*) FROM documento_areas WHERE id = ?");
        $stmt->execute([$id]);
        return $stmt->fetchColumn() > 0;
    }

    public static function desativar($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("UPDATE documento_areas SET ativo = 0 WHERE id = ?");
        return $stmt->execute([$id]);
    }
    
}
