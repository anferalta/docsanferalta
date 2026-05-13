<?php
require __DIR__ . '/../vendor/autoload.php';

\App\Services\BackupService::backupDatabase();
\App\Services\BackupService::backupFiles();