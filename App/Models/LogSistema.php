<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Auth;

class LogSistema extends Model
{
    protected string $table = 'logs_sistema';
    protected string $primaryKey = 'id';

    protected array $permitidos = [
        'tipo',
        'mensagem',
        'ficheiro',
        'linha',
        'detalhes',
        'ip',
        'user_agent',
        'url',
        'metodo',
        'utilizador_id'
    ];

    public ?int $id = null;
    public ?string $tipo = null;
    public ?string $mensagem = null;
    public ?string $ficheiro = null;
    public ?int $linha = null;
    public ?string $detalhes = null;
    public ?string $ip = null;
    public ?string $user_agent = null;
    public ?string $url = null;
    public ?string $metodo = null;
    public ?int $utilizador_id = null;
    public ?string $criado_em = null;

    /**
     * Registar log no sistema (melhorado)
     */
    public static function registar(
        string $tipo,
        string $mensagem,
        ?string $ficheiro = null,
        ?int $linha = null,
        ?string $detalhes = null
    ) {
        // Sanitização
        $tipo = substr(trim($tipo), 0, 50);
        $mensagem = substr(trim($mensagem), 0, 500);

        if ($detalhes !== null && strlen($detalhes) > 5000) {
            $detalhes = substr($detalhes, 0, 5000) . '... [TRUNCADO]';
        }

        // IP
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip !== null && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        // User Agent
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
        if ($ua !== null && strlen($ua) > 255) {
            $ua = substr($ua, 0, 255);
        }

        // URL
        $url = $_SERVER['REQUEST_URI'] ?? null;

        // Método HTTP
        $metodo = $_SERVER['REQUEST_METHOD'] ?? null;

        // Utilizador autenticado
        $utilizador = Auth::user()?->id ?? null;

        return parent::create([
            'tipo'         => $tipo,
            'mensagem'     => $mensagem,
            'ficheiro'     => $ficheiro,
            'linha'        => $linha,
            'detalhes'     => $detalhes,
            'ip'           => $ip,
            'user_agent'   => $ua,
            'url'          => $url,
            'metodo'       => $metodo,
            'utilizador_id'=> $utilizador
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
     * Limpar logs antigos
     */
    public static function limparAntigos(int $dias = 90): int
    {
        return static::query()
            ->where('criado_em', '<', date('Y-m-d H:i:s', strtotime("-{$dias} days")))
            ->delete();
    }
}
