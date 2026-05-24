<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class Utilizador extends Model
{

    protected string $table = 'utilizadores';
    protected string $primaryKey = 'id';

    /* ============================================================
     *  PROPRIEDADES (EXATAMENTE COMO NA TABELA)
     * ============================================================ */
    public ?int $id = null;
    public ?string $nome = null;
    public ?string $email = null;
    public ?string $password = null;
    public ?int $perfil_id = null;
    public ?string $ultimo_login = null;
    public ?int $tentativas_falhadas = null;
    public ?int $ativo = null;
    public ?int $aprovado_por = null;
    public ?string $aprovado_em = null;
    public ?string $criado_em = null;

    /* ============================================================
     *  CAMPOS PERMITIDOS
     * ============================================================ */
    protected array $permitidos = [
        'nome',
        'email',
        'password',
        'perfil_id',
        'ultimo_login',
        'tentativas_falhadas',
        'ativo',
        'aprovado_por',
        'aprovado_em',
    ];

    /* ============================================================
     *  CRIAÇÃO
     * ============================================================ */

    public static function create(array $dados): int
    {
        if (!empty($dados['password'])) {
            $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
        }

        return parent::create($dados);
    }

    /* ============================================================
     *  FINDERS
     * ============================================================ */

    public static function findByEmail(string $email): ?Utilizador
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT * FROM utilizadores WHERE email = :email LIMIT 1");
        $stmt->execute(['email' => $email]);

        $data = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$data) {
            return null;
        }

        $u = new Utilizador();

        foreach ($data as $key => $value) {
            if (property_exists($u, $key)) {
                $u->$key = $value;
            }
        }

        return $u;
    }

    /* ============================================================
     *  UPDATE
     * ============================================================ */

    public function updateUser(int $id, array $dados): bool
    {
        $dados = array_intersect_key($dados, array_flip($this->permitidos));

        if (!empty($dados['password'])) {
            $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
        } else {
            unset($dados['password']);
        }

        return $this->update($dados, "id = :id", ['id' => $id]);
    }

    /* ============================================================
     *  DELETE
     * ============================================================ */

    public function deleteUser(int $id): bool
    {
        return $this->delete($id);
    }

    /* ============================================================
     *  PERFIL
     * ============================================================ */

    public function perfil(): ?Perfil
    {
        if (!$this->perfil_id) {
            return null;
        }

        static $cache = [];

        if (!isset($cache[$this->perfil_id])) {
            $cache[$this->perfil_id] = (new Perfil())->find((int) $this->perfil_id);
        }

        return $cache[$this->perfil_id];
    }

    /* ============================================================
     *  PERMISSÕES
     * ============================================================ */

    public function permissoes(): array
    {
        if (!$this->perfil_id) {
            return [];
        }

        $sql = "SELECT p.codigo
                FROM perfis_permissoes pp
                INNER JOIN permissoes p ON p.id = pp.permissao_id
                WHERE pp.perfil_id = :perfil";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute(['perfil' => $this->perfil_id]);

        return array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'codigo');
    }

    /* ============================================================
     *  ADMIN (CORRIGIDO)
     * ============================================================ */

    public function isAdmin()
    {
        return $this->perfil_id == 1;
    }

    public function hasPermissao($permissao)
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permissao, $this->permissoes());
    }

    /* ============================================================
     *  ESTADO
     * ============================================================ */

    public function estadoBadge(): string
    {
        return $this->ativo == 1 ? '<span class="badge bg-success">Ativo</span>' : '<span class="badge bg-secondary">Inativo</span>';
    }

    /* ============================================================
     *  LOGIN
     * ============================================================ */

    public function registarLogin(): void
    {
        $sql = "UPDATE utilizadores 
                SET ultimo_login = NOW(), tentativas_falhadas = 0 
                WHERE id = :id";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute(['id' => $this->id]);
    }
}
