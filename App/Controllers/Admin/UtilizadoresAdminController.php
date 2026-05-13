<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\Sessao;
use App\Core\Helpers;
use App\Core\Auth;

class UtilizadoresAdminController extends BaseController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * LISTAGEM COM FILTROS + PAGINAÇÃO
     */
    public function index() {
        $this->authorize('admin.utilizadores.ver');

        $db = Conexao::getInstancia();

        // Filtros
        $nome = $_GET['nome'] ?? '';
        $email = $_GET['email'] ?? '';
        $ativo = $_GET['ativo'] ?? '';

        // Paginação
        $pagina = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $porPagina = 10;
        $offset = ($pagina - 1) * $porPagina;

        // Construção dinâmica da query
        $where = [];
        $params = [];

        if ($nome !== '') {
            $where[] = "u.nome LIKE :nome";
            $params[':nome'] = "%{$nome}%";
        }

        if ($email !== '') {
            $where[] = "u.email LIKE :email";
            $params[':email'] = "%{$email}%";
        }

        if ($ativo !== '') {
            $where[] = "u.ativo = :ativo";
            $params[':ativo'] = (int) $ativo;
        }

        $whereSql = $where ? "WHERE " . implode(" AND ", $where) : "";

        // Total de registos
        $stmtTotal = $db->prepare("
            SELECT COUNT(*) 
            FROM utilizadores u
            $whereSql
        ");
        $stmtTotal->execute($params);
        $totalRegistos = $stmtTotal->fetchColumn();

        $totalPaginas = max(1, ceil($totalRegistos / $porPagina));

        // Query principal
        $sql = "
            SELECT u.*, p.nome AS perfil_nome
            FROM utilizadores u
            LEFT JOIN perfis p ON p.id = u.perfil_id
            $whereSql
            ORDER BY u.id DESC
            LIMIT :limit OFFSET :offset
        ";

        $stmt = $db->prepare($sql);

        // Bind dos filtros
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        // Bind da paginação
        $stmt->bindValue(':limit', $porPagina, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();
        $utilizadores = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('admin/utilizadores/index.twig', [
                    'utilizadores' => $utilizadores,
                    'pagina' => $pagina,
                    'total_paginas' => $totalPaginas,
                    'nome' => $nome,
                    'email' => $email,
                    'ativo' => $ativo
        ]);
    }

    /**
     * FORMULÁRIO DE CRIAÇÃO
     */
    public function criar() {
        $this->authorize('admin.utilizadores.criar');

        $db = Conexao::getInstancia();
        $perfis = $db->query("SELECT id, nome FROM perfis ORDER BY nome ASC")
                ->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('admin/utilizadores/criar.twig', [
                    'perfis' => $perfis
        ]);
    }

    /**
     * SUBMISSÃO DE CRIAÇÃO
     */
    public function criarSubmit() {
        $this->authorize('admin.utilizadores.criar');

        $dados = $_POST;

        if (empty($dados['nome']) || empty($dados['email']) || empty($dados['password']) || empty($dados['perfil_id'])) {
            Sessao::flash('erro', 'Preencha todos os campos obrigatórios.');
            Helpers::redirect('/admin/utilizadores/criar');
        }

        $db = Conexao::getInstancia();

        // Verificar duplicação de email
        $stmt = $db->prepare("SELECT id FROM utilizadores WHERE email = ?");
        $stmt->execute([$dados['email']]);

        if ($stmt->fetch()) {
            Sessao::flash('erro', 'O email já está registado. Escolha outro.');
            Helpers::redirect('/admin/utilizadores/criar');
        }

        // Inserção
        $stmt = $db->prepare("
            INSERT INTO utilizadores (nome, email, password, ativo, perfil_id)
            VALUES (:nome, :email, :password, :ativo, :perfil_id)
        ");

        $stmt->execute([
            ':nome' => $dados['nome'],
            ':email' => $dados['email'],
            ':password' => password_hash($dados['password'], PASSWORD_DEFAULT),
            ':ativo' => 1, // Utilizador novo começa sempre pendente
            ':perfil_id' => $dados['perfil_id']
        ]);

        Sessao::flash('sucesso', 'Utilizador criado com sucesso.');
        Helpers::redirect('/admin/utilizadores');
    }

    /**
     * APAGAR UTILIZADOR
     */
    public function eliminar($id) {
        $this->authorize('admin.utilizadores.apagar');

        // Impedir que um utilizador apague a si próprio
        if (Auth::user()->id == $id) {
            Sessao::flash('erro', 'Não pode apagar o seu próprio utilizador.');
            Helpers::redirect('/admin/utilizadores');
        }

        // Buscar utilizador
        $utilizador = \App\Models\Utilizador::find($id);

        if (!$utilizador) {
            http_response_code(404);
            echo "Utilizador não encontrado.";
            return;
        }

        // Impedir apagar perfis superiores
        $this->bloquearEdicaoDePerfisSuperiores($utilizador);

        // APAGAR COM AUDITORIA
        $utilizadorModel = new \App\Models\Utilizador();
        $utilizadorModel->delete($id);

        Sessao::flash('sucesso', 'Utilizador apagado com sucesso.');
        Helpers::redirect('/admin/utilizadores');
    }

    /**
     * LISTAR UTILIZADORES PENDENTES
     */
    public function pendentes() {
        $this->authorize('admin.utilizadores.ver');

        $db = Conexao::getInstancia();

        // Filtros
        $nome = $_GET['nome'] ?? '';
        $email = $_GET['email'] ?? '';

        $pagina = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $porPagina = 10;
        $offset = ($pagina - 1) * $porPagina;

        // Pendentes = ativo = 0 AND aprovado_em IS NULL
        $where = ["u.ativo = 0", "u.aprovado_em IS NULL"];
        $params = [];

        if ($nome !== '') {
            $where[] = "u.nome LIKE :nome";
            $params[':nome'] = "%{$nome}%";
        }

        if ($email !== '') {
            $where[] = "u.email LIKE :email";
            $params[':email'] = "%{$email}%";
        }

        $whereSql = "WHERE " . implode(" AND ", $where);

        // Total
        $stmtTotal = $db->prepare("
        SELECT COUNT(*) 
        FROM utilizadores u
        $whereSql
    ");
        $stmtTotal->execute($params);
        $total = $stmtTotal->fetchColumn();
        $totalPaginas = max(1, ceil($total / $porPagina));

        // Query principal
        $sql = "
        SELECT u.*, 
               p.nome AS perfil_nome,
               a.nome AS aprovado_por_nome
        FROM utilizadores u
        LEFT JOIN perfis p ON p.id = u.perfil_id
        LEFT JOIN utilizadores a ON a.id = u.aprovado_por
        $whereSql
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $db->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->bindValue(':limit', $porPagina, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();

        // 🟩 ESTA LINHA FALTAVA!
        $pendentes = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Perfis para o modal
        $perfis = $db->query("SELECT id, nome FROM perfis ORDER BY nome")->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('@admin/utilizadores/pendentes.twig', [
                    'pendentes' => $pendentes,
                    'pagina' => $pagina,
                    'total_paginas' => $totalPaginas,
                    'nome' => $nome,
                    'email' => $email,
                    'perfis' => $perfis
        ]);
    }

    /**
     * APROVAR UTILIZADOR
     */
    public function aprovar($id) {
        $perfil = $_POST['perfil_id'] ?? null;

        if (!$perfil) {
            Sessao::flash('erro', 'Selecione um perfil.');
            Helpers::redirect('/admin/utilizadores/pendentes');
        }

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
        UPDATE utilizadores
        SET ativo = 1,
            aprovado_em = NOW(),
            aprovado_por = :admin,
            perfil_id = :perfil
        WHERE id = :id
    ");

        $stmt->execute([
            ':id' => $id,
            ':admin' => Auth::id(),
            ':perfil' => $perfil
        ]);

        Sessao::flash('sucesso', 'Utilizador aprovado com sucesso.');
        Helpers::redirect('/admin/utilizadores/pendentes');
    }

    /**
     * REJEITAR UTILIZADOR (APAGAR)
     */
    public function rejeitar($id) {
        $this->authorize('admin.utilizadores.apagar');

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("DELETE FROM utilizadores WHERE id = ?");
        $stmt->execute([$id]);

        Sessao::flash('sucesso', 'Utilizador rejeitado e removido.');
        Helpers::redirect('/admin/utilizadores/pendentes');
    }

    /**
     * BLOQUEAR UTILIZADOR
     */
    public function bloquear($id) {
        $this->authorize('admin.utilizadores.editar');

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
        UPDATE utilizadores
        SET ativo = 0
        WHERE id = :id
    ");

        $stmt->execute([':id' => $id]);

        Sessao::flash('sucesso', 'Utilizador bloqueado.');
        $this->redirect('/admin/utilizadores/ativos');
    }

    public function reativar($id) {
        $this->authorize('admin.utilizadores.editar');

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
        UPDATE utilizadores
        SET ativo = 1
        WHERE id = :id
    ");

        $stmt->execute([':id' => $id]);

        Sessao::flash('sucesso', 'Utilizador reativado.');
        $this->redirect('/admin/utilizadores/bloqueados');
    }

    /**
     * DESBLOQUEAR UTILIZADOR
     */
    public function desbloquear($id) {
        $this->authorize('admin.utilizadores.editar');

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("UPDATE utilizadores SET ativo = 1 WHERE id = ?");
        $stmt->execute([$id]);

        Sessao::flash('sucesso', 'Utilizador desbloqueado.');
        Helpers::redirect('/admin/utilizadores');
    }

    public function ativos() {
        $this->authorize('admin.utilizadores.ver');

        $db = Conexao::getInstancia();

        // Filtros
        $nome = $_GET['nome'] ?? '';
        $email = $_GET['email'] ?? '';

        $pagina = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;
        $porPagina = 10;
        $offset = ($pagina - 1) * $porPagina;

        $where = ["u.ativo = 1"];
        $params = [];

        if ($nome !== '') {
            $where[] = "u.nome LIKE :nome";
            $params[':nome'] = "%{$nome}%";
        }

        if ($email !== '') {
            $where[] = "u.email LIKE :email";
            $params[':email'] = "%{$email}%";
        }

        $whereSql = "WHERE " . implode(" AND ", $where);

        // Total
        $stmtTotal = $db->prepare("SELECT COUNT(*) FROM utilizadores u $whereSql");
        $stmtTotal->execute($params);
        $total = $stmtTotal->fetchColumn();
        $totalPaginas = max(1, ceil($total / $porPagina));

        // Query principal
        $sql = "
        SELECT u.*, p.nome AS perfil_nome
        FROM utilizadores u
        LEFT JOIN perfis p ON p.id = u.perfil_id
        $whereSql
        ORDER BY u.id DESC
        LIMIT :limit OFFSET :offset
    ";

        $stmt = $db->prepare($sql);

        foreach ($params as $k => $v) {
            $stmt->bindValue($k, $v);
        }

        $stmt->bindValue(':limit', $porPagina, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);

        $stmt->execute();
        $ativos = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('admin/utilizadores/ativos.twig', [
                    'ativos' => $ativos,
                    'pagina' => $pagina,
                    'total_paginas' => $totalPaginas,
                    'nome' => $nome,
                    'email' => $email
        ]);
    }

    public function bloqueados() {
        $this->authorize('admin.utilizadores.ver');

        $db = Conexao::getInstancia();

        $stmt = $db->query("
        SELECT u.*, p.nome AS perfil_nome
        FROM utilizadores u
        LEFT JOIN perfis p ON p.id = u.perfil_id
        WHERE u.ativo = 0 AND u.aprovado_em IS NOT NULL
        ORDER BY u.id DESC
    ");

        $bloqueados = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        return $this->render('admin/utilizadores/bloqueados.twig', [
                    'bloqueados' => $bloqueados
        ]);
    }

    public function exportarCSV() {
        $this->authorize('admin.utilizadores.ver');

        $db = Conexao::getInstancia();
        $dados = $db->query("SELECT id, nome, email, ativo, criado_em FROM utilizadores ORDER BY id DESC")
                ->fetchAll(\PDO::FETCH_ASSOC);

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="utilizadores.csv"');

        $f = fopen('php://output', 'w');
        fputcsv($f, ['ID', 'Nome', 'Email', 'Ativo', 'Criado em']);

        foreach ($dados as $linha) {
            fputcsv($f, $linha);
        }

        exit;
    }

    /**
     * FORMULÁRIO DE EDIÇÃO
     */
    public function editar($id) {
        $this->authorize('admin.utilizadores.editar');

        $db = Conexao::getInstancia();

        $stm = $db->prepare("SELECT * FROM utilizadores WHERE id = ?");
        $stm->execute([$id]);
        $utilizador = $stm->fetch();

        if (!$utilizador) {
            http_response_code(404);
            echo "Utilizador não encontrado.";
            return;
        }

        $this->bloquearEdicaoDePerfisSuperiores($utilizador);

        $stmPerfis = $db->query("SELECT id, nome FROM perfis ORDER BY nome ASC");
        $perfis = $stmPerfis->fetchAll();

        return $this->render('admin/utilizadores/editar.twig', [
                    'utilizador' => $utilizador,
                    'perfis' => $perfis
        ]);
    }

    /**
     * SUBMISSÃO DE EDIÇÃO
     */
    public function editarSubmit($id) {
        $this->authorize('admin.utilizadores.editar');

        $db = Conexao::getInstancia();

        $stm = $db->prepare("SELECT * FROM utilizadores WHERE id = ?");
        $stm->execute([$id]);
        $utilizador = $stm->fetch();

        if (!$utilizador) {
            http_response_code(404);
            echo "Utilizador não encontrado.";
            return;
        }

        $this->bloquearEdicaoDePerfisSuperiores($utilizador);

        $nome = $_POST['nome'] ?? '';
        $email = $_POST['email'] ?? '';
        $perfil_id = $_POST['perfil_id'] ?? null;
        $ativo = isset($_POST['ativo']) ? (int) $_POST['ativo'] : 1;

        // Verificar duplicação de email
        $check = $db->prepare("SELECT id FROM utilizadores WHERE email = :email AND id != :id");
        $check->execute([
            ':email' => $email,
            ':id' => $id
        ]);

        if ($check->fetch()) {
            Sessao::flash('erro', 'Já existe um utilizador com este email.');
            $this->redirect("/admin/utilizadores/editar/$id");
            return;
        }

        // Atualizar utilizador
        $sql = "UPDATE utilizadores 
                SET nome = :nome,
                    email = :email,
                    perfil_id = :perfil_id,
                    ativo = :ativo
                WHERE id = :id";

        $stm = $db->prepare($sql);

        $stm->execute([
            ':nome' => $nome,
            ':email' => $email,
            ':perfil_id' => $perfil_id,
            ':ativo' => $ativo,
            ':id' => $id
        ]);

        Sessao::flash('sucesso', 'Utilizador atualizado com sucesso.');
        $this->redirect('/admin/utilizadores');
    }

    /**
     * BLOQUEAR EDIÇÃO/APAGAR PERFIS SUPERIORES
     */
    private function bloquearEdicaoDePerfisSuperiores($utilizador) {
        $user = Auth::user();

        if (empty($user->is_admin) || !$user->is_admin) {
            if (!empty($utilizador->is_admin) && $utilizador->is_admin) {
                http_response_code(403);
                die("Acesso negado.");
            }
        }
    }
}
