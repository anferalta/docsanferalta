<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Services\FileBackupService;
use App\Services\BackupLogger;

try {
    $ficheiro = (new FileBackupService())->criar();

    // Registar sucesso
    BackupLogger::registar('FILES', $ficheiro, true, "Backup automático de ficheiros concluído.");
} catch (\Exception $e) {

    // Registar erro
    BackupLogger::registar('FILES', '', false, "Backup automático de ficheiros falhou: " . $e->getMessage());
}
