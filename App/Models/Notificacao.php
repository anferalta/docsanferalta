<?php

namespace App\Models;

use App\Core\Model;

class Notificacao extends Model
{
    protected string $table = 'notificacoes';

    public ?int $id = null;
    public ?int $utilizador_id = null;
    public ?int $documento_id = null;
    public ?string $tipo = null;
    public ?string $mensagem = null;
    public ?int $lida = null;
    public ?string $criado_em = null;

    protected array $fillable = [
        'utilizador_id',
        'documento_id',
        'tipo',
        'mensagem',
        'lida'
    ];
}
