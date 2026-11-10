<?php

require __DIR__ . '/../App/bootstrap/cli.php';

use App\Services\DatabaseBackupService;
use App\Services\BackupLogger;

try {
    $ficheiro = (new DatabaseBackupService())->criar();

    // Registar sucesso
    BackupLogger::registar('BD', $ficheiro, true, "Backup automático concluído.");
} catch (\Throwable $e) {

    // Registar erro
    BackupLogger::registar('BD', '', false, "Backup automático falhou: " . $e->getMessage());
    exit(1);
}
