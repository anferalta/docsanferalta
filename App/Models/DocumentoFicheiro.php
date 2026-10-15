<?php

namespace App\Models;

use App\Core\Model;

class DocumentoFicheiro extends Model
{
    protected string $table = 'documento_ficheiros';
    protected string $primaryKey = 'id';

    public ?int    $id               = null;
    public ?int    $documento_id     = null;
    public ?string $ficheiro         = null;
    public ?string $ficheiro_original = null;
    public ?string $caminho          = null;
    public ?int    $tamanho          = null;
    public ?string $mime_type        = null;
    public ?string $hash             = null;
    public ?string $criado_em        = null;

    public ?string $mime             = null;

    protected array $fillable = [
        'documento_id',
        'ficheiro',
        'ficheiro_original',
        'caminho',
        'tamanho',
        'mime_type',
        'mime',
        'hash',
        'criado_em'
    ];

    public function documento()
    {
        return (new Documento())->find($this->documento_id);
    }

    public function nome()
    {
        return $this->ficheiro_original ?: basename((string)$this->ficheiro);
    }

    public function ext()
    {
        return strtolower(pathinfo((string)$this->ficheiro, PATHINFO_EXTENSION));
    }

    public function caminhoAbsoluto()
    {
        return rtrim(STORAGE_DOCUMENTOS, '/')
            . '/'
            . trim($this->caminho, '/')
            . '/'
            . $this->ficheiro;
    }

    public function urlVer()
    {
        return "/admin/documentos/anexo/abrir/{$this->id}";
    }

    public function urlDownload()
    {
        return "/admin/documentos/anexo/download/{$this->id}";
    }

    public static function anexosDoDocumento($id)
    {
        $db = \App\Core\Conexao::getInstancia();

        $stmt = $db->prepare("
            SELECT * 
            FROM documento_ficheiros 
            WHERE documento_id = :id 
            ORDER BY criado_em DESC
        ");

        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(\PDO::FETCH_CLASS, self::class);
    }
}
