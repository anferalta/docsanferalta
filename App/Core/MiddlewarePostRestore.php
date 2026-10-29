<?php

namespace App\Core;

class MiddlewarePostRestore
{
    public static function check()
    {
        // Se o restauro foi feito, invalidar sessão atual
        if (!empty($_SESSION['restauro_bd_feito'])) {

            // Remover flag
            unset($_SESSION['restauro_bd_feito']);

            // Destruir sessão atual
            session_destroy();

            // Remover cookie da sessão
            setcookie(session_name(), '', time() - 3600, '/');

            // Criar nova sessão limpa
            session_start();

            // Mensagem de segurança
            $_SESSION['flash']['sucesso'] =
                "A base de dados foi restaurada. Por segurança, inicie sessão novamente.";

            // Redirecionar para login
            header("Location: /login");
            exit;
        }
    }
}
