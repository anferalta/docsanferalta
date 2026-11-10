<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$argv = [__FILE__, 'backup'];
require dirname(__DIR__) . '/cli/maintenance.php';
