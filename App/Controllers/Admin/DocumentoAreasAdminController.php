<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\DocumentoArea;

class DocumentoAreasAdminController extends BaseController
{

    public function index()
    {
        $this->authorize('admin.tramitacao.areas.ver');

        $areas = DocumentoArea::todas();

        return $this->render('@admin/documento-areas/index.twig', [
                    'areas' => $areas
        ]);
    }

    public function criar()
    {
        $this->authorize('admin.tramitacao.areas.criar');

        return $this->render('@admin/documento-areas/criar.twig');
    }

    public function store()
    {
        $this->authorize('admin.tramitacao.areas.criar');

        $ok = DocumentoArea::criar(
                $_POST['nome'],
                $_POST['codigo'],
                $_POST['descricao'],
                isset($_POST['ativo']) ? 1 : 0
        );

        if ($ok) {
            \App\Core\Sessao::flash('sucesso', 'Área criada com sucesso.');
        } else {
            \App\Core\Sessao::flash('erro', 'Falha ao criar a área.');
        }

        return $this->redirect('/admin/documento-areas');
    }

    public function editar($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        $area = DocumentoArea::find($id);

        if (!$area) {
            \App\Core\Sessao::flash('erro', 'A área não existe.');
            return $this->redirect('/admin/documento-areas');
        }

        return $this->render('@admin/documento-areas/editar.twig', [
                    'area' => $area
        ]);
    }

    public function update($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        $ok = DocumentoArea::atualizar(
                $id,
                $_POST['nome'],
                $_POST['codigo'],
                $_POST['descricao'],
                isset($_POST['ativo']) ? 1 : 0
        );

        if ($ok) {
            \App\Core\Sessao::flash('sucesso', 'Área atualizada com sucesso.');
        } else {
            \App\Core\Sessao::flash('erro', 'Falha ao atualizar a área.');
        }

        return $this->redirect('/admin/documento-areas');
    }

    public function apagar($id)
    {
        $this->authorize('admin.tramitacao.areas.apagar');

        $area = DocumentoArea::find($id);

        if (!$area) {
            \App\Core\Sessao::flash('erro', 'A área não existe.');
            return $this->redirect('/admin/documento-areas');
        }

        DocumentoArea::apagar($id);

        \App\Core\Sessao::flash('sucesso', 'Área apagada com sucesso.');

        return $this->redirect('/admin/documento-areas');
    }
}
