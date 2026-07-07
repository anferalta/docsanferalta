<?php

// HOME
$router->get('/', 'Site\HomeController@index')->name('site.home');

// LOGIN
$router->get('/login', 'AuthController@login')->name('auth.login');
$router->post('/login', 'AuthController@loginSubmit');

// REGISTO
$router->get('/registar', 'AuthController@registar')->name('auth.register');
$router->post('/registar', 'AuthController@registarSubmit');

// RECUPERAÇÃO
$router->get('/recuperar', 'AuthController@recuperar');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// RESET PASSWORD — rota sem token
$router->get('/reset-password', 'PasswordResetController@redirecionarSemToken');

// RESET PASSWORD — rota com token (prefixo obrigatório)
$router->get('/reset-password/token/{token}', 'PasswordResetController@formNovaPassword');

// Submissão da nova password
$router->post('/reset-password', 'PasswordResetController@guardarNovaPassword');

// LOGOUT
$router->get('/logout', 'AuthController@logout')->name('auth.logout');
