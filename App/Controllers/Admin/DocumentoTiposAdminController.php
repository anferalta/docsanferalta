<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\DocumentoTipo;
use App\Core\Sessao;

class DocumentoTiposAdminController extends BaseController {

    /* ============================================================
      LISTAR
    ============================================================ */
    public function index() {
        $this->authorize('admin.documento-tipos.ver');

        $tipos = DocumentoTipo::all();

        $this->render('@admin/documento_tipos/index.twig', [
            'tipos' => $tipos
        ]);
    }

    /* ============================================================
      CRIAR (FORM)
    ============================================================ */
    public function criar() {
        $this->authorize('admin.documento-tipos.criar');
        $this->render('@admin/documento_tipos/criar.twig');
    }

    /* ============================================================
      CRIAR (SUBMIT)
    ============================================================ */
    public function criarSubmit() {
        $this->authorize('admin.documento-tipos.criar');

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome é obrigatório.');
            return $this->redirect('/admin/documento-tipos/criar');
        }

        // impedir duplicados
        if (DocumentoTipo::existeNome($nome)) {
            Sessao::flash('erro', 'Já existe um tipo com esse nome.');
            return $this->redirect('/admin/documento-tipos/criar');
        }

        // criar tipo
        DocumentoTipo::criar($nome);

        Sessao::flash('sucesso', 'Tipo criado com sucesso.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      EDITAR (FORM)
    ============================================================ */
    public function editar($id) {
        $this->authorize('admin.documento-tipos.editar');

        $tipo = DocumentoTipo::find($id);

        if (!$tipo) {
            Sessao::flash('erro', 'Tipo não encontrado.');
            return $this->redirect('/admin/documento-tipos');
        }

        $this->render('@admin/documento_tipos/editar.twig', [
            'tipo' => $tipo
        ]);
    }

    /* ============================================================
      EDITAR (SUBMIT)
    ============================================================ */
    public function editarSubmit($id) {
        $this->authorize('admin.documento-tipos.editar');

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome é obrigatório.');
            return $this->redirect("/admin/documento-tipos/editar/$id");
        }

        // impedir duplicados (exceto o próprio)
        if (DocumentoTipo::existeNomeParaOutro($nome, $id)) {
            Sessao::flash('erro', 'Já existe outro tipo com esse nome.');
            return $this->redirect("/admin/documento-tipos/editar/$id");
        }

        DocumentoTipo::update($id, ['nome' => $nome]);

        Sessao::flash('sucesso', 'Tipo atualizado com sucesso.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      APAGAR
    ============================================================ */
    public function apagar($id) {
        $this->authorize('admin.documento-tipos.apagar');

        if (!DocumentoTipo::find($id)) {
            Sessao::flash('erro', 'Tipo não encontrado.');
            return $this->redirect('/admin/documento-tipos');
        }

        DocumentoTipo::delete($id);

        Sessao::flash('sucesso', 'Tipo apagado.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      AJAX — CRIAR
    ============================================================ */
    public function criarAjax() {
        $this->authorize('admin.documento-tipos.criar');
        header('Content-Type: application/json');

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            echo json_encode(['erro' => 'O nome é obrigatório.']);
            return;
        }

        if (DocumentoTipo::existeNome($nome)) {
            echo json_encode(['erro' => 'Já existe um tipo com esse nome.']);
            return;
        }

        $id = DocumentoTipo::criar($nome);

        echo json_encode([
            'sucesso' => true,
            'id' => $id,
            'nome' => $nome
        ]);
    }

    /* ============================================================
      AJAX — EDITAR
    ============================================================ */
    public function editarAjax($id) {
        $this->authorize('admin.documento-tipos.editar');
        header('Content-Type: application/json');

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            echo json_encode(['erro' => 'O nome é obrigatório.']);
            return;
        }

        if (DocumentoTipo::existeNomeParaOutro($nome, $id)) {
            echo json_encode(['erro' => 'Já existe outro tipo com esse nome.']);
            return;
        }

        DocumentoTipo::update($id, ['nome' => $nome]);

        echo json_encode(['sucesso' => true]);
    }

    /* ============================================================
      AJAX — APAGAR
    ============================================================ */
    public function apagarAjax($id) {
        $this->authorize('admin.documento-tipos.apagar');
        header('Content-Type: application/json');

        if (!DocumentoTipo::find($id)) {
            echo json_encode(['erro' => 'Tipo não encontrado.']);
            return;
        }

        DocumentoTipo::delete($id);

        echo json_encode(['sucesso' => true]);
    }
}