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
}
