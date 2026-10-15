<?php

// Página inicial
$router->get('/', 'WebController@index');

$router->post('/login', 'AuthController@loginPost');

// Recuperar password
$router->get('/recuperar', 'AuthController@recuperar');
$router->post('/recuperar', 'AuthController@recuperarPost');
