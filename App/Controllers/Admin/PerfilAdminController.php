<?php

namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BaseController;

class PerfilAdminController extends BaseController
{
    public function index(): void
    {
        $user = Auth::user();

        if (!$user) {
            $this->redirect('/login');
        }

        $this->render('@admin/perfis/meu_perfil.twig', [
            'user' => $user,
        ]);
    }
}
