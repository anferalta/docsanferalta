<?php

namespace App\Core;

use App\Core\Conexao;

class Permission
{

    private static array $cache = [];

    public static function getPermissoesUtilizador(int $userId): array
    {

        if (isset(self::$cache[$userId])) {
            return self::$cache[$userId];
        }

        $db = Conexao::getInstancia();

        $sqlPerfil = "
            SELECT p.codigo
            FROM perfis_permissoes pp
            JOIN permissoes p ON p.id = pp.permissao_id
            JOIN utilizadores u ON u.perfil_id = pp.perfil_id
            WHERE u.id = :id
        ";

        $stm = $db->prepare($sqlPerfil);
        $stm->execute(['id' => $userId]);
        $perfilPerms = array_column($stm->fetchAll(), 'codigo');

        $sqlDiretas = "
            SELECT p.codigo 
            FROM utilizadores_permissoes up
            JOIN permissoes p ON p.id = up.permissao_id
            WHERE up.utilizador_id = :id
        ";

        $stm2 = $db->prepare($sqlDiretas);
        $stm2->execute(['id' => $userId]);
        $diretas = array_column($stm2->fetchAll(), 'codigo');

        $todas = array_unique(array_merge($perfilPerms, $diretas));

        self::$cache[$userId] = $todas;

        return $todas;
    }

    public static function tem(string $codigo): bool
    {

        $user = Auth::user();

        if (!$user) {
            return false;
        }

        // ADMIN TEM ACESSO TOTAL
        if ($user->isAdmin()) {
            return true;
        }

        return in_array($codigo, $user->permissoes());
    }

    public static function temAlguma(array $lista): bool
    {
        foreach ($lista as $perm) {
            if (self::tem($perm)) {
                return true;
            }
        }
        return false;
    }

    public static function temTodas(array $lista): bool
    {
        foreach ($lista as $perm) {
            if (!self::tem($perm)) {
                return false;
            }
        }
        return true;
    }
}
