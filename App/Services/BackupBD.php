<?php
namespace App\Services;
class BackupBD { public static function executar(): string { return (new DatabaseBackupService())->criar(); } }
