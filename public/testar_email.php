<?php
require __DIR__ . '/../vendor/autoload.php';

\App\Services\EmailService::enviarComAnexo(
    'geral@anferalta.com',
    'Teste de envio',
    'O sistema está a enviar emails corretamente.',
    __DIR__ . '/teste.txt'
);

echo "Email enviado (ou erro no log).";
