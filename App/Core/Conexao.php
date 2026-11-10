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
                self::$instancia = \App\Services\DatabaseConnection::open();
                self::$instancia->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_OBJ);
            } catch (PDOException $e) {
                error_log('Database connection failed: ' . $e->getCode());
                throw new \RuntimeException('Nao foi possivel ligar a base de dados.');
            }
        }

        return self::$instancia;
    }
}