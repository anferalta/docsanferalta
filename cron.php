<?php
require __DIR__ . '/App/bootstrap/cli.php';
(new \App\Services\DatabaseBackupService())->criar();
(new \App\Services\FileBackupService())->criar();
