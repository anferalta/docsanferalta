<?php

namespace App\Models;

use App\Core\Model;

class DocumentosArquivados extends Model
{
    protected string $table = 'documentos_arquivados';
    protected string $primaryKey = 'id';

    public ?int    $id             = null;
    public ?int    $documento_id   = null;
    public ?string $ficheiro       = null;
    public ?string $caminho        = null;
    public ?string $mime_type      = null;
    public ?int    $tamanho        = null;
    public ?string $arquivado_em   = null;
    public ?int    $arquivado_por  = null;
    public ?string $recuperado_em  = null;
    public ?int    $recuperado_por = null;

    protected array $fillable = [
        'documento_id',
        'ficheiro',
        'caminho',
        'mime_type',
        'tamanho',
        'arquivado_em',
        'arquivado_por',
        'recuperado_em',
        'recuperado_por'
    ];

    public function caminhoAbsoluto(): string
    {
        return STORAGE_ARQUIVADOS . '/' . trim($this->caminho, '/') . '/' . $this->ficheiro;
    }

    public static function porDocumento(int $id): ?self
    {
        return self::query()
            ->where('documento_id', '=', $id)
            ->orderBy('arquivado_em', 'DESC')
            ->first();
    }
}
