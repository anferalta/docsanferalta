<?php
require __DIR__ . '/../App/bootstrap/cli.php';



use App\Services\RelatorioSLAEmailService;

$service = new RelatorioSLAEmailService();
$service->enviarEmail('diário');
