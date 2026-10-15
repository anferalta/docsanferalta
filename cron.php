<?php
require __DIR__ . '/App/Services/BackupBD.php';
require __DIR__ . '/App/Services/BackupFiles.php';

BackupBD::executar();
BackupFiles::executar();
