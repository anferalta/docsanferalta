<?php

namespace App\Services;

use App\Core\Conexao;
use App\Models\Auditoria;
use DomainException;
use InvalidArgumentException;
use PDO;
use Throwable;

class PerfisService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Conexao::getInstancia();
    }

    public function validarId(mixed $id): int
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $id)
            || filter_var($id, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Identificador de perfil inválido.');
        }
        return (int) $id;
    }

    public function listar(): array
    {
        return $this->db->query('SELECT p.*, COALESCE(pp.total, 0) AS total_permissoes,
            COALESCE(u.total, 0) AS total_utilizadores FROM perfis p
            LEFT JOIN (SELECT perfil_id, COUNT(*) AS total FROM perfis_permissoes GROUP BY perfil_id) pp ON pp.perfil_id = p.id
            LEFT JOIN (SELECT perfil_id, COUNT(*) AS total FROM utilizadores GROUP BY perfil_id) u ON u.perfil_id = p.id
            ORDER BY p.nome, p.id')->fetchAll(PDO::FETCH_ASSOC);
    }

    public function obter(mixed $id, bool $alteravel = false, bool $bloquear = false): array
    {
        $id = $this->validarId($id);
        $sql = 'SELECT * FROM perfis WHERE id = ?';
        if ($bloquear && $this->db->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
            $sql .= ' FOR UPDATE';
        }
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$id]);
        $perfil = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$perfil) {
            throw new DomainException('Perfil não encontrado.');
        }
        if ($alteravel && $id === 1) {
            throw new DomainException('O perfil Administrador e as suas permissões estão protegidos.');
        }
        return $perfil;
    }

    public function permissoesAtuais(mixed $id): array
    {
        $perfil = $this->obter($id);
        $stmt = $this->db->prepare('SELECT permissao_id FROM perfis_permissoes WHERE perfil_id = ? ORDER BY permissao_id');
        $stmt->execute([$perfil['id']]);
        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function validarDados(array $entrada): array
    {
        $dados = [];
        foreach (['nome' => 50, 'descricao' => 255] as $campo => $limite) {
            $valor = $entrada[$campo] ?? '';
            if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || str_contains($valor, "\0")) {
                throw new InvalidArgumentException('O nome e a descrição devem ser texto válido.');
            }
            $valor = trim($valor);
            if (mb_strlen($valor, 'UTF-8') > $limite) {
                throw new InvalidArgumentException("O campo {$campo} não pode exceder {$limite} caracteres.");
            }
            $dados[$campo] = $valor;
        }
        if ($dados['nome'] === '') {
            throw new InvalidArgumentException('O nome do perfil é obrigatório.');
        }
        return $dados;
    }

    private function validarNomeUnico(string $nome, int $id = 0): void
    {
        $stmt = $this->db->prepare('SELECT id FROM perfis WHERE nome = ? AND id <> ?');
        $stmt->execute([$nome, $id]);
        if ($stmt->fetchColumn() !== false) {
            throw new InvalidArgumentException('Já existe um perfil com este nome.');
        }
    }

    public function criar(array $entrada, ?int $autor): int
    {
        $dados = $this->validarDados($entrada);
        return $this->transacao(function () use ($dados, $autor) {
            $this->validarNomeUnico($dados['nome']);
            $stmt = $this->db->prepare('INSERT INTO perfis (nome, descricao) VALUES (?, ?)');
            $stmt->execute([$dados['nome'], $dados['descricao']]);
            $id = (int) $this->db->lastInsertId();
            Auditoria::registar('Criou perfil: ' . $dados['nome'], $autor);
            return $id;
        });
    }

    public function atualizar(mixed $id, array $entrada, ?int $autor): void
    {
        $dados = $this->validarDados($entrada);
        $this->transacao(function () use ($id, $dados, $autor) {
            $perfil = $this->obter($id, true, true);
            $this->validarNomeUnico($dados['nome'], (int) $perfil['id']);
            $stmt = $this->db->prepare('UPDATE perfis SET nome = ?, descricao = ? WHERE id = ?');
            $stmt->execute([$dados['nome'], $dados['descricao'], $perfil['id']]);
            Auditoria::registar('Editou perfil ID ' . $perfil['id'], $autor);
        });
    }

    public function apagar(mixed $id, ?int $autor): void
    {
        $this->transacao(function () use ($id, $autor) {
            $perfil = $this->obter($id, true, true);
            $stmt = $this->db->prepare('SELECT COUNT(*) FROM utilizadores WHERE perfil_id = ?');
            $stmt->execute([$perfil['id']]);
            if ((int) $stmt->fetchColumn() > 0) {
                throw new DomainException('Este perfil está associado a utilizadores. Reatribua-os antes de o apagar.');
            }
            $this->db->prepare('DELETE FROM perfis_permissoes WHERE perfil_id = ?')->execute([$perfil['id']]);
            $this->db->prepare('DELETE FROM perfis WHERE id = ?')->execute([$perfil['id']]);
            Auditoria::registar('Apagou perfil ID ' . $perfil['id'], $autor);
        });
    }

    public function guardarPermissoes(mixed $id, mixed $entrada, ?int $autor): void
    {
        if (!is_array($entrada)) {
            throw new InvalidArgumentException('A lista de permissões é inválida.');
        }
        $ids = [];
        foreach ($entrada as $valor) {
            $ids[] = $this->validarId($valor);
        }
        $ids = array_values(array_unique($ids));
        $this->transacao(function () use ($id, $ids, $autor) {
            $perfil = $this->obter($id, true, true);
            $existentes = array_map('intval', $this->db->query('SELECT id FROM permissoes')->fetchAll(PDO::FETCH_COLUMN));
            if (array_diff($ids, $existentes)) {
                throw new InvalidArgumentException('A lista contém permissões que não existem.');
            }
            $this->db->prepare('DELETE FROM perfis_permissoes WHERE perfil_id = ?')->execute([$perfil['id']]);
            $stmt = $this->db->prepare('INSERT INTO perfis_permissoes (perfil_id, permissao_id) VALUES (?, ?)');
            foreach ($ids as $permissao) {
                $stmt->execute([$perfil['id'], $permissao]);
            }
            Auditoria::registar('Alterou permissões do perfil ID ' . $perfil['id'], $autor);
        });
    }

    private function transacao(callable $acao): mixed
    {
        $this->db->beginTransaction();
        try {
            $resultado = $acao();
            $this->db->commit();
            return $resultado;
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;
        }
    }
}
