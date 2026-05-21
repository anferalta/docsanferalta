<?php

namespace App\Models;

use App\Core\Conexao;
use PDO;

class DocumentoTipo {

    public int $tipo_id;
    public string $nome;

    /* ============================================================
       LISTAR TODOS (como objetos)
    ============================================================ */
    public static function all() {
        $db = Conexao::getInstancia();
        $stmt = $db->query("
            SELECT tipo_id, nome 
            FROM documento_tipos 
            ORDER BY nome
        ");
        return $stmt->fetchAll(PDO::FETCH_CLASS, self::class);
    }

    /* ============================================================
       CRIAR
    ============================================================ */
    public static function create($data) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            INSERT INTO documento_tipos (nome) 
            VALUES (:nome)
        ");
        $stmt->execute(['nome' => $data['nome']]);
        return (int) $db->lastInsertId();
    }

    public static function criar(string $nome): int {
        return self::create(['nome' => $nome]);
    }

    /* ============================================================
       ENCONTRAR POR ID
    ============================================================ */
    public static function find($tipo_id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            SELECT tipo_id, nome 
            FROM documento_tipos 
            WHERE tipo_id = :tipo_id
        ");
        $stmt->execute(['tipo_id' => $tipo_id]);
        return $stmt->fetchObject(self::class);
    }

    /* ============================================================
       ATUALIZAR
    ============================================================ */
    public static function update($tipo_id, $data) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            UPDATE documento_tipos 
            SET nome = :nome 
            WHERE tipo_id = :tipo_id
        ");
        $stmt->execute([
            'nome' => $data['nome'],
            'tipo_id' => $tipo_id
        ]);
    }

    /* ============================================================
       APAGAR
    ============================================================ */
    public static function delete($tipo_id) {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            DELETE FROM documento_tipos 
            WHERE tipo_id = :tipo_id
        ");
        $stmt->execute(['tipo_id' => $tipo_id]);
    }

    /* ============================================================
       VERIFICAR DUPLICADOS
    ============================================================ */
    public static function existeNome(string $nome): bool {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            SELECT tipo_id 
            FROM documento_tipos 
            WHERE nome = :nome 
            LIMIT 1
        ");
        $stmt->execute(['nome' => $nome]);
        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public static function existeNomeParaOutro(string $nome, int $tipo_id): bool {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("
            SELECT tipo_id 
            FROM documento_tipos 
            WHERE nome = :nome 
              AND tipo_id != :tipo_id
            LIMIT 1
        ");
        $stmt->execute([
            'nome' => $nome,
            'tipo_id' => $tipo_id
        ]);

        return (bool) $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
