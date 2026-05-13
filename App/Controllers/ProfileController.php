<?php
namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\Sessao;
use App\Core\Helpers;

class ProfileController extends BaseController
{
    private Sessao $sessao;

    public function __construct()
    {
        parent::__construct();
        $this->sessao = new Sessao();
    }

    public function index(): void
    {
        $db = Conexao::getInstancia();
        $perfis = $db->query("SELECT * FROM perfis ORDER BY nome")->fetchAll();

        echo $this->twig->render('perfis/index.twig', [
            'titulo' => 'Gestão de Perfis',
            'perfis' => $perfis
        ]);
    }

    public function criar(): void
    {
        echo $this->twig->render('perfis/criar.twig', [
            'titulo' => 'Criar Perfil'
        ]);
    }

    public function guardar(): void
    {
        $db = Conexao::getInstancia();

        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);

        if ($nome === '') {
            $this->sessao->setFlash("O nome do perfil é obrigatório", "danger");
            header("Location: /admin/perfis/criar");
            exit;
        }

        $stm = $db->prepare("INSERT INTO perfis (nome, descricao) VALUES (:nome, :descricao)");
        $stm->execute([
            'nome' => $nome,
            'descricao' => $descricao
        ]);

        Helpers::log("Criou perfil $nome");

        $this->sessao->setFlash("Perfil criado com sucesso", "success");
        header("Location: /admin/perfis");
        exit;
    }

    public function editar(int $id): void
    {
        $db = Conexao::getInstancia();

        $stm = $db->prepare("SELECT * FROM perfis WHERE id = :id");
        $stm->execute(['id' => $id]);
        $perfil = $stm->fetch();

        if (!$perfil) {
            $this->sessao->setFlash("Perfil não encontrado", "danger");
            header("Location: /admin/perfis");
            exit;
        }

        echo $this->twig->render('perfis/editar.twig', [
            'titulo' => 'Editar Perfil',
            'perfil' => $perfil
        ]);
    }

    public function atualizar(int $id): void
    {
        $db = Conexao::getInstancia();

        $nome = trim($_POST['nome']);
        $descricao = trim($_POST['descricao']);

        if ($nome === '') {
            $this->sessao->setFlash("O nome do perfil é obrigatório", "danger");
            header("Location: /admin/perfis/editar/$id");
            exit;
        }

        $stm = $db->prepare("UPDATE perfis SET nome = :nome, descricao = :descricao WHERE id = :id");
        $stm->execute([
            'nome' => $nome,
            'descricao' => $descricao,
            'id' => $id
        ]);

        Helpers::log("Atualizou perfil ID $id");

        $this->sessao->setFlash("Perfil atualizado com sucesso", "success");
        header("Location: /admin/perfis");
        exit;
    }

    public function permissoes(int $id): void
    {
        $db = Conexao::getInstancia();

        $stm = $db->prepare("SELECT * FROM perfis WHERE id = :id");
        $stm->execute(['id' => $id]);
        $perfil = $stm->fetch();

        if (!$perfil) {
            $this->sessao->setFlash("Perfil não encontrado", "danger");
            header("Location: /admin/perfis");
            exit;
        }

        $permissoes = $db->query("SELECT * FROM permissoes ORDER BY codigo")->fetchAll();

        $stm2 = $db->prepare("SELECT permissao_id FROM perfil_permissoes WHERE perfil_id = :id");
        $stm2->execute(['id' => $id]);
        $permissoesPerfil = array_column($stm2->fetchAll(), 'permissao_id');

        echo $this->twig->render('perfis/permissoes.twig', [
            'titulo' => 'Permissões do Perfil',
            'perfil' => $perfil,
            'permissoes' => $permissoes,
            'permissoesPerfil' => $permissoesPerfil
        ]);
    }

    public function guardarPermissoes(int $id): void
    {
        $db = Conexao::getInstancia();

        $db->prepare("DELETE FROM perfil_permissoes WHERE perfil_id = :id")
           ->execute(['id' => $id]);

        if (!empty($_POST['permissoes'])) {
            foreach ($_POST['permissoes'] as $perm) {
                $stm = $db->prepare("INSERT INTO perfil_permissoes (perfil_id, permissao_id) VALUES (:p, :perm)");
                $stm->execute(['p' => $id, 'perm' => $perm]);
            }
        }

        Helpers::log("Atualizou permissões do perfil ID $id");

        $this->sessao->setFlash("Permissões atualizadas com sucesso", "success");
        header("Location: /admin/perfis/permissoes/$id");
        exit;
    }

    public function eliminar(int $id): void
    {
        if ($id == 1) {
            $this->sessao->setFlash("Não é possível eliminar o perfil principal", "danger");
            header("Location: /admin/perfis");
            exit;
        }

        $db = Conexao::getInstancia();

        $stm = $db->prepare("DELETE FROM perfis WHERE id = :id");
        $stm->execute(['id' => $id]);

        Helpers::log("Eliminou perfil ID $id");

        $this->sessao->setFlash("Perfil eliminado com sucesso", "success");
        header("Location: /admin/perfis");
        exit;
    }
}