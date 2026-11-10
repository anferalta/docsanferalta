<?php
namespace App\Controllers;
class PerfilController extends \App\Core\BaseController
{
    public function index(): void
    {
        $user = \App\Core\Auth::user();
        if (!$user) {
            $this->redirect('/login');
            return;
        }
        $this->render('@site/perfil.twig', ['user' => $user]);
    }
}
