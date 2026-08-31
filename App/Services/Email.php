<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class Email
{
    public static function enviar(string $para, string $assunto, string $mensagem): bool
    {
        try {

            // Se quiseres usar PHPMailer:
            if (class_exists(PHPMailer::class)) {

                $mail = new PHPMailer(true);

                // Configuração mínima (podes ajustar)
                $mail->isSMTP();
                $mail->Host = $_ENV['MAIL_HOST'] ?? 'localhost';
                $mail->SMTPAuth = false;
                $mail->Port = $_ENV['MAIL_PORT'] ?? 25;

                $mail->setFrom($_ENV['MAIL_FROM'] ?? 'no-reply@localhost', 'Sistema');
                $mail->addAddress($para);

                $mail->Subject = $assunto;
                $mail->Body = $mensagem;

                $mail->isHTML(false);

                return $mail->send();
            }

            // Fallback para mail()
            return mail($para, $assunto, $mensagem);

        } catch (Exception $e) {

            // Log opcional
            error_log("Erro ao enviar email: " . $e->getMessage());

            return false;
        }
    }
}
