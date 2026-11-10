<?php
namespace App\Services;

/** Shared policy for every document and workflow attachment entry point. */
final class UploadPolicy
{
    private const TYPES = [
        'pdf' => ['application/pdf'],
        'txt' => ['text/plain', 'application/x-empty'],
        'jpg' => ['image/jpeg'], 'jpeg' => ['image/jpeg'],
        'png' => ['image/png'], 'gif' => ['image/gif'], 'webp' => ['image/webp'],
        'doc' => ['application/msword', 'application/x-ole-storage', 'application/CDFV2'],
        'xls' => ['application/vnd.ms-excel', 'application/x-ole-storage', 'application/CDFV2'],
        'ppt' => ['application/vnd.ms-powerpoint', 'application/x-ole-storage', 'application/CDFV2'],
        'docx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
        'xlsx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'],
        'pptx' => ['application/zip', 'application/vnd.openxmlformats-officedocument.presentationml.presentation'],
        'zip' => ['application/zip', 'application/x-zip-compressed'],
        'rar' => ['application/x-rar', 'application/x-rar-compressed', 'application/vnd.rar'],
        '7z' => ['application/x-7z-compressed'],
    ];

    public static function validarUpload(string $tmp, string $name): void
    {
        if (!is_uploaded_file($tmp)) {
            throw new \RuntimeException('Origem de upload inválida.');
        }
        self::validarConteudo($tmp, $name);
    }

    public static function validarConteudo(string $path, string $name): void
    {
        clearstatcache(true, $path);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!isset(self::TYPES[$ext]) || preg_match('/\.(php\d*|phtml|phar|html?|svg|js|exe|sh|ps1)\./i', $name)) {
            throw new \RuntimeException('Tipo de ficheiro não permitido.');
        }
        $size = is_file($path) ? filesize($path) : false;
        $limit = match ($ext) {
            'txt' => 2 * 1024 * 1024,
            'zip', 'rar', '7z' => 50 * 1024 * 1024,
            default => 20 * 1024 * 1024,
        };
        if ($size === false || $size === 0 || $size > $limit) {
            throw new \RuntimeException('Dimensão do ficheiro inválida.');
        }
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);
        if (!in_array($mime, self::TYPES[$ext], true)) {
            throw new \RuntimeException('O conteúdo não corresponde ao tipo de ficheiro permitido.');
        }
        $entry = ['docx' => 'word/document.xml', 'xlsx' => 'xl/workbook.xml', 'pptx' => 'ppt/presentation.xml'][$ext] ?? null;
        if ($entry !== null) {
            $zip = new \ZipArchive();
            if ($zip->open($path) !== true) {
                throw new \RuntimeException('Documento Office inválido.');
            }
            try {
                if ($zip->locateName('[Content_Types].xml') === false || $zip->locateName($entry) === false) {
                    throw new \RuntimeException('Estrutura Office inválida.');
                }
            } finally {
                $zip->close();
            }
        }
    }
}
