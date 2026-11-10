<?php

require __DIR__ . '/../App/bootstrap/cli.php';

use App\Services\SLAService;

SLAService::verificarSLA();
