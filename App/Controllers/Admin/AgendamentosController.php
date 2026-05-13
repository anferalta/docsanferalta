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
        // Não existe permissão "criar" na BD → usar editar
        $this->authorize('admin.backups.agendamentos.editar');

        return $this->render('admin/backups/agendamento_criar.twig');
    }

    public function criarPost()
    {
        $this->authorize('admin.backups.agendamentos.editar');

        $nome = trim($_POST['nome'] ?? '');
        $frequencia = trim($_POST['frequencia'] ?? '');

        if ($nome === '' || $frequencia === '') {
            $this->flash('error', 'Preencha todos os campos.');
            return $this->redirect('/admin/agendamentos/criar');
        }

        $ficheiro = strtolower(preg_replace('/[^a-z0-9_\-]/i', '_', $nome)) . '.txt';

        $dados = [
            'nome' => $nome,
            'frequencia' => $frequencia,
            'ativo' => 1,
            'ultima_execucao' => null,
            'proxima_execucao' => null
        ];

        $this->service->guardar($ficheiro, $dados);

        $this->flash('success', 'Agendamento criado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function ver($ficheiro)
    {
        $this->authorize('admin.backups.agendamentos.ver');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $path = $this->service->getPath($ficheiro);

        if (!file_exists($path)) {
            $this->flash('error', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $dados = $this->service->parse($path);

        return $this->render('admin/backups/agendamento_ver.twig', [
            'ficheiro' => $ficheiro,
            'dados' => $dados
        ]);
    }

    public function editar($ficheiro)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $path = $this->service->getPath($ficheiro);

        if (!file_exists($path)) {
            $this->flash('error', 'Agendamento não encontrado.');
            return $this->redirect('/admin/agendamentos');
        }

        $dados = $this->service->parse($path);

        return $this->render('admin/backups/agendamento_editar.twig', [
            'ficheiro' => $ficheiro,
            'dados' => $dados
        ]);
    }

    public function editarPost($ficheiro)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $dados = [
            'nome' => $_POST['nome'] ?? '',
            'frequencia' => $_POST['frequencia'] ?? '',
            'ultima_execucao' => $_POST['ultima_execucao'] ?: null,
            'proxima_execucao' => $_POST['proxima_execucao'] ?: null,
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];

        $this->service->guardar($ficheiro, $dados);

        $this->flash('success', 'Agendamento atualizado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function ativar($ficheiro)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->ativar($ficheiro);

        $this->flash('success', 'Agendamento ativado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function desativar($ficheiro)
    {
        $this->authorize('admin.backups.agendamentos.editar');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->desativar($ficheiro);

        $this->flash('success', 'Agendamento desativado.');
        return $this->redirect('/admin/agendamentos');
    }

    public function eliminar($ficheiro)
    {
        // Não existe "apagar" na BD → usar editar
        $this->authorize('admin.backups.agendamentos.editar');

        if (!$this->validarFicheiro($ficheiro)) {
            $this->flash('error', 'Ficheiro inválido.');
            return $this->redirect('/admin/agendamentos');
        }

        $this->service->eliminar($ficheiro);

        $this->flash('success', 'Agendamento eliminado.');
        return $this->redirect('/admin/agendamentos');
    }

    private function validarFicheiro(string $ficheiro): bool
    {
        return preg_match('/^[a-z0-9_\-]+\.txt$/i', $ficheiro) === 1;
    }
}