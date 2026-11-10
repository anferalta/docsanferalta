<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/App/bootstrap/runtime.php';
try {
    \App\Services\EmailService::checkConnection();
    echo "TLS e autenticação SMTP verificados; nenhum email enviado.\n";
} catch (\Throwable $error) {
    $message = strtolower($error->getMessage());
    $category = str_contains($message, 'ficheiro') ? 'ficheiro privado' : ((str_contains($message, 'auth') || str_contains($message, 'autent')) ? 'autenticação' : 'ligação TLS/configuração');
    fwrite(STDERR, "SMTP não verificado ({$category}). Nenhum email enviado.\n");
    exit(1);
}
