<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Core\Conexao;
use App\Models\Notification;

class NotificationController extends BaseController
{
    private Notification $model;

    public function __construct()
    {
        parent::__construct();
        $this->model = new Notification(Conexao::getInstancia());
    }

    public function index()
    {
        $this->authorize('admin.notificacoes.ver');

        $user = Auth::user();
        $notificacoes = $this->model->getByUser($user->id);

        return $this->view('notificacoes/index.twig', [
            'notificacoes' => $notificacoes,
        ]);
    }

    public function marcarLida(int $id)
    {
        $this->authorize('admin.notificacoes.ver');

        $this->model->markAsRead($id);

        return $this->back();
    }

    public function limparTodas()
    {
        $this->authorize('admin.notificacoes.eliminar');

        $user = Auth::user();
        $this->model->clearAll($user->id);

        return $this->redirect('/admin/notificacoes');
    }
}