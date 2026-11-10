<?php

namespace App\Services;

class SchedulerService
{
    /**
     * Instruções para CRON (Linux)
     */
    public static function cronLinux(): array
    {
        $php = escapeshellarg(\App\Core\Env::get('PHP_CLI_BIN', PHP_BINARY));
        $root = BASE_PATH;

        return [
            'backup_bd_diario'     => "0 3 * * * {$php} " . escapeshellarg($root . "/cron/backup_db.php"),
            'backup_files_semanal' => "0 4 * * 0 {$php} " . escapeshellarg($root . "/cron/backup_files.php"),
        ];
    }

    /**
     * Instruções para Windows Task Scheduler
     */
    public static function cronWindows(): array
    {
        $php = '"' . \App\Core\Env::get('PHP_CLI_BIN', PHP_BINARY) . '"';
        $root = BASE_PATH;

        return [
            'backup_bd_diario'     => $php . ' "' . $root . '/cron/backup_db.php"',
            'backup_files_semanal' => $php . ' "' . $root . '/cron/backup_files.php"',
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
