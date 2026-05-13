<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Sessao;
use App\Core\Validator;
use App\Core\Auth;
use App\Models\Perfil;
use App\Models\Permissao;
use App\Models\PerfilPermissao;
use App\Core\Conexao;

class PerfisController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.perfis.ver');

        $perfis = Perfil::all();

        return $this->render('admin/perfis/index.twig', [
            'title'  => 'Perfis',
            'perfis' => $perfis,
            'user'   => Auth::user()
        ]);
    }

    public function criar()
    {
        $this->authorize('admin.perfis.criar');

        return $this->render('admin/perfis/criar.twig', [
            'title' => 'Criar Perfil',
            'user'  => Auth::user()
        ]);
    }

    public function store()
    {
        $this->authorize('admin.perfis.criar');

        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        $v = new Validator();
        $v->required('nome', $nome, 'O nome é obrigatório.');
        $v->required('slug', $slug, 'O slug é obrigatório.');

        if ($v->hasErrors()) {
            Sessao::flash('erro', $v->firstError());
            return $this->redirect('/admin/perfis/criar');
        }

        (new Perfil())->insert([
            'nome'      => $nome,
            'slug'      => $slug,
            'descricao' => $descricao
        ]);

        Sessao::flash('sucesso', 'Perfil criado com sucesso.');
Audit::log("Criou o perfil '$nome'");        
        return $this->redirect('/admin/perfis');
    }

    public function editar($id)
    {
        $this->authorize('admin.perfis.editar');

        $perfil = Perfil::find($id);
        $permissoes = Permissao::allOrdered();

        // Permissões atribuídas
        $atribuídas = PerfilPermissao::query()
            ->where('perfil_id', '=', $id)
            ->get();

        $ids = array_map(fn($p) => $p->permissao_id, $atribuídas);

        return $this->render('admin/perfis/editar.twig', [
            'title'       => 'Editar Perfil',
            'perfil'      => $perfil,
            'permissoes'  => $permissoes,
            'atribuídas'  => $ids,
            'user'        => Auth::user()
        ]);
    }

    public function update($id)
    {
        $this->authorize('admin.perfis.editar');

        $nome = trim($_POST['nome'] ?? '');
        $slug = trim($_POST['slug'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        (new Perfil())->update([
            'nome'      => $nome,
            'slug'      => $slug,
            'descricao' => $descricao
        ], "id = :id", [':id' => $id]);

        Sessao::flash('sucesso', 'Perfil atualizado.');
Audit::log("Editou o perfil ID $id");        
        return $this->redirect('/admin/perfis');
    }

    public function permissoes($id)
    {
        $this->authorize('admin.perfis.editar');

        // Limpar permissões antigas
        PerfilPermissao::deleteWhere("perfil_id = :id", [':id' => $id]);

        // Inserir novas
        if (!empty($_POST['permissoes'])) {
            foreach ($_POST['permissoes'] as $pid) {
                (new PerfilPermissao())->insert([
                    'perfil_id'    => $id,
                    'permissao_id' => $pid
                ]);
            }
        }

        Sessao::flash('sucesso', 'Permissões atualizadas.');
Audit::log("Alterou permissões do perfil ID $id");        
        return $this->redirect("/admin/perfis/editar/$id");
    }

    public function delete($id)
    {
        $this->authorize('admin.perfis.eliminar');

        Perfil::deleteWhere("id = :id", [':id' => $id]);
        PerfilPermissao::deleteWhere("perfil_id = :id", [':id' => $id]);

        Sessao::flash('sucesso', 'Perfil eliminado.');
Audit::log("Eliminou o perfil ID $id");        
        return $this->redirect('/admin/perfis');
    }
}