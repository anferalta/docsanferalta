<?php

namespace App\Services;

use App\Core\Conexao;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;
use PDOStatement;

class AuditoriaConsultaService
{
    public const ORDEM = ['id', 'utilizador_id', 'utilizador_nome', 'acao', 'ip', 'criado_em', 'origem'];
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Conexao::getInstancia();
    }

    public function normalizar(array $entrada): array
    {
        $filtros = [];
        foreach (['utilizador' => 255, 'ip' => 45, 'acao' => 255, 'data_inicio' => 10, 'data_fim' => 10, 'origem' => 10] as $campo => $limite) {
            $valor = $entrada[$campo] ?? '';
            if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || str_contains($valor, "\0")) {
                throw new InvalidArgumentException('Os filtros devem conter texto válido dentro dos limites dos campos.');
            }
            $valor = trim($valor);
            if (mb_strlen($valor, 'UTF-8') > $limite) {
                throw new InvalidArgumentException('O filtro excede o limite permitido.');
            }
            $filtros[$campo] = $valor;
        }
        if (!in_array($filtros['origem'], ['', 'auditoria', 'eliminacao'], true)) {
            throw new InvalidArgumentException('Origem de auditoria inválida.');
        }
        foreach (['data_inicio', 'data_fim'] as $campo) {
            if ($filtros[$campo] !== '') {
                $data = DateTimeImmutable::createFromFormat('!Y-m-d', $filtros[$campo]);
                if (!$data || $data->format('Y-m-d') !== $filtros[$campo] || $data->format('Y') === '0000') {
                    throw new InvalidArgumentException('Introduza datas válidas no formato AAAA-MM-DD.');
                }
            }
        }
        if ($filtros['data_inicio'] !== '' && $filtros['data_fim'] !== '' && $filtros['data_inicio'] > $filtros['data_fim']) {
            throw new InvalidArgumentException('A data inicial não pode ser posterior à data final.');
        }
        foreach (['ordem', 'dir'] as $campo) {
            if (isset($entrada[$campo]) && !is_string($entrada[$campo])) {
                throw new InvalidArgumentException('Ordenação inválida.');
            }
        }
        $ordem = in_array($entrada['ordem'] ?? '', self::ORDEM, true) ? $entrada['ordem'] : 'criado_em';
        $dir = strtoupper($entrada['dir'] ?? 'DESC');
        $dir = in_array($dir, ['ASC', 'DESC'], true) ? $dir : 'DESC';
        $pagina = $entrada['p'] ?? 1;
        if (!is_string($pagina) && !is_int($pagina)) {
            throw new InvalidArgumentException('Página inválida.');
        }
        $pagina = filter_var($pagina, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: 1;
        return ['filtros' => $filtros, 'ordem' => $ordem, 'dir' => $dir, 'pagina' => $pagina];
    }

    private function consulta(array $filtros, bool $detalhes = false): array
    {
        $partes = [];
        $params = [];
        foreach (['auditoria', 'eliminacao'] as $origem) {
            if ($filtros['origem'] !== '' && $filtros['origem'] !== $origem) {
                continue;
            }
            $acao = $origem === 'auditoria' ? 'r.acao' : "CONCAT('Eliminou ', r.tabela)";
            $utilizador = $origem === 'auditoria' ? 'r.utilizador_id' : 'r.apagado_por';
            $tabela = $origem === 'auditoria' ? 'auditoria' : 'auditoria_eliminacoes';
            $detalhe = $origem === 'auditoria' ? 'r.detalhes' : 'r.dados';
            $sql = "SELECT r.id, {$utilizador} AS utilizador_id, u.nome AS utilizador_nome,
                    {$acao} AS acao, r.ip, r.criado_em, '{$origem}' AS origem";
            if ($detalhes) {
                $sql .= ", {$detalhe} AS detalhes";
            }
            $sql .= " FROM {$tabela} r LEFT JOIN utilizadores u ON u.id = {$utilizador} WHERE 1=1";
            foreach (['utilizador' => 'u.nome', 'ip' => 'r.ip', 'acao' => $acao] as $campo => $coluna) {
                if ($filtros[$campo] !== '') {
                    $sql .= " AND {$coluna} LIKE ?";
                    $params[] = '%' . $filtros[$campo] . '%';
                }
            }
            if ($filtros['data_inicio'] !== '') {
                $sql .= ' AND r.criado_em >= ?';
                $params[] = $filtros['data_inicio'] . ' 00:00:00';
            }
            if ($filtros['data_fim'] === '9999-12-31') {
                $sql .= ' AND r.criado_em <= ?';
                $params[] = '9999-12-31 23:59:59.999999';
            } elseif ($filtros['data_fim'] !== '') {
                $sql .= ' AND r.criado_em < ?';
                $params[] = (new DateTimeImmutable($filtros['data_fim']))->modify('+1 day')->format('Y-m-d H:i:s');
            }
            $partes[] = $sql;
        }
        return [implode(' UNION ALL ', $partes), $params];
    }

    private function executar(string $sql, array $params): PDOStatement
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    public function listar(array $entrada = []): array
    {
        $opcoes = $this->normalizar($entrada);
        [$sql, $params] = $this->consulta($opcoes['filtros']);
        $total = (int) $this->executar("SELECT COUNT(*) FROM ({$sql}) logs", $params)->fetchColumn();
        $paginas = max(1, (int) ceil($total / 20));
        $pagina = min($opcoes['pagina'], $paginas);
        $offset = ($pagina - 1) * 20;
        $ordem = $opcoes['ordem'];
        $dir = $opcoes['dir'];
        $logs = $this->executar("SELECT * FROM ({$sql}) logs ORDER BY {$ordem} {$dir}, origem ASC, id DESC LIMIT 20 OFFSET {$offset}", $params)->fetchAll(PDO::FETCH_ASSOC);
        return array_replace($opcoes, ['logs' => $logs, 'total' => $total, 'pagina' => $pagina, 'paginas' => $paginas]);
    }

    public function obter(mixed $id, mixed $origem = ''): ?array
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $id) || filter_var($id, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Identificador de auditoria inválido.');
        }
        $opcoes = $this->normalizar(['origem' => $origem]);
        [$sql, $params] = $this->consulta($opcoes['filtros'], true);
        $params[] = (int) $id;
        $log = $this->executar("SELECT * FROM ({$sql}) logs WHERE id = ? ORDER BY origem ASC LIMIT 1", $params)->fetch(PDO::FETCH_ASSOC);
        return $log ?: null;
    }

    public function exportar(array $entrada = []): PDOStatement
    {
        $opcoes = $this->normalizar($entrada);
        [$sql, $params] = $this->consulta($opcoes['filtros'], true);
        return $this->executar("SELECT * FROM ({$sql}) logs ORDER BY {$opcoes['ordem']} {$opcoes['dir']}, origem ASC, id DESC", $params);
    }

    public function dashboard(array $entrada = []): array
    {
        $opcoes = $this->normalizar($entrada);
        [$sql, $params] = $this->consulta($opcoes['filtros']);
        $resumo = $this->executar("SELECT COUNT(*) AS total,
            COALESCE(SUM(CASE WHEN origem = 'auditoria' THEN 1 ELSE 0 END), 0) AS gerais,
            COALESCE(SUM(CASE WHEN origem = 'eliminacao' THEN 1 ELSE 0 END), 0) AS eliminacoes FROM ({$sql}) logs", $params)->fetch(PDO::FETCH_ASSOC);
        $porAcao = $this->executar("SELECT acao, COUNT(*) AS total FROM ({$sql}) logs GROUP BY acao ORDER BY total DESC, acao ASC LIMIT 20", $params)->fetchAll(PDO::FETCH_ASSOC);
        $porDia = $this->executar("SELECT DATE(criado_em) AS dia, COUNT(*) AS total FROM ({$sql}) logs GROUP BY DATE(criado_em) ORDER BY dia DESC", $params)->fetchAll(PDO::FETCH_ASSOC);
        return $opcoes + ['resumo' => $resumo, 'porAcao' => $porAcao, 'porDia' => $porDia,
            'maxAcao' => max([1, ...array_column($porAcao, 'total')]), 'maxDia' => max([1, ...array_column($porDia, 'total')])];
    }

    public static function escreverCSV($stream, PDOStatement $registos): void
    {
        fwrite($stream, "\xEF\xBB\xBF");
        fputcsv($stream, ['ID', 'Utilizador ID', 'Utilizador', 'Ação', 'IP', 'Data', 'Origem', 'Detalhes'], ';', '"', '');
        while ($log = $registos->fetch(PDO::FETCH_ASSOC)) {
            $valores = [];
            foreach (['id', 'utilizador_id', 'utilizador_nome', 'acao', 'ip', 'criado_em', 'origem', 'detalhes'] as $campo) {
                $valor = (string) ($log[$campo] ?? '');
                if (preg_match('/\A(?:[\x00-\x20]*[=+\-@]|[\t\r\n])/', $valor)) {
                    $valor = "'" . $valor;
                }
                $valores[] = $valor;
            }
            fputcsv($stream, $valores, ';', '"', '');
        }
    }
}
