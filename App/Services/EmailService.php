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
            // SMTP — OTIMIZADO (SMTPS 465)
            // ============================
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'geral@anferalta.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '@nF1ra!ta26';

            // 🔥 Muito mais rápido que STARTTLS
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // ============================
            // TIMEOUT — EVITA BLOQUEIOS
            // ============================
            $mail->Timeout = 5;     // máximo 5 segundos
            $mail->SMTPKeepAlive = false; // evita pendurar ligações
            // ============================
            // SSL — COMPATÍVEL COM WAMP
            // ============================
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // ============================
            // DEBUG OPCIONAL
            // ============================
            if (!empty($_ENV['MAIL_DEBUG']) && $_ENV['MAIL_DEBUG'] == 'true') {
                $mail->SMTPDebug = 2; // mostra handshake e autenticação
            }

            // ============================
            // REMETENTE
            // ============================
            $mail->setFrom(
                    $_ENV['MAIL_FROM'] ?? 'geral@anferalta.com',
                    $_ENV['MAIL_FROM_NAME'] ?? 'AnferaltaDocs'
            );

            // ============================
            // DESTINATÁRIO
            // ============================
            $mail->addAddress($para);

            // ============================
            // CONTEÚDO
            // ============================
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isHTML(true);

            $mail->Subject = $assunto;
            $mail->Body = EmailTemplate::render($template, $dados);

            // ============================
            // ENVIO
            // ============================
            $resultado = $mail->send();

            Helpers::log("Email enviado", "Para: {$para} | Assunto: {$assunto}");

            return $resultado;
        } catch (Exception $e) {
            Helpers::log(
                    "Erro ao enviar email",
                    "Destinatário: {$para} | Erro PHPMailer: " . $mail->ErrorInfo . " | Exceção: " . $e->getMessage()
            );

            throw $e; // mostra o erro real no ambiente local
        }
    }
}
