<?php

namespace App\Core;

use PDO;
use PDOException;

class Conexao
{
    private static ?PDO $instancia = null;

    public static function getInstancia(): PDO
    {
        if (self::$instancia === null) {
            try {
                // Usa Env::get() para buscar credenciais
                $host     = Env::get('DB_HOST', 'localhost');
                $dbname   = Env::get('DB_NAME', 'anferaltadocs');
                $user     = Env::get('DB_USER', 'root');
                $password = Env::get('DB_PASS', '');

                self::$instancia = new PDO(
                    "mysql:host={$host};dbname={$dbname};charset=utf8",
                    $user,
                    $password,
                    [
                        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_OBJ,
                        PDO::ATTR_PERSISTENT         => true
                    ]
                );
            } catch (PDOException $e) {
                die("Erro na conexão com a base de dados: " . $e->getMessage());
            }
        }

        return self::$instancia;
    }
}