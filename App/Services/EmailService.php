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
    ): bool
    {
        $mail = new PHPMailer(true);

        try {
            // Configuração SMTP (DinaServer)
            $mail->isSMTP();
            $mail->Host = 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'geral@anferalta.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '@nF1ra!ta26';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port = 465;

            // Evitar erros de certificado no Windows
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Remetente
            $mail->setFrom('geral@anferalta.com', 'Anferalta');

            // Destinatário
            $mail->addAddress($para);

            // Anexos
            foreach ($anexos as $ficheiro) {
                if (file_exists($ficheiro)) {
                    $mail->addAttachment($ficheiro);
                }
            }

            // HTML
            $mail->isHTML(true);
            $mail->Subject = $assunto;
            $mail->Body = EmailTemplate::render($template, $dados);
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

    public static function enviarComAnexo(
        string $para,
        string $assunto,
        string $mensagem,
        string $ficheiro,
        string $de = 'geral@anferalta.com'
    ): bool
    {
        $mail = new PHPMailer(true);

        try {
            // SMTP (DinaServer)
            $mail->isSMTP();
            $mail->Host = 'anferalta-com.correoseguro.dinaserver.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'geral@anferalta.com';
            $mail->Password = $_ENV['MAIL_PASS'] ?? '@nF1ra!ta26';
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; // SSL
            $mail->Port = 465;

            // Evitar erros de certificado no Windows
            $mail->SMTPOptions = [
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false,
                    'allow_self_signed' => true
                ]
            ];

            // Remetente
            $mail->setFrom($de, 'AnferaltaDocs');

            // Destinatário
            $mail->addAddress($para);

            // Conteúdo
            $mail->isHTML(false);
            $mail->Subject = $assunto;
            $mail->Body = $mensagem;

            // Anexo
            if (file_exists($ficheiro)) {
                $mail->addAttachment($ficheiro);
            } else {
                self::log("Aviso: anexo não encontrado: {$ficheiro}");
            }

            $mail->send();

            self::log("Email enviado para {$para} | Assunto: {$assunto}");
            return true;

        } catch (Exception $e) {
            self::log("Erro ao enviar email: " . $mail->ErrorInfo);
            return false;
        }
    }
}
