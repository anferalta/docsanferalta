<?php

namespace App\Services;

class BackupBD
{
    public static function executar()
    {
        $data = date('Y-m-d_H-i-s');
        $ano  = date('Y');
        $mes  = date('m');

        $dir = "/home/anferalta/www/anferaltadocs/backups/BaseDados/$ano/$mes";

        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $ficheiro = "$dir/backup_db_$data.sql.gz";

        // Credenciais da BD
        $user = "anferaltadocs";
        $pass = "AQUI_A_PASSWORD";
        $db   = "anferaltadocs";

        // Comando mysqldump
        $cmd = "mysqldump --user=$user --password=$pass --host=localhost $db | gzip > $ficheiro";

        exec($cmd, $output, $result);

        if ($result !== 0) {
            mail("geral@anferalta.com", "Backup BD Falhou", "O backup da BD falhou em $data.");
            return false;
        }

        mail(
            "geral@anferalta.com",
            "Backup da Base de Dados concluído",
            "Backup da BD concluído com sucesso.\n\nFicheiro: $ficheiro"
        );

        return true;
    }
}
