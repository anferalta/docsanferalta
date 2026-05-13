<?php

namespace App\Controllers\Admin;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Notificacao;

class NotificacoesAdminController extends BaseController
{
    public function index()
    {
        $uid = Auth::id();

        $lista = Notificacao::where('utilizador_id', $uid)
            ->orderBy('id', 'DESC')
            ->get();

        return $this->render('@admin/notificacoes/index.twig', [
            'notificacoes' => $lista
        ]);
    }

    public function ver(int $id)
    {
        $uid = Auth::id();

        $n = Notificacao::find($id);

        if (!$n || $n->utilizador_id != $uid) {
            return $this->redirect('/admin/notificacoes');
        }

        // Marcar como lida
        $n->lida = 1;
        $n->save();

        // Se tiver documento associado, redireciona para ele
        if ($n->documento_id) {
            return $this->redirect("/admin/tramitacao/{$n->documento_id}");
        }

        return $this->redirect('/admin/notificacoes');
    }
}
