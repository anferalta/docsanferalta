<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Sessao;
use App\Models\Permissao;
use App\Core\Auth;

class PermissoesAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.permissoes.ver');

        $pesquisa = $_GET['q'] ?? '';
        $pagina = isset($_GET['p']) ? (int) $_GET['p'] : 1;
        $porPagina = 20;

        $permissoes = Permissao::paginar($pagina, $porPagina, $pesquisa);
        $total = Permissao::total($pesquisa);
        $paginas = ceil($total / $porPagina);

        $ordem = $_GET['ordem'] ?? 'codigo';
        $dir = $_GET['dir'] ?? 'ASC';

        return $this->render('admin/permissoes/index.twig', [
            'permissoes' => $permissoes,
            'pesquisa' => $pesquisa,
            'pagina' => $pagina,
            'paginas' => $paginas,
            'dir' => $dir
        ]);
    }

    public function criar()
    {
        $this->authorize('admin.permissoes.criar');

        return $this->render('admin/permissoes/criar.twig');
    }

    public function criarSubmit()
    {
        $this->authorize('admin.permissoes.criar');

        $codigo = trim($_POST['codigo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($codigo === '') {
            Sessao::flash('erro', 'O código da permissão é obrigatório.');
            return $this->redirect('/admin/permissoes/criar');
        }

        // Verificar duplicado
        if (Permissao::existeCodigo($codigo)) {
            Sessao::flash('erro', 'Já existe uma permissão com este código.');
            return $this->redirect('/admin/permissoes/criar');
        }

        Permissao::create([
            'codigo' => $codigo,
            'descricao' => $descricao
        ]);

        Sessao::flash('sucesso', 'Permissão criada com sucesso.');
        return $this->redirect('/admin/permissoes');
    }

    public function editar($id)
    {
        $this->authorize('admin.permissoes.editar');

        $permissao = Permissao::find($id);

        if (!$permissao) {
            Sessao::flash('erro', 'Permissão não encontrada.');
            return $this->redirect('/admin/permissoes');
        }

        return $this->render('admin/permissoes/editar.twig', [
            'permissao' => $permissao
        ]);
    }

    public function editarSubmit($id)
    {
        $this->authorize('admin.permissoes.editar');

        $permissao = Permissao::find($id);

        if (!$permissao) {
            Sessao::flash('erro', 'Permissão não encontrada.');
            return $this->redirect('/admin/permissoes');
        }

        $codigo = trim($_POST['codigo'] ?? '');
        $descricao = trim($_POST['descricao'] ?? '');

        if ($codigo === '') {
            Sessao::flash('erro', 'O código da permissão é obrigatório.');
            return $this->redirect("/admin/permissoes/editar/$id");
        }

        // Verificar duplicado
        if (Permissao::existeCodigoEmOutro($codigo, $id)) {
            Sessao::flash('erro', 'Já existe outra permissão com este código.');
            return $this->redirect("/admin/permissoes/editar/$id");
        }

        $permissao->update(
            ['codigo' => $codigo, 'descricao' => $descricao],
            "id = :id",
            ['id' => $id]
        );

        Sessao::flash('sucesso', 'Permissão atualizada.');
        return $this->redirect('/admin/permissoes');
    }

    public function apagar($id)
    {
        $this->authorize('admin.permissoes.apagar');

        $permissao = Permissao::find($id);

        if (!$permissao) {
            Sessao::flash('erro', 'Permissão não encontrada.');
            return $this->redirect('/admin/permissoes');
        }

        // Impedir apagar permissões críticas
        if (str_starts_with($permissao->codigo, 'admin.')) {
            Sessao::flash('erro', 'Permissões administrativas não podem ser apagadas.');
            return $this->redirect('/admin/permissoes');
        }

        $permissao->delete($id);

        Sessao::flash('sucesso', 'Permissão apagada.');
        return $this->redirect('/admin/permissoes');
    }

    public function sincronizar()
    {
        $this->authorize('admin.permissoes.sincronizar');

        Permissao::sincronizar();

        Sessao::flash('sucesso', 'Permissões sincronizadas com sucesso.');
        return $this->redirect('/admin/permissoes');
    }
}