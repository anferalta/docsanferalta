<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Conexao;
use App\Models\DocumentoEstado;

class DocumentoEstadosAdminController extends BaseController
{

    public function index()
    {
        $estados = DocumentoEstado::all();
        return $this->render('admin/documento-estados/index.twig', compact('estados'));
    }

    public function criar()
    {
        return $this->render('admin/documento-estados/criar.twig');
    }

    public function criarPost()
    {
        $nome = trim($_POST['nome']);

        if ($nome === '') {
            $this->flash('erro', 'O nome é obrigatório.');
            return $this->redirect('/admin/documento-estados/criar');
        }

        $estado = new DocumentoEstado();
        $estado->nome = $nome;

        // gerar código automático (slug)
        $estado->codigo = strtolower(
                preg_replace('/[^a-z0-9]+/', '-', $nome)
        );

        // ordem automática
        $estado->ordem = count(DocumentoEstado::all()) + 1;

        // final e ativo já têm defaults no model
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

        return $this->render('admin/documento-estados/editar.twig', compact('estado'));
    }

    public function editarPost($id)
    {
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

        // atualizar código automaticamente
        $estado->codigo = strtolower(
                preg_replace('/[^a-z0-9]+/', '-', $nome)
        );

        $estado->save();

        $this->flash('sucesso', 'Estado atualizado.');
        return $this->redirect('/admin/documento-estados');
    }

    public function apagar($id)
    {
        $estado = DocumentoEstado::find($id);

        if (!$estado) {
            $this->flash('erro', 'Estado não encontrado.');
            return $this->redirect('/admin/documento-estados');
        }

        $db = Conexao::getInstancia();

        // Verificar se algum documento usa este estado
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

        return $this->render('admin/documento-estados/apagar.twig', compact('estado'));
    }
}
