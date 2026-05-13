<?php

namespace App\Services;

use App\Models\LogSistema;

class BackupLogger {

    public static function registar(string $tipo, string $ficheiro, bool $sucesso, string $mensagem = ''): void {
        $texto = $mensagem ?: "Backup {$tipo}: {$ficheiro}";

        if (!$sucesso) {
            self::enviarEmailErro($mensagem);
        }

        LogSistema::registar(
            $sucesso ? 'backup_sucesso' : 'backup_erro',
            $texto,
            __FILE__,
            __LINE__
        );

        $assunto = $sucesso ? "Backup {$tipo} concluído" : "ERRO no backup {$tipo}";
        EmailService::enviar('admin@anferaltadocs.test', $assunto, $texto);
    }

    private static function enviarEmailErro(string $mensagem) {
        $to = getenv('ADMIN_EMAIL') ?: 'admin@localhost';
        $assunto = "Falha no Backup da Loja Virtual";
        $body = "Ocorreu um erro no backup:\n\n" . $mensagem;

        mail($to, $assunto, $body);
    }
}