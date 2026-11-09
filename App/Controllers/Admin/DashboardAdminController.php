<?php
namespace App\Controllers\Admin;

use App\Core\Auth;
use App\Core\BaseController;
use App\Services\DashboardService;

class DashboardAdminController extends BaseController
{
    public function index()
    {
        $this->authorize('admin.dashboard.ver');
        $user=Auth::user();
        if(!$user){$this->acessoNegado();return;}
        $this->render('@admin/dashboard/index.twig',(new DashboardService())->carregar($user));
    }
}
