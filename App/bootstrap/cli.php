<?php
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require_once __DIR__ . '/runtime.php';
$maintenanceLock = new \App\Core\MaintenanceLock(STORAGE_PATH, false);
$task = basename($_SERVER['SCRIPT_FILENAME'] ?? 'cli');
$lock = fopen(STORAGE_TEMP . '/' . hash('sha256', $task) . '.lock', 'c');
if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
    fwrite(STDERR, "Tarefa ja em execucao.\n");
    exit(1);
}
register_shutdown_function(static function () use ($lock): void {
    flock($lock, LOCK_UN);
    fclose($lock);
});
