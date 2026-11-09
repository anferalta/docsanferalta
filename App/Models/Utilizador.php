<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class Utilizador extends Model
{
    protected string $table = 'utilizadores';
    protected string $primaryKey = 'id';

    public $perfil = null;
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

    public static function normalizeEmail(string $email): string
    {
        $email = strtolower(trim($email));
        $email = preg_replace('/\s+/u', '', $email);
        $email = str_replace(["\u{FEFF}", "\u{200B}"], "", $email);
        return $email;
    }

    public static function create(array $dados): int
    {
        if (!empty($dados['password'])) {
            $dados['password'] = password_hash($dados['password'], PASSWORD_DEFAULT);
        }

        return parent::create($dados);
    }

    public static function findByEmail(string $email): ?Utilizador
    {
        $email = self::normalizeEmail($email);
        $db = Conexao::getInstancia();
        // Caminho habitual: usa o índice único de email.
        $stmt = $db->prepare('SELECT * FROM utilizadores WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();
        if ($row && self::normalizeEmail($row['email']) === $email) {
            return new static($row);
        }
        // Compatibilidade com endereços antigos contendo espaços ou caracteres invisíveis.
        $stmt = $db->query('SELECT * FROM utilizadores');
        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            if (self::normalizeEmail($row['email'] ?? '') === $email) {
                $stmt->closeCursor();
                return new static($row);
            }
        }
        return null;
    }

    public static function updatePasswordByEmail(string $email, string $password): bool
    {
        $email = self::normalizeEmail($email);

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
            UPDATE utilizadores
            SET password = :password
            WHERE LOWER(TRIM(email)) = :email
            LIMIT 1
        ");

        return $stmt->execute([
            ':password' => password_hash($password, PASSWORD_DEFAULT),
            ':email'    => $email
        ]);
    }

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

    public function deleteUser(int $id): bool
    {
        return $this->delete($id);
    }

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

    public function isAdmin(): bool
    {
        return $this->perfil_id == 1;
    }

    public function hasPermissao($permissao): bool
    {
        if ($this->isAdmin()) {
            return true;
        }

        return in_array($permissao, $this->permissoes());
    }

    public function estadoBadge(): string
    {
        return $this->ativo == 1
            ? '<span class="badge bg-success">Ativo</span>'
            : '<span class="badge bg-secondary">Inativo</span>';
    }

    public function registarLogin(): void
    {
        $sql = "UPDATE utilizadores 
                SET ultimo_login = NOW(), tentativas_falhadas = 0 
                WHERE id = :id";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute(['id' => $this->id]);
    }
}
