<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Core\EmailTemplate;
use App\Core\Helpers;

class EmailService
{
    public static function enviar(string $para, string $assunto, string $template, array $dados = []): bool
    {
        $mail = new PHPMailer(true);

        try {

            // ============================
            // CONFIGURAÇÃO SMTP (TLS 587)
            // ============================
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'geral@anferalta.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '@nF1ra!ta26';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = 587;

            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';

            // ============================
            // OPÇÕES SSL (evitar bloqueios no WAMP)
            // ============================
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // ============================
            // REMETENTE
            // ============================
            $mail->setFrom($_ENV['MAIL_FROM'] ?? 'geral@anferalta.com', $_ENV['MAIL_FROM_NAME'] ?? 'AnferaltaDocs');

            // ============================
            // DESTINATÁRIO
            // ============================
            $mail->addAddress($para);

            // ============================
            // CONTEÚDO DO EMAIL
            // ============================
            $mail->isHTML(true);
            $mail->Subject = $assunto;

            // Renderizar template Twig (agora correto)
            $mail->Body = EmailTemplate::render($template, $dados);

            // ============================
            // ENVIO
            // ============================
            $resultado = $mail->send();

            Helpers::log("Email enviado para {$para}", "Assunto: {$assunto}");

            return $resultado;

        } catch (Exception $e) {

            Helpers::log("Erro ao enviar email", $mail->ErrorInfo);
            return false;
        }
    }
}
