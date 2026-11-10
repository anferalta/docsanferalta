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
            self::configureSmtp($mail);

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

    private static function configureSmtp(PHPMailer $mail): void
    {
        $mail->isSMTP();
        $mail->Host = \App\Core\Env::required('MAIL_HOST');
        $mail->SMTPAuth = filter_var(\App\Core\Env::get('MAIL_AUTH', 'true'), FILTER_VALIDATE_BOOLEAN);
        if ($mail->SMTPAuth) {
            $mail->Username = \App\Core\Env::required('MAIL_USER');
            $mail->Password = \App\Core\Env::secret('MAIL_PASS');
            if ($mail->Password === '') throw new \RuntimeException('Credencial SMTP não configurada.');
        }
        $encryption = (string) \App\Core\Env::get('MAIL_ENCRYPTION', 'smtps');
        if (!in_array($encryption, ['smtps', 'tls'], true)) {
            throw new \RuntimeException('MAIL_ENCRYPTION deve ser smtps ou tls.');
        }
        $mail->SMTPSecure = $encryption === 'smtps'
            ? PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = (int) \App\Core\Env::get('MAIL_PORT', $encryption === 'smtps' ? 465 : 587);
        if ($mail->Port < 1 || $mail->Port > 65535) {
            throw new \RuntimeException('MAIL_PORT invalido.');
        }
        $mail->Timeout = 10;
        $mail->getSMTPInstance()->Timelimit = 15;
        $mail->SMTPKeepAlive = false;
        $mail->SMTPOptions = ['ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
            'allow_self_signed' => false,
        ]];
        $ca = (string) \App\Core\Env::get('MAIL_CA_FILE', '');
        if ($ca !== '') {
            if (!is_readable($ca)) {
                throw new \RuntimeException('MAIL_CA_FILE nao e legivel.');
            }
            $mail->SMTPOptions['ssl']['cafile'] = $ca;
        }
        $mail->setFrom(
            \App\Core\Env::required('MAIL_FROM'),
            (string) \App\Core\Env::get('MAIL_FROM_NAME', 'Gestao Documental')
        );
    }

    public static function checkConnection(): bool
    {
        $mail = new PHPMailer(true);
        self::configureSmtp($mail);
        try {
            if (!$mail->smtpConnect()) throw new \RuntimeException('A autenticacao SMTP falhou.');
            return true;
        } finally {
            $mail->smtpClose();
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
