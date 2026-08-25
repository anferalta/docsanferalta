<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Core\CSRF;
use App\Models\DocumentoEstado;

class DocumentoEstadosAdminController extends BaseController
{

    public function index()
    {
        $csrf = CSRF::token();

        $estados = DocumentoEstado::all();
        return $this->render('admin/documento-estados/index.twig', [
            'estados' => $estados,
            '_csrf' => $csrf
        ]);
    }

    public function criar()
    {
        $csrf = CSRF::token();

        return $this->render('admin/documento-estados/criar.twig', [
            '_csrf' => $csrf
        ]);
    }

    public function criarPost()
    {
        if (!CSRF::validateFromRequest()) {
            $this->flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-estados/criar');
        }

        $nome = trim($_POST['nome']);

        if ($nome === '') {
            $this->flash('erro', 'O nome é obrigatório.');
            return $this->redirect('/admin/documento-estados/criar');
        }

        $estado = new DocumentoEstado();
        $estado->nome = $nome;

        $estado->codigo = strtolower(
            preg_replace('/[^a-z0-9]+/', '-', $nome)
        );

        $estado->ordem = count(DocumentoEstado::all()) + 1;

        $estado->save();

        $this->flash('sucesso', 'Estado criado com sucesso.');
        return $this->redirect('/admin/documento-estados');
    }

    public function editar($id)
    {
        $estado = DocumentoEstado::find($id);

        if (!$estado) {
            $this->flash('erro', 'Estado não encontrado.');
            return $this->redirect('/admin/documento-estados');
        }

        $csrf = CSRF::token();

        return $this->render('admin/documento-estados/editar.twig', [
            'estado' => $estado,
            '_csrf' => $csrf
        ]);
    }

    public function editarPost($id)
    {
        if (!CSRF::validateFromRequest()) {
            $this->flash('erro', 'Token CSRF inválido.');
            return $this->redirect("/admin/documento-estados/editar/$id");
        }

        $estado = DocumentoEstado::find($id);

        if (!$estado) {
            $this->flash('erro', 'Estado não encontrado.');
            return $this->redirect('/admin/documento-estados');
        }

        $nome = trim($_POST['nome']);

        if ($nome === '') {
            $this->flash('erro', 'O nome é obrigatório.');
            return $this->redirect("/admin/documento-estados/editar/$id");
        }

        $estado->nome = $nome;

        $estado->codigo = strtolower(
            preg_replace('/[^a-z0-9]+/', '-', $nome)
        );

        $estado->save();

        $this->flash('sucesso', 'Estado atualizado.');
        return $this->redirect('/admin/documento-estados');
    }

    public function apagar($id)
    {
        if (!CSRF::validateFromRequest()) {
            $this->flash('erro', 'Token CSRF inválido.');
            return $this->redirect('/admin/documento-estados');
        }

        $estado = DocumentoEstado::find($id);

        if (!$estado) {
            $this->flash('erro', 'Estado não encontrado.');
            return $this->redirect('/admin/documento-estados');
        }

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT COUNT(*) FROM documentos WHERE estado_atual = ?");
        $stmt->execute([$estado->codigo]);
        $emUso = $stmt->fetchColumn();

        if ($emUso > 0) {
            $this->flash('erro', 'Não é possível eliminar este estado porque está a ser utilizado em documentos.');
            return $this->redirect('/admin/documento-estados');
        }

        $estado->delete();

        $this->flash('sucesso', 'Estado eliminado.');
        return $this->redirect('/admin/documento-estados');
    }

    public function confirmarApagar($id)
    {
        $estado = DocumentoEstado::find($id);

        if (!$estado) {
            $this->flash('erro', 'Estado não encontrado.');
            return $this->redirect('/admin/documento-estados');
        }

        $csrf = CSRF::token();

        return $this->render('admin/documento-estados/apagar.twig', [
            'estado' => $estado,
            '_csrf' => $csrf
        ]);
    }
}
