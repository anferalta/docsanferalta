<?php

namespace App\Controllers;

class DebugController
{
    public function limparSessao()
    {
        session_start();
        session_destroy();
        echo "Sessão limpa.";
        exit;
    }
}
