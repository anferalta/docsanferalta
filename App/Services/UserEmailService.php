<?php

namespace App\Services;

use App\Models\PasswordReset;
use App\Models\Utilizador;
use App\Core\Helpers;
use App\Models\Auditoria;

class UserEmailService
{

    /**
     * Envia email com link de reset/aprovação.
     * O template deve ser passado APENAS pelo nome (ex: 'utilizador_aprovado')
     */
    public static function enviarLinkPassword(Utilizador $user, string $template, string $assunto): bool
    {
        if (empty($user->email)) {
            Auditoria::registar("Falha ao enviar email: utilizador ID {$user->id} sem email.");
            return false;
        }

        // Criar token
        $token = PasswordReset::criarToken($user->email);

        // Criar link CORRETO
        $link = Helpers::baseUrl() . "/reset-password/token/$token";
        
        // Enviar email
        $resultado = EmailService::enviar(
                $user->email,
                $assunto,
                $template,
                [
                    'nome' => $user->nome,
                    'link' => $link
                ]
        );

        if ($resultado) {
            Auditoria::registar("Email enviado para utilizador ID {$user->id} | Template: {$template}");
        } else {
            Auditoria::registar("Falha ao enviar email para utilizador ID {$user->id} | Template: {$template}");
        }

        return $resultado;
    }
}
