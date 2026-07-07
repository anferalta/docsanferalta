<?php

// LOGIN
$router->get('/login', 'AuthController@login');
$router->post('/login', 'AuthController@loginSubmit');

// LOGOUT
$router->get('/logout', 'AuthController@logout');

// RECUPERAR PASSWORD
$router->get('/recuperar', 'AuthController@recuperar');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// RESET PASSWORD
$router->get('/reset-password/{token}', 'PasswordResetController@formNovaPassword');
$router->post('/reset-password', 'PasswordResetController@guardarNovaPassword');

// REGISTAR
$router->get('/registar', 'AuthController@registar');
$router->post('/registar', 'AuthController@registarSubmit');
