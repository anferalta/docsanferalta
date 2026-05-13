<?php

namespace App\Core;

use App\Core\Conexao;
use App\Core\Auth;

class Acl {

    private array $permissoes = [];
    private ?int $userId = null;

    public function __construct(?int $userId = null) {
        $user = Auth::user();

        // Se não houver utilizador autenticado, ACL fica vazia e sai
        if (!$user) {
            $this->permissoes = [];
            $this->userId = null;
            return;
        }

        // Garantir que o ID é um inteiro válido
        $this->userId = $userId ?? ($user->id ?? null);

        // Se ainda assim não houver ID, sair
        if ($this->userId === null) {
            $this->permissoes = [];
            return;
        }

        // Carregar permissões
        $this->carregarPermissoes();
    }

    private function carregarPermissoes(): void {
        $db = Conexao::getInstancia();

        // Buscar perfil do utilizador
        $stmt = $db->prepare("SELECT perfil_id FROM utilizadores WHERE id = :id");
        $stmt->execute([':id' => $this->userId]);
        $perfilId = $stmt->fetchColumn();

        if (!$perfilId) {
            $this->permissoes = [];
            return;
        }

        // 1) Permissões do perfil
        $sqlPerfil = "
        SELECT p.codigo
        FROM perfis_permissoes pp
        JOIN permissoes p ON p.id = pp.permissao_id
        WHERE pp.perfil_id = :perfil_id
    ";

        $stmt = $db->prepare($sqlPerfil);
        $stmt->execute([':perfil_id' => $perfilId]);
        $permissoesPerfil = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'codigo');

        // 2) Permissões diretas
        $sqlDiretas = "
        SELECT p.codigo
        FROM utilizadores_permissoes up
        JOIN permissoes p ON p.id = up.permissao_id
        WHERE up.utilizador_id = :user_id
    ";

        $stmt = $db->prepare($sqlDiretas);
        $stmt->execute([':user_id' => $this->userId]);
        $permissoesDiretas = array_column($stmt->fetchAll(\PDO::FETCH_ASSOC), 'codigo');

        // 3) Unir
        $this->permissoes = array_unique(array_merge($permissoesPerfil, $permissoesDiretas));
    }

    public function has(string $codigo): bool {
        return in_array($codigo, $this->permissoes, true);
    }
}