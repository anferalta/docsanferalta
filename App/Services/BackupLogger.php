<?php

namespace App\Services;

use App\Models\LogSistema;
use App\Services\EmailService;
use App\Services\PathGuardService;

class BackupLogger
{
    /**
     * Regista sucesso ou erro no backup e envia notificação por email.
     */
    public static function registar(string $tipo, string $ficheiro, bool $sucesso, string $mensagem = ''): void
    {
        PathGuardService::init();

        // Blindagem do caminho recebido
        if (is_string($ficheiro)) {
            PathGuardService::proteger($ficheiro);
        }

        $texto = $mensagem ?: "Backup {$tipo}: {$ficheiro}";

        // Log no sistema
        LogSistema::registar(
            $sucesso ? 'backup_sucesso' : 'backup_erro',
            $texto,
            __FILE__,
            __LINE__
        );

        // Enviar email de erro
        if (!$sucesso) {
            self::notificarErro($tipo, $ficheiro, $mensagem);
        }

        // Enviar email de sucesso
        self::notificarResultado($tipo, $ficheiro, $sucesso, $texto);
    }

    /**
     * Envia email de erro detalhado.
     */
    private static function notificarErro(string $tipo, string $ficheiro, string $mensagem): void
    {
        PathGuardService::proteger($ficheiro);

        $emails = self::destinatarios();

        foreach ($emails as $email) {
            EmailService::enviar(
                $email,
                "⚠️ Falha no Backup ({$tipo})",
                'backup_erro',
                [
                    'mensagem' => $mensagem,
                    'ficheiro' => $ficheiro,
                    'ip'       => ($_SERVER['REMOTE_ADDR'] ?? 'CLI'),
                    'data'     => date('Y-m-d H:i:s'),
                    'tipo'     => $tipo
                ]
            );
        }
    }

    /**
     * Envia email de sucesso ou erro simples.
     */
    private static function notificarResultado(string $tipo, string $ficheiro, bool $sucesso, string $texto): void
    {
        PathGuardService::proteger($ficheiro);

        $emails = self::destinatarios();

        foreach ($emails as $email) {
            EmailService::enviar(
                $email,
                $sucesso ? "Backup {$tipo} concluído" : "Erro no backup {$tipo}",
                'backup_notificacao',
                [
                    'mensagem' => $texto,
                    'ficheiro' => $ficheiro,
                    'sucesso'  => $sucesso,
                    'tipo'     => $tipo
                ]
            );
        }
    }

    /**
     * Obtém lista de destinatários válidos.
     */
    private static function destinatarios(): array
    {
        $lista = $_ENV['ADMIN_EMAILS'] ?? 'geral@anferalta.com';

        $emails = array_filter(array_map('trim', explode(',', $lista)), function ($email) {
            return filter_var($email, FILTER_VALIDATE_EMAIL);
        });

        // fallback seguro
        if (empty($emails)) {
            $emails = ['geral@anferalta.com'];
        }

        return $emails;
    }
}
