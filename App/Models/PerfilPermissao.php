<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class PerfilPermissao extends Model
{
    protected string $table = 'perfis_permissoes';

    public ?int $perfil_id = null;
    public ?int $permissao_id = null;

    /**
     * Devolve todas as permissões agrupadas por perfil.
     * Estrutura:
     * [
     *     perfil_id => [permissao_id, permissao_id, ...]
     * ]
     */
    public static function allGrouped(): array
    {
        $sql = "SELECT perfil_id, permissao_id FROM perfis_permissoes";

        $stmt = Conexao::getInstancia()->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $result = [];

        foreach ($rows as $row) {
            $perfil = (int)$row['perfil_id'];
            $perm   = (int)$row['permissao_id'];

            if (!isset($result[$perfil])) {
                $result[$perfil] = [];
            }

            $result[$perfil][] = $perm;
        }

        return $result;
    }

    /**
     * Devolve todas as permissões de um perfil específico
     */
    public static function getByPerfil(int $perfilId): array
    {
        $sql = "SELECT permissao_id 
                FROM perfis_permissoes 
                WHERE perfil_id = :perfil";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute(['perfil' => $perfilId]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'permissao_id');
    }

    /**
     * Verifica se um perfil tem uma permissão específica
     */
    public static function exists(int $perfilId, int $permissaoId): bool
    {
        $sql = "SELECT COUNT(*) 
                FROM perfis_permissoes 
                WHERE perfil_id = :perfil 
                AND permissao_id = :perm";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute([
            'perfil' => $perfilId,
            'perm'   => $permissaoId
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Adiciona permissão ao perfil (com proteção contra duplicados)
     */
    public static function add(int $perfilId, int $permissaoId): bool
    {
        if (self::exists($perfilId, $permissaoId)) {
            return true;
        }

        $sql = "INSERT INTO perfis_permissoes (perfil_id, permissao_id)
                VALUES (:perfil, :perm)";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute([
            'perfil' => $perfilId,
            'perm'   => $permissaoId
        ]);
    }

    /**
     * Remove permissão do perfil
     */
    public static function remove(int $perfilId, int $permissaoId): bool
    {
        $sql = "DELETE FROM perfis_permissoes
                WHERE perfil_id = :perfil
                AND permissao_id = :perm";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute([
            'perfil' => $perfilId,
            'perm'   => $permissaoId
        ]);
    }

    /**
     * Remove todas as permissões de um perfil
     */
    public static function removeAll(int $perfilId): bool
    {
        $sql = "DELETE FROM perfis_permissoes WHERE perfil_id = :perfil";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute(['perfil' => $perfilId]);
    }
}