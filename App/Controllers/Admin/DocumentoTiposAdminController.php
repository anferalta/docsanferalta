<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Models\DocumentoTipo;
use App\Core\Sessao;
use App\Core\CSRF;

class DocumentoTiposAdminController extends BaseController {

    /* ============================================================
      LISTAR
    ============================================================ */
    public function index() {
        $this->authorize('admin.documento-tipos.ver');

        $tipos = DocumentoTipo::all();
        $csrf  = CSRF::token();

        $this->render('@admin/documento_tipos/index.twig', [
            'tipos' => $tipos,
            '_csrf' => $csrf
        ]);
    }

    /* ============================================================
      CRIAR (FORM)
    ============================================================ */
    public function criar() {
        $this->authorize('admin.documento-tipos.criar');

        $csrf = CSRF::token();

        $this->render('@admin/documento_tipos/criar.twig', [
            '_csrf' => $csrf
        ]);
    }

    /* ============================================================
      CRIAR (SUBMIT)
    ============================================================ */
    public function criarSubmit() {
        $this->authorize('admin.documento-tipos.criar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-tipos/criar');
        }

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome é obrigatório.');
            return $this->redirect('/admin/documento-tipos/criar');
        }

        if (DocumentoTipo::existeNome($nome)) {
            Sessao::flash('erro', 'Já existe um tipo com esse nome.');
            return $this->redirect('/admin/documento-tipos/criar');
        }

        DocumentoTipo::criar($nome);

        Sessao::flash('sucesso', 'Tipo criado com sucesso.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      EDITAR (FORM)
    ============================================================ */
    public function editar($tipo_id) {
        $this->authorize('admin.documento-tipos.editar');

        $tipo = DocumentoTipo::find($tipo_id);

        if (!$tipo) {
            Sessao::flash('erro', 'Tipo não encontrado.');
            return $this->redirect('/admin/documento-tipos');
        }

        $csrf = CSRF::token();

        $this->render('@admin/documento_tipos/editar.twig', [
            'tipo'  => $tipo,
            '_csrf' => $csrf
        ]);
    }

    /* ============================================================
      EDITAR (SUBMIT)
    ============================================================ */
    public function editarSubmit($tipo_id) {
        $this->authorize('admin.documento-tipos.editar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect("/admin/documento-tipos/editar/$tipo_id");
        }

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            Sessao::flash('erro', 'O nome é obrigatório.');
            return $this->redirect("/admin/documento-tipos/editar/$tipo_id");
        }

        if (DocumentoTipo::existeNomeParaOutro($nome, $tipo_id)) {
            Sessao::flash('erro', 'Já existe outro tipo com esse nome.');
            return $this->redirect("/admin/documento-tipos/editar/$tipo_id");
        }

        DocumentoTipo::update($tipo_id, ['nome' => $nome]);

        Sessao::flash('sucesso', 'Tipo atualizado com sucesso.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      APAGAR
    ============================================================ */
    public function apagar($tipo_id) {
        $this->authorize('admin.documento-tipos.apagar');

        if (!CSRF::validateFromRequest()) {
            Sessao::flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-tipos');
        }

        if (!DocumentoTipo::find($tipo_id)) {
            Sessao::flash('erro', 'Tipo não encontrado.');
            return $this->redirect('/admin/documento-tipos');
        }

        DocumentoTipo::delete($tipo_id);

        Sessao::flash('sucesso', 'Tipo apagado.');
        return $this->redirect('/admin/documento-tipos');
    }

    /* ============================================================
      AJAX — CRIAR
    ============================================================ */
    public function criarAjax() {
        $this->authorize('admin.documento-tipos.criar');
        header('Content-Type: application/json');

        if (!CSRF::validateFromRequest()) {
            echo json_encode(['erro' => 'Token CSRF inválido.']);
            return;
        }

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
            'tipo_id' => $id,
            'nome'    => $nome
        ]);
    }

    /* ============================================================
      AJAX — EDITAR
    ============================================================ */
    public function editarAjax($tipo_id) {
        $this->authorize('admin.documento-tipos.editar');
        header('Content-Type: application/json');

        if (!CSRF::validateFromRequest()) {
            echo json_encode(['erro' => 'Token CSRF inválido.']);
            return;
        }

        $nome = trim($_POST['nome'] ?? '');

        if ($nome === '') {
            echo json_encode(['erro' => 'O nome é obrigatório.']);
            return;
        }

        if (DocumentoTipo::existeNomeParaOutro($nome, $tipo_id)) {
            echo json_encode(['erro' => 'Já existe outro tipo com esse nome.']);
            return;
        }

        DocumentoTipo::update($tipo_id, ['nome' => $nome]);

        echo json_encode(['sucesso' => true]);
    }

    /* ============================================================
      AJAX — APAGAR
    ============================================================ */
    public function apagarAjax($tipo_id) {
        $this->authorize('admin.documento-tipos.apagar');
        header('Content-Type: application/json');

        if (!CSRF::validateFromRequest()) {
            echo json_encode(['erro' => 'Token CSRF inválido.']);
            return;
        }

        if (!DocumentoTipo::find($tipo_id)) {
            echo json_encode(['erro' => 'Tipo não encontrado.']);
            return;
        }

        DocumentoTipo::delete($tipo_id);

        echo json_encode(['sucesso' => true]);
    }
}
