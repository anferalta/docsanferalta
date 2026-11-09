<?php
require __DIR__ . '/../vendor/autoload.php';

\App\Core\Env::load();

use App\Services\RelatorioSLAEmailService;

$service = new RelatorioSLAEmailService();
$service->enviarEmail('diário');
