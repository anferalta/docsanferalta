<?php

namespace App\Services;

use App\Core\Conexao;
use DateTimeImmutable;
use InvalidArgumentException;
use PDO;

class RelatorioSLAService
{
    public const ESTADOS = ['pendente', 'analise', 'em_tramitacao', 'concluido'];

    public function __construct(private ?PDO $db = null)
    {
    }

    public function normalizarFiltros(array $entrada): array
    {
        $filtros = [];
        foreach (['area', 'estado', 'data_inicio', 'data_fim'] as $campo) {
            $valor = $entrada[$campo] ?? '';
            if (!is_string($valor) || str_contains($valor, "\0")) {
                throw new InvalidArgumentException('Os filtros devem conter valores de texto sem caracteres nulos.');
            }
            $filtros[$campo] = trim($valor);
        }

        if ($filtros['estado'] !== '' && !in_array($filtros['estado'], self::ESTADOS, true)) {
            throw new InvalidArgumentException('O estado selecionado é inválido.');
        }
        foreach (['data_inicio', 'data_fim'] as $campo) {
            if ($filtros[$campo] === '') {
                continue;
            }
            $data = DateTimeImmutable::createFromFormat('!Y-m-d', $filtros[$campo]);
            if (!$data || $data->format('Y-m-d') !== $filtros[$campo] || $data->format('Y') === '0000') {
                throw new InvalidArgumentException('As datas devem ser válidas e usar o formato AAAA-MM-DD.');
            }
        }
        if ($filtros['data_inicio'] !== '' && $filtros['data_fim'] !== ''
            && $filtros['data_inicio'] > $filtros['data_fim']) {
            throw new InvalidArgumentException('A data inicial não pode ser posterior à data final.');
        }

        return $filtros;
    }

    public function gerar(array $entrada = [], ?DateTimeImmutable $agora = null): array
    {
        $filtros = $this->normalizarFiltros($entrada);
        $agora ??= new DateTimeImmutable();
        $db = $this->db ?? Conexao::getInstancia();
        $sql = "SELECT d.id, d.titulo, d.estado_atual, d.area_atual_desde, d.criado_em,
                       a.nome AS area_nome, a.prazo_resposta
                FROM documentos d
                LEFT JOIN documento_areas a ON a.id = d.area_atual_id
                WHERE d.estado_atual NOT IN ('arquivado')";
        $params = [];
        foreach (['area' => 'a.nome', 'estado' => 'd.estado_atual'] as $campo => $coluna) {
            if ($filtros[$campo] !== '') {
                $sql .= " AND {$coluna} = ?";
                $params[] = $filtros[$campo];
            }
        }
        if ($filtros['data_inicio'] !== '') {
            $sql .= ' AND d.criado_em >= ?';
            $params[] = $filtros['data_inicio'] . ' 00:00:00';
        }
        if ($filtros['data_fim'] !== '') {
            // Limite exclusivo inclui todo o último dia, mesmo com microssegundos.
            if ($filtros['data_fim'] === '9999-12-31') {
                // Evita gerar o ano 10000, fora do intervalo DATETIME do MySQL.
                $sql .= ' AND d.criado_em <= ?';
                $params[] = '9999-12-31 23:59:59.999999';
            } else {
                $sql .= ' AND d.criado_em < ?';
                $params[] = (new DateTimeImmutable($filtros['data_fim']))->modify('+1 day')->format('Y-m-d H:i:s');
            }
        }
        $sql .= ' ORDER BY d.criado_em DESC, d.id DESC';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $documentos = [];
        $totais = ['ok' => 0, 'alerta' => 0, 'atrasado' => 0, 'indefinido' => 0];
        while ($documento = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $documento = $this->calcularDocumento($documento, $agora);
            $totais[$documento['sla']]++;
            $documentos[] = $documento;
        }

        return ['documentos' => $documentos, 'totais' => $totais, 'filtros' => $filtros, 'gerado_em' => $agora];
    }

    public function calcularDocumento(array $documento, DateTimeImmutable $agora): array
    {
        $documento['sla'] = 'indefinido';
        $documento['dias_parado'] = null;
        $prazo = filter_var($documento['prazo_resposta'] ?? null, FILTER_VALIDATE_INT);
        $valor = $documento['area_atual_desde'] ?? '';
        if (!is_string($valor) || !preg_match('/\A\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\z/', $valor) || $prazo === false || $prazo <= 0) {
            return $documento;
        }
        $inicio = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $valor);
        if (!$inicio || $inicio->format('Y-m-d H:i:s') !== $valor || $inicio > $agora) {
            return $documento;
        }

        // Mantém dias completos e a tolerância original de dois dias após o prazo.
        $dias = $inicio->diff($agora)->days;
        $documento['dias_parado'] = $dias;
        $documento['sla'] = $dias <= $prazo ? 'ok' : ($dias <= $prazo + 2 ? 'alerta' : 'atrasado');
        return $documento;
    }

    public function gerarHTML(array $relatorio): string
    {
        $docs = $relatorio['documentos'];
        $totais = $relatorio['totais'];
        $area = $relatorio['filtros']['area'];
        $estado = $relatorio['filtros']['estado'];
        $dataInicio = $relatorio['filtros']['data_inicio'];
        $dataFim = $relatorio['filtros']['data_fim'];
        $geradoEm = $relatorio['gerado_em'];
        ob_start();
        try {
            include __DIR__ . '/../Views/admin/relatorios/pdf_template.php';
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
