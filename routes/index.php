<?php

// Rotas públicas
require __DIR__ . '/public.php';

// Rotas admin
require __DIR__ . '/admin.php';

// Rotas de autenticação
require __DIR__ . '/auth.php';

// Recuperação de password (pedido)
$router->get('/recuperar', 'AuthController@recuperar')->name('auth.recover');
$router->post('/recuperar', 'AuthController@recuperarSubmit');

// Limpar sessão
$router->get('/limpar-sessao', 'DebugController@limparSessao');
