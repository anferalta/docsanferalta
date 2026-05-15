<?php

namespace App\Models;

use App\Core\Model;
use App\Models\Utilizador;
use App\Core\Conexao;

class Documento extends Model
{
    protected string $table = 'documentos';

    // ============================================================
    //  PROPRIEDADES DA TABELA DOCUMENTOS
    // ============================================================
    public ?int $id = null;
    public ?string $titulo = null;
    public ?string $ficheiro = null;
    public ?string $ficheiro_original = null;
    public ?string $mime_type = null;
    public ?int $tamanho = null;
    public ?string $hash = null;
    public ?int $criado_por = null;
    public ?string $criado_em = null;
    public ?string $caminho = null;
    public ?int $tipo_id = null;

    // ============================================================
    //  CAMPOS DO MÓDULO DE TRAMITAÇÃO
    // ============================================================
    public ?string $estado_atual = null;
    public ?int $area_atual_id = null;
    public ?string $arquivado_em = null;
    public ?int $arquivado_por_id = null;
    public ?string $estado = null;

    // ============================================================
    //  CAMPOS DERIVADOS (JOINs)
    // ============================================================
    public ?string $tipo_nome = null;
    public ?string $criador_nome = null;
    public ?string $criador_avatar = null;
    public ?string $area_nome = null;

    protected array $fillable = [
        'titulo',
        'ficheiro',
        'ficheiro_original',
        'mime_type',
        'tamanho',
        'hash',
        'criado_por',
        'criado_em',
        'caminho',
        'tipo_id'
    ];

    /* ============================================================
     *  RELAÇÃO COM UTILIZADOR
     * ============================================================ */

    public function utilizador(): ?Utilizador
    {
        return $this->criado_por ? Utilizador::find($this->criado_por) : null;
    }

    public function criador_nome(): string
    {
        $u = $this->utilizador();
        return $u ? $u->nome : 'Desconhecido';
    }

    /**
     * Avatar seguro — SEM depender de $u->avatar (que não existe)
     */
    public function criador_avatar(): string
    {
        return '/assets/img/avatar-default.png';
    }

    /* ============================================================
     *  CAMINHO DO FICHEIRO
     * ============================================================ */

    public function path(): string
    {
        // Caminho absoluto SEM realpath()
        $base = dirname(__DIR__, 3) . '/storage/documentos';

        $subpasta = trim($this->caminho ?? '', "/\\");

        if ($subpasta !== '') {
            return $base . DIRECTORY_SEPARATOR . $subpasta . DIRECTORY_SEPARATOR . $this->ficheiro;
        }

        return $base . DIRECTORY_SEPARATOR . $this->ficheiro;
    }

    /* ============================================================
     *  ÍCONES
     * ============================================================ */

    public function tipo(): string
    {
        $nome = $this->ficheiro_original ?: $this->ficheiro;
        return strtolower(pathinfo($nome, PATHINFO_EXTENSION));
    }

    public function icon(): string
    {
        $ext = $this->tipo();

        $icons = [
            'pdf' => 'fa-file-pdf',
            'doc' => 'fa-file-word',
            'docx' => 'fa-file-word',
            'xls' => 'fa-file-excel',
            'xlsx' => 'fa-file-excel',
            'txt' => 'fa-file-lines',
            'jpg' => 'fa-file-image',
            'jpeg' => 'fa-file-image',
            'png' => 'fa-file-image',
            'gif' => 'fa-file-image',
            'webp' => 'fa-file-image',
            'zip' => 'fa-file-zipper',
            'rar' => 'fa-file-zipper',
        ];

        return $icons[$ext] ?? 'fa-file';
    }

    /* ============================================================
     *  DATAS
     * ============================================================ */

    public function criado_em_formatado(): string
    {
        return $this->criado_em ? date('d/m/Y H:i', strtotime($this->criado_em)) : '';
    }

    public function areaAtual()
    {
        return $this->belongsTo(\App\Models\DocumentoArea::class, 'area_atual_id');
    }

    public function getAreaAtualNomeAttribute()
    {
        return $this->areaAtual ? $this->areaAtual->nome : '-';
    }

    /* ============================================================
     *  TRAMITAÇÃO
     * ============================================================ */

    public static function updateEstado($id, $estado, $area_id = null)
    {
        $db = Conexao::getInstancia();

        // Se arquivar, limpar área e registar data
        if ($estado === 'arquivado') {
            $sql = "UPDATE documentos 
                SET estado_atual = 'arquivado',
                    area_atual_id = NULL,
                    arquivado_em = NOW(),
                    arquivado_por_id = ?
                WHERE id = ?";
            $stmt = $db->prepare($sql);
            $stmt->execute([Auth::id(), $id]);
            return;
        }

        // Caso normal
        $sql = "UPDATE documentos 
            SET estado_atual = ?, area_atual_id = ?
            WHERE id = ?";
        $stmt = $db->prepare($sql);
        $stmt->execute([$estado, $area_id, $id]);
    }

    public static function arquivar($id, $user_id)
    {
        $db = Conexao::getInstancia();

        $sql = "UPDATE documentos 
                SET estado_atual = 'arquivado',
                    arquivado_em = NOW(),
                    arquivado_por_id = ?
                WHERE id = ?";

        $stmt = $db->prepare($sql);
        $stmt->execute([$user_id, $id]);
    }
}
