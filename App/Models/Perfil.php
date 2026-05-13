<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class Perfil extends Model
{
    protected string $table = 'perfis';
    protected string $primaryKey = 'id';

    public ?int $id = null;
    public ?string $nome = null;
    public ?string $descricao = null;
    public ?int $ativo = null;
    public ?string $criado_em = null;

    protected array $permitidos = [
        'nome',
        'descricao'
    ];

    /**
     * Permissões associadas ao perfil
     */
    public function permissoes(): array
    {
        $sql = "SELECT p.*
                FROM perfis_permissoes pp
                INNER JOIN permissoes p ON p.id = pp.permissao_id
                WHERE pp.perfil_id = :perfil";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute(['perfil' => $this->id]);

        return $stmt->fetchAll(\PDO::FETCH_CLASS, Permissao::class);
    }

    /**
     * Verifica se o perfil tem uma permissão específica (por código)
     */
    public function temPermissao(string $codigo): bool
    {
        $sql = "SELECT COUNT(*)
                FROM perfis_permissoes pp
                INNER JOIN permissoes p ON p.id = pp.permissao_id
                WHERE pp.perfil_id = :perfil
                AND p.codigo = :codigo";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute([
            'perfil' => $this->id,
            'codigo' => $codigo
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Verifica se o perfil tem uma permissão específica (por ID)
     */
    public function temPermissaoId(int $permissaoId): bool
    {
        $sql = "SELECT COUNT(*)
                FROM perfis_permissoes
                WHERE perfil_id = :perfil
                AND permissao_id = :pid";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute([
            'perfil' => $this->id,
            'pid' => $permissaoId
        ]);

        return (int)$stmt->fetchColumn() > 0;
    }

    /**
     * Apagar perfil + permissões associadas
     */
    public function delete($id): bool
    {
        // Proteger perfil Administrador
        if ((int)$id === 1) {
            return false;
        }

        $db = Conexao::getInstancia();

        // Apagar permissões associadas
        $stmt = $db->prepare("DELETE FROM perfis_permissoes WHERE perfil_id = ?");
        $stmt->execute([$id]);

        // Apagar o perfil
        $stmt = $db->prepare("DELETE FROM perfis WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * Atribuir permissão ao perfil
     */
    public function adicionarPermissao(int $permissaoId): bool
    {
        // Evitar duplicados
        if ($this->temPermissaoId($permissaoId)) {
            return true;
        }

        $sql = "INSERT INTO perfis_permissoes (perfil_id, permissao_id)
                VALUES (:perfil, :permissao)";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute([
            'perfil' => $this->id,
            'permissao' => $permissaoId
        ]);
    }

    /**
     * Remover permissão do perfil
     */
    public function removerPermissao(int $permissaoId): bool
    {
        $sql = "DELETE FROM perfis_permissoes
                WHERE perfil_id = :perfil
                AND permissao_id = :permissao";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute([
            'perfil' => $this->id,
            'permissao' => $permissaoId
        ]);
    }

    /**
     * Listar perfis ordenados
     */
    public static function allOrdered(): array
    {
        return static::query()
            ->orderBy('nome', 'ASC')
            ->get();
    }

    /**
     * Contagem com filtros (para paginação futura)
     */
    public static function countFiltered(array $filtros): int
    {
        $query = static::query();

        if (!empty($filtros['nome'])) {
            $query->where('nome', 'LIKE', '%' . $filtros['nome'] . '%');
        }

        if (!empty($filtros['ativo'])) {
            $query->where('ativo', '=', (int)$filtros['ativo']);
        }

        return $query->count();
    }
}