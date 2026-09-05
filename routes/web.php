<?php

$router->get('/', 'WebController@index');

$router->get('/login', 'AuthController@login');
$router->post('/login', 'AuthController@loginPost');

$router->get('/recuperar', 'AuthController@recuperar');
$router->post('/recuperar', 'AuthController@recuperarPost');
