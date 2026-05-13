<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\FileBackupService;

(new FileBackupService())->criar();