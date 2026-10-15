<?php

namespace App\Models;

use App\Core\Model;

class DocumentoTramitacaoAnexo extends Model
{
    protected string $table = 'documento_tramitacao_anexos';
    protected string $primaryKey = 'id';

    public ?int $id = null;
    public ?int $tramitacao_id = null;
    public ?string $ficheiro = null;
    public ?string $ficheiro_original = null;
    public ?string $caminho = null;
    public ?int $tamanho = null;
    public ?string $mime_type = null;
    public ?string $criado_em = null;

    public function caminhoAbsoluto()
    {
        $root = realpath(__DIR__ . '/../../');
        return $root . '/storage/tramitacao/' . $this->caminho . $this->ficheiro;
    }

    public static function anexosPorHistorico($id)
    {
        return self::query(
            "SELECT * FROM documento_tramitacao_anexos WHERE tramitacao_id = :id ORDER BY criado_em ASC",
            ['id' => $id]
        );
    }

    public static function guardarAnexos(int $tramitacao_id): void
    {
        if (empty($_FILES['anexos']['name'][0])) {
            return;
        }

        // ⭐ TIPOS PERMITIDOS NA TRAMITAÇÃO
        $permitidos = [
            'image/jpeg',
            'image/png',
            'image/gif',
            'image/webp',
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/zip',
            'application/x-rar',
            'text/plain', // ⭐ AGORA ACEITA TXT, LOG, INI, CSV, ETC.
        ];

        foreach ($_FILES['anexos']['name'] as $i => $nomeOriginal) {

            $tmp      = $_FILES['anexos']['tmp_name'][$i];
            $tamanho  = $_FILES['anexos']['size'][$i];
            $mime     = mime_content_type($tmp);

            // ⭐ Validação MIME
            if (!in_array($mime, $permitidos)) {
                throw new \Exception("Tipo de ficheiro não permitido: {$mime}");
            }

            $ficheiro = uniqid() . '_' . preg_replace('/[^A-Za-z0-9_\.\-]/', '_', $nomeOriginal);
            $caminho  = $tramitacao_id . '/';

            $destinoDir = STORAGE_TRAMITACAO . '/' . $caminho;

            if (!is_dir($destinoDir)) {
                mkdir($destinoDir, 0777, true);
            }

            if (!move_uploaded_file($tmp, $destinoDir . $ficheiro)) {
                continue;
            }

            self::create([
                'tramitacao_id'     => $tramitacao_id,
                'ficheiro'          => $ficheiro,
                'ficheiro_original' => $nomeOriginal,
                'caminho'           => $caminho,
                'tamanho'           => $tamanho,
                'mime_type'         => $mime,
            ]);
        }
    }
}
