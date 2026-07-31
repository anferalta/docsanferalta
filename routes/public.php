<?php

// HOME
$router->get('/', 'Site\HomeController@index')->name('site.home');

// LOGIN
$router->get('/login', 'AuthController@login')->name('auth.login');
//$router->post('/login', 'AuthController@loginSubmit');
$router->post('/login', 'AuthController@loginSubmit', ['csrf']);

// REGISTO
$router->get('/registar', 'AuthController@registar')->name('auth.register');
$router->post('/registar', 'AuthController@registarSubmit');

// RECUPERAR PASSWORD
$router->get('/recuperar', 'AuthController@recuperar')->name('auth.recover');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// RESET PASSWORD — rota com token (única correta)
$router->get('/reset-password/token/{token}', 'PasswordResetController@formNovaPassword');
$router->post('/reset-password/token/{token}', 'PasswordResetController@guardarNovaPassword');

// LOGOUT
$router->get('/logout', 'AuthController@logout')->name('auth.logout');

// DASHBOARD USER
$router->get('/dashboard', 'DashboardUserController@index');

// DOCUMENTOS USER
$router->get('/documentos', 'DocumentosUserController@index');
$router->get('/documentos/criar', 'DocumentosUserController@criar');
$router->post('/documentos/criar', 'DocumentosUserController@criarSubmit');
$router->get('/documentos/anexo/abrir/{id:\d+}', 'DocumentosUserController@abrir');
$router->get('/documentos/abrir/{id}', 'DocumentosUserController@abrir');
$router->get('/documentos/download/{id:\d+}', 'DocumentosUserController@download');
