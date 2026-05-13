<?php

namespace App\Services;

use App\Core\Conexao;
use App\Core\Auth;

class Auditoria
{
    public static function registarEliminacao($tabela, $registoId, $dados)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
            INSERT INTO auditoria_eliminacoes 
            (tabela, registo_id, dados, apagado_por, ip)
            VALUES (:tabela, :registo_id, :dados, :apagado_por, :ip)
        ");

        $stmt->execute([
            'tabela' => $tabela,
            'registo_id' => $registoId,
            'dados' => json_encode($dados),
            'apagado_por' => Auth::user()->id ?? null,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? null
        ]);
    }
}