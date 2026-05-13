<?php

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    public static function enviar(
        string $para,
        string $assunto,
        string $template,
        array $dados = [],
        array $anexos = []
    ): bool {

        // Fallback em ambiente local
        $appEnv = $_ENV['APP_ENV'] ?? 'local';
        if ($appEnv === 'local') {
            self::logLocal($para, $assunto, $template, $dados);
            return true;
        }

        $mail = new PHPMailer(true);

        try {
            // Configuração SMTP
            $mail->isSMTP();
            $mail->Host       = 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'geral@anferalta.com';
            $mail->Password   = $_ENV['MAIL_PASS'] ?? ''; // seguro e sem Env::get()
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = 587;

            // Remetente
            $mail->setFrom('geral@anferalta.com', 'Anferalta');

            // Destinatário
            $mail->addAddress($para);

            // Anexos
            foreach ($anexos as $ficheiro) {
                $mail->addAttachment($ficheiro);
            }

            // HTML
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body    = EmailTemplate::render($template, $dados);

            // Alternativa em texto simples
            $mail->AltBody = strip_tags($mail->Body);

            $resultado = $mail->send();

            self::log("Email enviado para {$para} | Assunto: {$assunto}");

            return $resultado;
        } catch (Exception $e) {
            self::log("Erro ao enviar email: " . $mail->ErrorInfo);
            return false;
        }
    }

    private static function log(string $mensagem): void
    {
        $linha = '[' . date('Y-m-d H:i:s') . '] ' . $mensagem . PHP_EOL;
        @file_put_contents(__DIR__ . '/../../storage/logs/email.log', $linha, FILE_APPEND);
    }

    private static function logLocal(string $para, string $assunto, string $template, array $dados): void
    {
        $conteudo = EmailTemplate::render($template, $dados);

        $linha = "\n=== EMAIL LOCAL ===\nPara: $para\nAssunto: $assunto\nConteúdo:\n$conteudo\n\n";
        @file_put_contents(__DIR__ . '/../../storage/logs/email.log', $linha, FILE_APPEND);
    }
}