<?php

namespace App\Services;

class SchedulerService
{
    public static function instrucoesCron(): array
    {
        return [
            'backup_bd_diario'      => '0 3 * * * php C:/wamp64/www/anferaltadocs/artisan backup:db',
            'backup_files_semanal'  => '0 4 * * 0 php C:/wamp64/www/anferaltadocs/artisan backup:files',
        ];
    }
}