<?php

namespace App\Services;

class SchedulerService
{
    /**
     * Instruções para CRON (Linux)
     */
    public static function cronLinux(): array
    {
        $php = '/usr/bin/php';
        $root = '/var/www/anferaltadocs';

        return [
            'backup_bd_diario'     => "0 3 * * * {$php} {$root}/cron/backup_db.php",
            'backup_files_semanal' => "0 4 * * 0 {$php} {$root}/cron/backup_files.php",
        ];
    }

    /**
     * Instruções para Windows Task Scheduler
     */
    public static function cronWindows(): array
    {
        $php = 'C:\\wamp64\\bin\\php\\php8.2.0\\php.exe';
        $root = 'C:\\wamp64\\www\\anferaltadocs';

        return [
            'backup_bd_diario'     => "{$php} {$root}\\cron\\backup_db.php",
            'backup_files_semanal' => "{$php} {$root}\\cron\\backup_files.php",
        ];
    }

    /**
     * Execução direta via PHP CLI
     */
    public static function executar(string $tarefa): void
    {
        switch ($tarefa) {
            case 'backup_db':
                (new DatabaseBackupService())->criar();
                break;

            case 'backup_files':
                (new FileBackupService())->criar();
                break;

            default:
                throw new \Exception("Tarefa de cron desconhecida: {$tarefa}");
        }
    }
}
