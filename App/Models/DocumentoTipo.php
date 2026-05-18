<?php

namespace App\Models;

use App\Core\Conexao;
use PDO;

class DocumentoTipo {

    public int $id;
    public string $nome;

    public static function all() {
        $db = Conexao::getInstancia();
        $stmt = $db->query("SELECT tipo_id AS id, nome FROM documento_tipos ORDER BY nome");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public static function create($data) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("INSERT INTO documento_tipos (nome) VALUES (:nome)");
        $stmt->execute(['nome' => $data['nome']]);
        return $db->lastInsertId();
    }

    public static function criar(string $nome): int {
        return self::create(['nome' => $nome]);
    }

    public static function find($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT * FROM documento_tipos WHERE id = :id");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchObject(self::class);
    }

    public static function update($id, $data) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("UPDATE documento_tipos SET nome = :nome WHERE id = :id");
        $stmt->execute([
            'nome' => $data['nome'],
            'id' => $id
        ]);
    }

    public static function delete($id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("DELETE FROM documento_tipos WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    public static function existeNome(string $nome): bool {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT id FROM documento_tipos WHERE nome = :nome LIMIT 1");
        $stmt->execute(['nome' => $nome]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function existeNomeParaOutro(string $nome, int $id): bool {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
        SELECT id 
        FROM documento_tipos 
        WHERE nome = :nome 
          AND id != :id 
        LIMIT 1
    ");
        $stmt->execute([
            'nome' => $nome,
            'id' => $id
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
