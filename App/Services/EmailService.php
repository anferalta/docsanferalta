<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use App\Core\EmailTemplate;
use App\Core\Helpers;

class EmailService
{

    public static function enviar(string|array $para, string $assunto, string $template, array $dados = [], array $anexos = []): bool
    {
        // Validar template antes de enviar
        try {
            $html = EmailTemplate::render($template, $dados);
        } catch (\Throwable $e) {
            throw new \Exception("Template de email inválido: {$template}");
        }

        // Validar destinatários
        $destinatarios = is_array($para) ? $para : [$para];
        $destinatarios = array_filter($destinatarios, fn($email) =>
                filter_var($email, FILTER_VALIDATE_EMAIL)
        );

        if (empty($destinatarios)) {
            throw new \Exception("Nenhum destinatário válido para envio de email.");
        }

        $mail = new PHPMailer(true);

        try {
            // SMTP
            $mail->isSMTP();
            $mail->Host = $_ENV['MAIL_HOST'] ?? 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth = true;
            $mail->Username = $_ENV['MAIL_USER'] ?? 'geral@anferalta.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '@nF1ra!ta26';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            $mail->Port = 465;

            // Timeout
            $mail->Timeout = 5;
            $mail->SMTPKeepAlive = false;

            // SSL compatível com WAMP
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Debug opcional
            if (!empty($_ENV['MAIL_DEBUG']) && $_ENV['MAIL_DEBUG'] === 'true') {
                $mail->SMTPDebug = 2;
            }

            // Remetente
            $mail->setFrom(
                    $_ENV['MAIL_FROM'] ?? 'geral@anferalta.com',
                    $_ENV['MAIL_FROM_NAME'] ?? 'AnferaltaDocs'
            );

            // Destinatários
            foreach ($destinatarios as $email) {
                $mail->addAddress($email);
            }

            // Conteúdo
            $mail->CharSet = 'UTF-8';
            $mail->Encoding = 'base64';
            $mail->isHTML(true);

            $mail->Subject = $assunto;
            $mail->Body = $html;

            // Anexos
            foreach ($anexos as $ficheiro) {
                if (file_exists($ficheiro)) {
                    $mail->addAttachment($ficheiro);
                }
            }

            // Envio
            $resultado = $mail->send();

            Helpers::log("Email enviado", "Para: " . implode(', ', $destinatarios) . " | Assunto: {$assunto}");

            return $resultado;
        } catch (Exception $e) {
            Helpers::log(
                    "Erro ao enviar email",
                    "Destinatário(s): " . implode(', ', $destinatarios) .
                    " | Erro PHPMailer: " . $mail->ErrorInfo .
                    " | Exceção: " . $e->getMessage()
            );

            throw $e;
        }
    }

    public static function destinatarioExiste(string $email): bool
    {
        // Extrair utilizador e domínio
        if (!str_contains($email, '@')) {
            return false;
        }

        [$user, $domain] = explode('@', $email);

        // Obter MX do domínio
        $mx = dns_get_record($domain, DNS_MX);
        if (!$mx || empty($mx)) {
            return false;
        }

        $server = $mx[0]['target'];

        // Tentar ligação SMTP
        $conn = @fsockopen($server, 25, $errno, $errstr, 5);
        if (!$conn) {
            return true; // servidor não permite VRFY → assumimos que existe
        }

        // VRFY (verificar caixa de correio)
        fputs($conn, "VRFY $user\r\n");
        $response = fgets($conn, 1024);
        fclose($conn);

        // Se o servidor disser "550", o utilizador não existe
        return !str_contains($response, "550");
    }
}
