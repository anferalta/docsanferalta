<?php

namespace App\Core;

use App\Models\LogSistema;
use App\Services\Email; // imagino um serviço de email teu

class ErrorHandler
{
    public static function register()
    {
        set_error_handler([self::class, 'handleError']);
        set_exception_handler([self::class, 'handleException']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    protected static function registarEAlertar(string $tipo, string $mensagem, ?string $ficheiro, ?int $linha, ?string $detalhes = null)
    {
        LogSistema::registar($tipo, $mensagem, $ficheiro, $linha, $detalhes);

        if (in_array($tipo, ['fatal', 'excecao'])) {
            // Ajusta para o teu serviço de email real
            Email::enviar(
                'admin@teusite.com',
                "[CRÍTICO] Erro no sistema",
                "Tipo: $tipo\nMensagem: $mensagem\nFicheiro: $ficheiro\nLinha: $linha\n\n$detalhes"
            );
        }
    }

    public static function handleError($errno, $errstr, $errfile, $errline)
    {
        self::registarEAlertar('erro', $errstr, $errfile, $errline);
        return false;
    }

    public static function handleException($exception)
    {
        self::registarEAlertar(
            'excecao',
            $exception->getMessage(),
            $exception->getFile(),
            $exception->getLine(),
            $exception->getTraceAsString()
        );
    }

    public static function handleShutdown()
    {
        $error = error_get_last();

        if ($error && in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
            self::registarEAlertar(
                'fatal',
                $error['message'],
                $error['file'],
                $error['line']
            );
        }
    }
}