<?php

namespace App\Controllers\Site;

use App\Core\BaseController;

class HomeController extends BaseController
{
    public function index()
    {
        return $this->render('@site/home/index.twig');
    }
}