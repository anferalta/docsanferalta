<?php

namespace App\Models;

use PDO;
use App\Core\Conexao;

class DocumentoEstado
{

    public $id;
    public $codigo;
    public $nome;
    public $ordem = 1;
    public $final = 0;
    public $ativo = 1;

    public static function all()
    {
        $db = Conexao::getInstancia();
        $stmt = $db->query("SELECT * FROM documento_estados ORDER BY ordem ASC, nome ASC");
        return $stmt->fetchAll(\PDO::FETCH_CLASS, self::class);
    }

    public static function find($id)
    {
        $db = Conexao::getInstancia();
        $stmt = $db->prepare("SELECT * FROM documento_estados WHERE id = ?");
        $stmt->execute([$id]);
        $stmt->setFetchMode(\PDO::FETCH_CLASS, self::class);
        return $stmt->fetch();
    }

    public function save()
    {
        $db = Conexao::getInstancia();

        // UPDATE
        if (!empty($this->id)) {
            $stmt = $db->prepare("
                UPDATE documento_estados 
                SET codigo = ?, nome = ?, ordem = ?, final = ?, ativo = ?
                WHERE id = ?
            ");
            return $stmt->execute([
                        $this->codigo,
                        $this->nome,
                        $this->ordem,
                        $this->final,
                        $this->ativo,
                        $this->id
            ]);
        }

        // INSERT
        $stmt = $db->prepare("
            INSERT INTO documento_estados (codigo, nome, ordem, final, ativo)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $this->codigo,
            $this->nome,
            $this->ordem,
            $this->final,
            $this->ativo
        ]);

        $this->id = $db->lastInsertId();
        return true;
    }

    public function delete()
    {
        if (empty($this->id)) {
            return false;
        }

        $db = Conexao::getInstancia();
        $stmt = $db->prepare("DELETE FROM documento_estados WHERE id = ?");
        return $stmt->execute([$this->id]);
    }

    public static function semArquivado()
    {
        $db = Conexao::getInstancia();

        // Estados que não são finais (ex.: arquivado)
        $stmt = $db->query("
        SELECT * 
        FROM documento_estados
        WHERE final = 0 AND ativo = 1
        ORDER BY ordem ASC, nome ASC
    ");

        return $stmt->fetchAll(\PDO::FETCH_CLASS, self::class);
    }

    public static function findByCodigo($codigo)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT * FROM documento_estados WHERE codigo = ? LIMIT 1");
        $stmt->execute([$codigo]);

        return $stmt->fetch(PDO::FETCH_OBJ);
    }
}
