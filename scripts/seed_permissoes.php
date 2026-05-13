<?php

require __DIR__ . '/../vendor/autoload.php';

use App\Seeders\PermissoesSeeder;

// Ajusta conforme o teu container ou ligação PDO
$db = container('db');

$seeder = new PermissoesSeeder();
$seeder->run($db);

echo "Permissões seedadas.\n";