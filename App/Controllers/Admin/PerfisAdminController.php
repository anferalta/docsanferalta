<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\Auditoria;
use App\Core\Conexao;
use App\Core\Sessao;
use App\Core\Auth;

class PerfisAdminController extends BaseController {

    public function index() {
        $this->authorize('admin.perfis.ver');

        $perfis = Perfil::all();

        return $this->render('admin/perfis/index.twig', [
                    'perfis' => $perfis
        ]);
    }

    public function criar() {
        $this->authorize('admin.perfis.criar');

        $todas = Permissao::allOrdered(); // todas as permissões agrupadas
        $atuais = []; // nenhum selecionado ao criar
        $perfis = Perfil::all(); // para o select da descrição

        return $this->render('admin/perfis/criar.twig', [
                    'todas' => $todas,
                    'atuais' => $atuais,
                    'perfis' => $perfis
        ]);
    }

    public function criarSubmit() {
        $this->authorize('admin.perfis.criar');

        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome do perfil é obrigatório.');
            return $this->redirect('/admin/perfis/criar');
        }

        // Verificar duplicado
        $db = Conexao::getInstancia();
        $check = $db->prepare("SELECT id FROM perfis WHERE nome = ?");
        $check->execute([$nome]);

        if ($check->fetch()) {
            Sessao::flash('erro', 'Já existe um perfil com este nome.');
            return $this->redirect('/admin/perfis/criar');
        }

        Perfil::create([
            'nome' => $nome,
            'descricao' => $descricao
        ]);

        Auditoria::registar("Criou perfil: $nome", Auth::user()->id);

        Sessao::flash('sucesso', 'Perfil criado com sucesso.');
        return $this->redirect('/admin/perfis');
    }

    public function editar($id) {
        $this->authorize('admin.perfis.editar');

        $perfil = Perfil::find($id);
        $todas = Permissao::allOrdered();
        $atuais = array_column($perfil->permissoes(), 'id');
        $perfis = Perfil::all();

        if (!$perfil) {
            Sessao::flash('erro', 'Perfil não encontrado.');
            return $this->redirect('/admin/perfis');
        }

        if ($perfil->id == 1) {
            Sessao::flash('erro', 'O perfil Administrador não pode ser editado.');
            return $this->redirect('/admin/perfis');
        }

        $perfis = Perfil::all(); // <-- ADICIONAR

        return $this->render('admin/perfis/editar.twig', [
                    'perfil' => $perfil,
                    'perfis' => $perfis,
                    'todas' => $todas,
                    'atuais' => $atuais
        ]);
    }

    public function editarSubmit($id) {
        $this->authorize('admin.perfis.editar');

        $perfil = Perfil::find($id);

        if (!$perfil) {
            Sessao::flash('erro', 'Perfil não encontrado.');
            return $this->redirect('/admin/perfis');
        }

        if ($perfil->id == 1) {
            Sessao::flash('erro', 'O perfil Administrador não pode ser editado.');
            return $this->redirect('/admin/perfis');
        }

        $nome = trim($_POST['nome'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome do perfil é obrigatório.');
            return $this->redirect("/admin/perfis/editar/$id");
        }

        // Verificar duplicado
        $db = Conexao::getInstancia();
        $check = $db->prepare("SELECT id FROM perfis WHERE nome = :nome AND id != :id");
        $check->execute([':nome' => $nome, ':id' => $id]);

        if ($check->fetch()) {
            Sessao::flash('erro', 'Já existe um perfil com este nome.');
            return $this->redirect("/admin/perfis/editar/$id");
        }

        $perfil->update(
                ['nome' => $nome, 'descricao' => $descricao],
                "id = :id",
                ['id' => $id]
        );

        Auditoria::registar("Editou perfil ID $id", Auth::user()->id);

        Sessao::flash('sucesso', 'Perfil atualizado com sucesso.');
        return $this->redirect('/admin/perfis');
    }

    public function apagar($id) {
        $this->authorize('admin.perfis.apagar');

        $perfil = Perfil::find($id);

        if (!$perfil) {
            Sessao::flash('erro', 'Perfil não encontrado.');
            return $this->redirect('/admin/perfis');
        }

        if ($perfil->id == 1) {
            Sessao::flash('erro', 'O perfil Administrador não pode ser apagado.');
            return $this->redirect('/admin/perfis');
        }

        $perfil->delete($perfil->id);

        Sessao::flash('sucesso', 'Perfil apagado com sucesso.');
        return $this->redirect('/admin/perfis');
    }

    public function permissoes($id) {
        $this->authorize('admin.perfis.permissoes');

        $perfil = Perfil::find($id);

        if (!$perfil) {
            Sessao::flash('erro', 'Perfil não encontrado.');
            return $this->redirect('/admin/perfis');
        }

        if ($perfil->id == 1) {
            Sessao::flash('erro', 'As permissões do Administrador não podem ser alteradas.');
            return $this->redirect('/admin/perfis');
        }

        $todas = Permissao::allOrdered();
        $atuais = $perfil->permissoes();

        return $this->render('admin/perfis/permissoes.twig', [
                    'perfil' => $perfil,
                    'todas' => $todas,
                    'atuais' => array_column($atuais, 'id')
        ]);
    }

    public function permissoesSubmit($id) {
        $this->authorize('admin.perfis.permissoes');

        $perfil = Perfil::find($id);

        if (!$perfil) {
            Sessao::flash('erro', 'Perfil não encontrado.');
            return $this->redirect('/admin/perfis');
        }

        if ($perfil->id == 1) {
            Sessao::flash('erro', 'As permissões do Administrador não podem ser alteradas.');
            return $this->redirect('/admin/perfis');
        }

        $db = Conexao::getInstancia();
        $db->prepare("DELETE FROM perfis_permissoes WHERE perfil_id = :id")
                ->execute(['id' => $id]);

        $permissoes = $_POST['permissoes'] ?? [];

        foreach ($permissoes as $pid) {
            $perfil->adicionarPermissao((int) $pid);
        }

        Auditoria::registar("Alterou permissões do perfil ID $id", Auth::user()->id);

        Sessao::flash('sucesso', 'Permissões atualizadas.');
        return $this->redirect("/admin/perfis/permissoes/$id");
    }
}
