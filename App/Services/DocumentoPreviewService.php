<?php

namespace App\Services;

use App\Models\DocumentoFicheiro;

class DocumentoPreviewService
{
    public static function caminhoPdf(DocumentoFicheiro $anexo, string $origem): string
    {
        return self::caminhoPdfPorId((int) $anexo->id, $origem);
    }

    public static function caminhoPdfPorId(int $anexoId, string $origem): string
    {
        $versao = (string) filesize($origem) . ':' . (string) filemtime($origem);
        $diretorio = rtrim(STORAGE_TEMP, '/\\') . DIRECTORY_SEPARATOR
            . 'documentos_preview' . DIRECTORY_SEPARATOR
            . $anexoId . '-' . hash('sha256', $versao);

        return $diretorio . DIRECTORY_SEPARATOR
            . pathinfo($origem, PATHINFO_FILENAME) . '.pdf';
    }

    public static function converterParaPdf(string $origem, string $pdfDestino): bool
    {
        if (is_file($pdfDestino) && filesize($pdfDestino) > 0) {
            return true;
        }

        $diretorio = dirname($pdfDestino);
        if (!is_dir($diretorio) && !mkdir($diretorio, 0775, true) && !is_dir($diretorio)) {
            self::registar('Não foi possível criar a pasta temporária do preview: ' . $diretorio);
            return false;
        }

        $binario = self::localizarLibreOffice();

        $origemConversao = $origem;
        $origemTemporaria = null;
        $pdfGerado = $pdfDestino;

        // Alguns DOCX têm espaços antes da assinatura ZIP; normalizar uma cópia para o LibreOffice.
        if (strtolower(pathinfo($origem, PATHINFO_EXTENSION)) === 'docx') {
            $cabecalho = @file_get_contents($origem, false, null, 0, 16);
            $posAssinatura = $cabecalho === false ? false : strpos($cabecalho, "PK\x03\x04");
            if ($posAssinatura !== false && $posAssinatura > 0) {
                $prefixo = substr($cabecalho, 0, $posAssinatura);
                if (trim($prefixo, " \t\r\n\0") === '') {
                    $conteudo = @file_get_contents($origem);
                    if ($conteudo === false) {
                        return false;
                    }

                    $origemTemporaria = $diretorio . DIRECTORY_SEPARATOR
                        . pathinfo($origem, PATHINFO_FILENAME) . '.normalized.docx';
                    if (@file_put_contents($origemTemporaria, substr($conteudo, $posAssinatura)) === false) {
                        return false;
                    }
                    $origemConversao = $origemTemporaria;
                    $pdfGerado = $diretorio . DIRECTORY_SEPARATOR
                        . pathinfo($origemTemporaria, PATHINFO_FILENAME) . '.pdf';
                }
            }
        }

        if (function_exists('exec') || function_exists('proc_open')) {
            $comando = escapeshellarg($binario)
                . ' --headless --nologo --nofirststartwizard --convert-to pdf '
                . escapeshellarg($origemConversao)
                . ' --outdir ' . escapeshellarg($diretorio);

            [$codigo, $saida] = self::executar($comando);
            self::registar("Comando LibreOffice terminou com código {$codigo}: {$saida}");

            if ($pdfGerado !== $pdfDestino && is_file($pdfGerado)) {
                @rename($pdfGerado, $pdfDestino);
            }
        } else {
            self::registar('exec() e proc_open() estão indisponíveis; a tentar a conversão PHPWord/Dompdf.');
        }

        if (is_file($pdfDestino) && filesize($pdfDestino) > 0) {
            if ($origemTemporaria && is_file($origemTemporaria)) {
                @unlink($origemTemporaria);
            }
            return true;
        }

        // Fallback sem processos do sistema. Pode não reproduzir com precisão layouts
        // complexos, mas permite gerar um preview em alojamentos que bloqueiam exec/proc_open.
        $gerado = self::converterComPhpWord($origemConversao, $pdfDestino);

        if ($origemTemporaria && is_file($origemTemporaria)) {
            @unlink($origemTemporaria);
        }

        return $gerado && is_file($pdfDestino) && filesize($pdfDestino) > 0;
    }

    private static function converterComPhpWord(string $origem, string $pdfDestino): bool
    {
        try {
            $dompdfDiretorio = dirname(__DIR__, 2) . DIRECTORY_SEPARATOR . 'vendor'
                . DIRECTORY_SEPARATOR . 'dompdf' . DIRECTORY_SEPARATOR . 'dompdf';
            if (!class_exists(\PhpOffice\PhpWord\IOFactory::class)
                || !class_exists(\PhpOffice\PhpWord\Settings::class)
                || !class_exists(\Dompdf\Dompdf::class)
                || !is_dir($dompdfDiretorio)) {
                self::registar('Fallback PHPWord/Dompdf indisponível; confirme as dependências Composer em produção.');
                return false;
            }

            if (!\PhpOffice\PhpWord\Settings::setPdfRenderer(
                \PhpOffice\PhpWord\Settings::PDF_RENDERER_DOMPDF,
                $dompdfDiretorio
            )) {
                self::registar('Não foi possível configurar Dompdf como renderer de PDF do PHPWord.');
                return false;
            }

            $extensao = strtolower(pathinfo($origem, PATHINFO_EXTENSION));
            $leitor = $extensao === 'doc' ? 'MsDoc' : 'Word2007';
            $documento = \PhpOffice\PhpWord\IOFactory::load($origem, $leitor);
            $temporario = $pdfDestino . '.phpword.tmp';
            @unlink($temporario);
            \PhpOffice\PhpWord\IOFactory::createWriter($documento, 'PDF')->save($temporario);

            if (!is_file($temporario) || filesize($temporario) === 0) {
                @unlink($temporario);
                self::registar('Fallback PHPWord/Dompdf não produziu um PDF válido.');
                return false;
            }

            if (is_file($pdfDestino)) {
                @unlink($pdfDestino);
            }
            if (!@rename($temporario, $pdfDestino)) {
                @unlink($temporario);
                self::registar('Não foi possível mover o PDF temporário do fallback para o cache.');
                return false;
            }

            self::registar('Preview convertido com PHPWord/Dompdf.');
            return true;
        } catch (\Throwable $e) {
            self::registar('Falha no fallback PHPWord/Dompdf: ' . $e->getMessage());
            return false;
        }
    }

    private static function localizarLibreOffice(): ?string
    {
        $configurado = trim((string) ($_ENV['LIBREOFFICE_BIN'] ?? getenv('LIBREOFFICE_BIN') ?: ''));
        if ($configurado !== '') {
            // Não validar com is_file(): open_basedir pode bloquear essa verificação
            // mesmo quando o processo externo é executável pelo utilizador do PHP.
            return trim($configurado, " \t\n\r\0\x0B\"'");
        }

        // A localização é passada diretamente ao sistema operativo. Verificar estes
        // caminhos com is_file()/is_executable() pode produzir warnings sob open_basedir.
        return PHP_OS_FAMILY === 'Windows'
            ? 'C:\\Program Files\\LibreOffice\\program\\soffice.com'
            : '/usr/bin/soffice';
    }

    /** @return array{0:int,1:string} */
    private static function executar(string $comando): array
    {
        if (function_exists('proc_open')) {
            $spec = [
                0 => ['pipe', 'r'],
                1 => ['pipe', 'w'],
                2 => ['pipe', 'w'],
            ];
            $processo = @proc_open($comando, $spec, $pipes);
            if (is_resource($processo)) {
                fclose($pipes[0]);
                $saida = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                return [proc_close($processo), trim($saida)];
            }
        }

        if (function_exists('exec')) {
            $linhas = [];
            $codigo = 1;
            @exec($comando . ' 2>&1', $linhas, $codigo);
            return [$codigo, implode("\n", $linhas)];
        }

        return [1, 'Não foi possível iniciar um processo no PHP.'];
    }

    private static function registar(string $mensagem): void
    {
        $ficheiro = rtrim(STORAGE_LOGS, '/\\') . DIRECTORY_SEPARATOR . 'conversao.log';
        @file_put_contents($ficheiro, date('Y-m-d H:i:s') . ' ' . $mensagem . PHP_EOL, FILE_APPEND);
        error_log('[DOCUMENTO_PREVIEW] ' . $mensagem);
    }
}
