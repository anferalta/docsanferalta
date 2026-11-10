<?php
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
require dirname(__DIR__) . '/App/bootstrap/app.php';
$router = new \App\Core\Router();
\App\Core\RouterSingleton::set($router);
require dirname(__DIR__) . '/routes/index.php';
$router->dispatch();
