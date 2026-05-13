<?php

namespace App\Models;

use App\Core\Model;

class LogSistema extends Model
{
    protected string $table = 'logs_sistema';
    protected string $primaryKey = 'id';

    // Campos permitidos explicitamente
    protected array $permitidos = [
        'tipo',
        'mensagem',
        'ficheiro',
        'linha',
        'detalhes',
        'ip'
    ];

    public ?int $id = null;
    public ?string $tipo = null;
    public ?string $mensagem = null;
    public ?string $ficheiro = null;
    public ?int $linha = null;
    public ?string $detalhes = null;
    public ?string $ip = null;
    public ?string $criado_em = null;

    /**
     * Registar log no sistema
     */
    public static function registar(
        string $tipo,
        string $mensagem,
        ?string $ficheiro = null,
        ?int $linha = null,
        ?string $detalhes = null
    ) {
        // Sanitização mínima
        $tipo = trim($tipo);
        $mensagem = trim($mensagem);

        if ($detalhes !== null && strlen($detalhes) > 2000) {
            $detalhes = substr($detalhes, 0, 2000) . '... [TRUNCADO]';
        }

        // Sanitizar IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip !== null && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        return parent::create([
            'tipo'     => $tipo,
            'mensagem' => $mensagem,
            'ficheiro' => $ficheiro,
            'linha'    => $linha,
            'detalhes' => $detalhes,
            'ip'       => $ip
        ]);
    }

    /**
     * Últimos registos
     */
    public static function ultimos(int $limite = 50): array
    {
        return static::query()
            ->orderBy('id', 'DESC')
            ->limit($limite)
            ->get();
    }

    /**
     * Contagem com filtros (para paginação)
     */
    public static function countFiltered(array $filtros): int
    {
        $query = static::query();

        if (!empty($filtros['tipo'])) {
            $query->where('tipo', '=', $filtros['tipo']);
        }

        if (!empty($filtros['ip'])) {
            $query->where('ip', '=', $filtros['ip']);
        }

        if (!empty($filtros['data_inicio'])) {
            $query->where('criado_em', '>=', $filtros['data_inicio'] . ' 00:00:00');
        }

        if (!empty($filtros['data_fim'])) {
            $query->where('criado_em', '<=', $filtros['data_fim'] . ' 23:59:59');
        }

        if (!empty($filtros['mensagem'])) {
            $query->where('mensagem', 'LIKE', '%' . $filtros['mensagem'] . '%');
        }

        return $query->count();
    }
}