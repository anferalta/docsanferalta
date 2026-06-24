<?php
require __DIR__ . '/../vendor/autoload.php';

use App\Services\RelatorioSLAEmailService;

$service = new RelatorioSLAEmailService();
$service->enviarEmail('diário');
