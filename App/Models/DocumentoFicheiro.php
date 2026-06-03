<?php

namespace App\Models;

use App\Core\Model;

class DocumentoFicheiro extends Model
{

    /**
     * Nome da tabela
     */
    protected string $table = 'documento_ficheiros';
    protected string $primaryKey = 'id';
    public ?int $id = null;
    public ?int $documento_id = null;
    public ?string $ficheiro = null;
    public ?int $tamanho = null;
    public ?string $mime = null;
    public ?string $criado_em = null;
    public ?string $ficheiro_original = null;

    /**
     * Campos permitidos para mass assignment
     */
    protected array $fillable = [
        'id',
        'documento_id',
        'ficheiro',
        'tamanho',
        'mime',
        'criado_em',
        'ficheiro_original',
    ];

    /**
     * Relacionamento: este anexo pertence a um documento
     */
    public function documento()
    {
        $m = new Documento();
        return $m->find($this->documento_id);
    }

    /**
     * Devolve apenas o nome do ficheiro (sem diretórios)
     */
    public function nome()
    {
        // Se existir nome original, usa-o
        if (!empty($this->ficheiro_original)) {
            return $this->ficheiro_original;
        }

        // Caso contrário, usa o nome renomeado
        return basename($this->ficheiro);
    }

    /**
     * Extensão do ficheiro
     */
    public function ext()
    {
        return strtolower(pathinfo($this->ficheiro, PATHINFO_EXTENSION));
    }

    /**
     * Caminho absoluto no servidor
     */
    public function caminhoAbsoluto()
    {
        return realpath(__DIR__ . '/../../') . '/storage/documentos/' . $this->ficheiro;
    }

    /**
     * URL pública para abrir inline
     */
    public function urlVer()
    {
        return "/admin/documentos/anexo/abrir/{$this->id}";
    }

    /**
     * URL pública para download
     */
    public function urlDownload()
    {
        return "/admin/documentos/anexo/download/{$this->id}";
    }

    public static function anexosDoDocumento($id)
    {
        $m = new self();
        return $m->where('documento_id', '=', $id)->get();
    }
}
