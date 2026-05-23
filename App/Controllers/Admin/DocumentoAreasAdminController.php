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

        DocumentoArea::criar(
                $_POST['nome'],
                $_POST['codigo'],
                $_POST['descricao'],
                isset($_POST['ativo']) ? 1 : 0
        );

        return $this->redirect('/admin/tramitacao/areas');
    }

    public function editar($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        $area = DocumentoArea::find($id);

        return $this->render('@admin/documento-areas/editar.twig', [
                    'area' => $area
        ]);
    }

    public function update($id)
    {
        $this->authorize('admin.tramitacao.areas.editar');

        DocumentoArea::atualizar(
                $id,
                $_POST['nome'],
                $_POST['codigo'],
                $_POST['descricao'],
                isset($_POST['ativo']) ? 1 : 0
        );

        return $this->redirect('/admin/documento-areas');
    }

    public function apagar($id)
    {
        $this->authorize('admin.tramitacao.areas.apagar');
        file_put_contents(__DIR__ . '/apagar_log.txt', "1 - Entrou no apagar ($id)\n", FILE_APPEND);

        $area = DocumentoArea::find($id);
        file_put_contents(__DIR__ . '/apagar_log.txt', "3 - Encontrou area? " . ($area ? 'SIM' : 'NAO') . "\n", FILE_APPEND);

        if (!$area) {
            return $this->redirect('/admin/documento-areas');
        }

        DocumentoArea::atualizar(
                $id,
                $area->nome,
                $area->codigo,
                $area->descricao,
                0
        );
file_put_contents(__DIR__ . '/apagar_log.txt', "4 - Atualizou area\n", FILE_APPEND);
file_put_contents(__DIR__ . '/apagar_log.txt', "5 - Vai redirecionar\n", FILE_APPEND);

        return $this->redirect('/admin/documento-areas');
    }
}
