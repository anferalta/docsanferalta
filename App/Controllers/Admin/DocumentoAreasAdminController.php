<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\CSRF;
use App\Core\Sessao;
use App\Models\DocumentoArea;

class DocumentoAreasAdminController extends BaseController
{

    public function index()
    {
        $this->authorize('admin.tramitacao.areas.ver');

        $csrf = CSRF::token();
        $areas = DocumentoArea::todas();

        return $this->render('@admin/documento-areas/index.twig', [
            'areas' => $areas,
            '_csrf' => $csrf
        ]);
    }

    public function criar()
    {
        $this->authorize('admin.tramitacao.areas.criar');

        $csrf = CSRF::token();

        return $this->render('@admin/documento-areas/criar.twig', [
            '_csrf' => $csrf
        ]);
    }

    public function store()
    {
        $this->authorize('admin.tramitacao.areas.criar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-areas/criar');
        }

        $ok = DocumentoArea::criar(
            $_POST['nome'],
            $_POST['codigo'],
            $_POST['descricao'],
            isset($_POST['ativo']) ? 1 : 0,
            (int) $_POST['prazo_resposta']
        );

        if ($ok) {
            Sessao::flash('sucesso', 'Área criada com sucesso.');
        } else {
            Sessao::flash('erro', 'Falha ao criar a área.');
        }

        return $this->redirect('/admin/documento-areas');
    }

    public function editar($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        $area = DocumentoArea::find($id);

        if (!$area) {
            Sessao::flash('erro', 'A área não existe.');
            return $this->redirect('/admin/documento-areas');
        }

        $csrf = CSRF::token();

        return $this->render('@admin/documento-areas/editar.twig', [
            'area' => $area,
            '_csrf' => $csrf
        ]);
    }

    public function update($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect("/admin/documento-areas/editar/$id");
        }

        $ok = DocumentoArea::atualizar(
            $id,
            $_POST['nome'],
            $_POST['codigo'],
            $_POST['descricao'],
            isset($_POST['ativo']) ? 1 : 0,
            (int) $_POST['prazo_resposta']
        );

        if ($ok) {
            Sessao::flash('sucesso', 'Área atualizada com sucesso.');
        } else {
            Sessao::flash('erro', 'Falha ao atualizar a área.');
        }

        return $this->redirect('/admin/documento-areas');
    }

    public function apagar($id)
    {
        $this->authorize('admin.tramitacao.areas.apagar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-areas');
        }

        $area = DocumentoArea::find($id);

        if (!$area) {
            Sessao::flash('erro', 'A área não existe.');
            return $this->redirect('/admin/documento-areas');
        }

        DocumentoArea::apagar($id);

        Sessao::flash('sucesso', 'Área apagada com sucesso.');

        return $this->redirect('/admin/documento-areas');
    }
}
