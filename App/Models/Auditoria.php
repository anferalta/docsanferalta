<?php

namespace App\Models;

use App\Core\Model;
use App\Core\Conexao;

class Auditoria extends Model {

    protected string $table = 'auditoria';
    protected string $primaryKey = 'id';
    // === COLUNAS ===
    public ?int $id = null;
    public ?int $utilizador_id = null;
    public ?string $acao = null;
    public ?string $ip = null;
    public ?string $detalhes = null;
    public ?string $criado_em = null;

    /**
     * Registar ação na auditoria
     */
    public static function registar(string $acao, ?int $userId = null, ?string $detalhes = null): int {
        // Sanitização mínima
        $acao = trim($acao);
        if ($acao === '') {
            $acao = '(ação não especificada)';
        }

        // Limitar tamanho dos detalhes
        if ($detalhes !== null && strlen($detalhes) > 2000) {
            $detalhes = substr($detalhes, 0, 2000) . '... [TRUNCADO]';
        }

        // IP seguro
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        if ($ip !== null && strlen($ip) > 45) {
            $ip = substr($ip, 0, 45);
        }

        return parent::create([
                    'utilizador_id' => $userId,
                    'acao' => $acao,
                    'detalhes' => $detalhes,
                    'ip' => $ip
        ]);
    }

    /**
     * Últimos registos
     */
    public static function ultimos(int $limite = 10): array {
        return static::query()
                        ->orderBy('id', 'DESC')
                        ->limit($limite)
                        ->get();
    }

    /**
     * Todos ordenados
     */
    public static function allOrdered(): array {
        return static::query()
                        ->orderBy('id', 'DESC')
                        ->get();
    }

    /**
     * Contagem com filtros (útil para paginação)
     */
    public static function countFiltered(array $filtros): int {
        $query = static::query();

        if (!empty($filtros['utilizador'])) {
            $query->where('utilizador_id', '=', (int) $filtros['utilizador']);
        }

        if (!empty($filtros['acao'])) {
            $query->where('acao', 'LIKE', '%' . $filtros['acao'] . '%');
        }

        if (!empty($filtros['data_inicio'])) {
            $query->where('criado_em', '>=', $filtros['data_inicio'] . ' 00:00:00');
        }

        if (!empty($filtros['data_fim'])) {
            $query->where('criado_em', '<=', $filtros['data_fim'] . ' 23:59:59');
        }

        return $query->count();
    }

    public static function graficoPorAcao() {
        $db = static::db();

        $stmt = $db->query("
        SELECT acao, COUNT(*) AS total
        FROM auditoria
        GROUP BY acao
    ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public static function graficoPorDia() {
        $db = static::db();

        $stmt = $db->query("
        SELECT DATE(criado_em) AS dia, COUNT(*) AS total
        FROM auditoria
        GROUP BY DATE(criado_em)
        ORDER BY dia ASC
    ");

        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
