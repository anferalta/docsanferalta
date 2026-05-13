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
$router->get('/recuperar', 'AuthController@recuperar')->name('auth.recover');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// LOGOUT
$router->get('/logout', 'AuthController@logout')->name('auth.logout');