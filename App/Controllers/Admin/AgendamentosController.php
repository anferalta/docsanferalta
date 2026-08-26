<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Services\AgendamentosService;
use App\Core\Sessao;

class AgendamentosController extends BaseController
{
    private AgendamentosService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AgendamentosService();
    }

    public function index()
    {
        $this->authorize('admin.backups.agendamentos.ver');

        $cron = $this->service->listar();

        return $this->render('admin/backups/agendamentos_index.twig', [
            'cron' => $cron
        ]);
    }

    public function criar()
    {
        $this->authorize('admin.backups.agendamentos.editar');

        return $this->render('admin/backups/agendamento_criar.twig');
    }

    public function criarPost()
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $nome = trim($_POST['nome'] ?? '');
        $frequencia = trim($_POST['frequencia'] ?? '');

        if ($nome === '' || $frequencia === '') {
            Sessao::flash('erro', 'Preencha todos os campos.');
            return $this->redirect('/admin/agendamentos/criar');
        }

        $this->service->definir($nome, $frequencia, true);

        Sessao::flash('sucesso', 'Agendamento criado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function ver($nome)
    {
        $this->authorize('admin.backups.agendamentos.ver');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        return $this->render('admin/backups/agendamento_ver.twig', [
            'nome' => $nome,
            'dados' => $cron[$nome]
        ]);
    }

    public function editar($nome)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        return $this->render('admin/backups/agendamento_editar.twig', [
            'nome' => $nome,
            'dados' => $cron[$nome]
        ]);
    }

    public function editarPost($nome)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $frequencia = trim($_POST['frequencia'] ?? '');
        $ativo = isset($_POST['ativo']);

        if ($frequencia === '') {
            Sessao::flash('erro', 'A frequência é obrigatória.');
            return $this->redirect("/admin/agendamentos/editar/{$nome}");
        }

        $this->service->definir($nome, $frequencia, $ativo);

        Sessao::flash('sucesso', 'Agendamento atualizado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function ativar($nome)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->ativar($nome);

        Sessao::flash('sucesso', 'Agendamento ativado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function desativar($nome)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->desativar($nome);

        Sessao::flash('sucesso', 'Agendamento desativado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function eliminar($nome)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $cron = $this->service->listar();

        if (!isset($cron[$nome])) {
            Sessao::flash('erro', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->eliminar($nome);

        Sessao::flash('sucesso', 'Agendamento eliminado.');
        return $this->redirect('/admin/agendamentos');
    }
}
