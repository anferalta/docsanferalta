<?php

namespace App\Controllers;

use App\Core\BaseController;
use App\Core\Auth;
use App\Models\Auditoria;

class AuditoriaController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.auditoria.ver');

        $logs = Auditoria::query()
            ->orderBy('id', 'DESC')
            ->limit(200)
            ->get();

        return $this->render('admin/auditoria/index.twig', [
            'title' => 'Auditoria',
            'logs'  => $logs,
            'user'  => Auth::user()
        ]);
    }
}