<?php

namespace App\Services;

class Email
{
    public static function enviar(string $para, string $assunto, string $mensagem): bool
    {
        // Implementa com mail(), SMTP, PHPMailer, etc.
        return mail($para, $assunto, $mensagem);
    }
}