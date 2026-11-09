<?php

namespace App\Services;

use App\Core\Conexao;
use DateTimeImmutable;
use Generator;
use InvalidArgumentException;
use PDO;

class LogsSistemaConsultaService
{
    private PDO $db;
    public function __construct(?PDO $db = null) { $this->db = $db ?? Conexao::getInstancia(); }

    public function validarId(mixed $id): int
    {
        if ((!is_string($id) && !is_int($id)) || !preg_match('/\A[1-9][0-9]*\z/', (string) $id) || filter_var($id, FILTER_VALIDATE_INT) === false) {
            throw new InvalidArgumentException('Identificador de log inválido.');
        }
        return (int) $id;
    }

    public function normalizar(array $entrada): array
    {
        $filtros = [];
        foreach (['tipo'=>50, 'ip'=>45, 'data_inicio'=>10, 'data_fim'=>10, 'mensagem'=>500] as $campo=>$limite) {
            $valor = $entrada[$campo] ?? '';
            if (!is_string($valor) || !mb_check_encoding($valor, 'UTF-8') || str_contains($valor, "\0")) {
                throw new InvalidArgumentException('Os filtros devem conter texto válido.');
            }
            $valor = trim($valor);
            if (mb_strlen($valor, 'UTF-8') > $limite) { throw new InvalidArgumentException('O filtro excede o limite permitido.'); }
            $filtros[$campo] = $valor;
        }
        if ($filtros['ip'] !== '' && filter_var($filtros['ip'], FILTER_VALIDATE_IP) === false) {
            throw new InvalidArgumentException('Introduza um endereço IPv4 ou IPv6 válido.');
        }
        foreach (['data_inicio','data_fim'] as $campo) {
            if ($filtros[$campo] === '') { continue; }
            $data = DateTimeImmutable::createFromFormat('!Y-m-d', $filtros[$campo]);
            if (!$data || $data->format('Y-m-d') !== $filtros[$campo] || $data->format('Y') === '0000') {
                throw new InvalidArgumentException('Introduza datas válidas no formato AAAA-MM-DD.');
            }
        }
        if ($filtros['data_inicio'] !== '' && $filtros['data_fim'] !== '' && $filtros['data_inicio'] > $filtros['data_fim']) {
            throw new InvalidArgumentException('A data inicial não pode ser posterior à data final.');
        }
        $pagina = $entrada['p'] ?? 1;
        if (!is_int($pagina) && !is_string($pagina)) { throw new InvalidArgumentException('Página inválida.'); }
        $pagina = filter_var($pagina, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]) ?: 1;
        $id = isset($entrada['id']) && $entrada['id'] !== '' ? $this->validarId($entrada['id']) : null;
        return ['filtros'=>$filtros, 'pagina'=>$pagina, 'id'=>$id];
    }

    private function condicao(array $opcoes): array
    {
        $sql = ' WHERE 1=1'; $params = [];
        foreach (['tipo','ip'] as $campo) {
            if ($opcoes['filtros'][$campo] !== '') { $sql .= " AND {$campo} = ?"; $params[] = $opcoes['filtros'][$campo]; }
        }
        if ($opcoes['filtros']['mensagem'] !== '') { $sql .= ' AND mensagem LIKE ?'; $params[] = '%'.$opcoes['filtros']['mensagem'].'%'; }
        if ($opcoes['filtros']['data_inicio'] !== '') { $sql .= ' AND criado_em >= ?'; $params[] = $opcoes['filtros']['data_inicio'].' 00:00:00'; }
        $fim = $opcoes['filtros']['data_fim'];
        if ($fim === '9999-12-31') { $sql .= ' AND criado_em <= ?'; $params[] = '9999-12-31 23:59:59.999999'; }
        elseif ($fim !== '') { $sql .= ' AND criado_em < ?'; $params[] = (new DateTimeImmutable($fim))->modify('+1 day')->format('Y-m-d H:i:s'); }
        if ($opcoes['id'] !== null) { $sql .= ' AND id = ?'; $params[] = $opcoes['id']; }
        return [$sql,$params];
    }

    public function listar(array $entrada = []): array
    {
        $opcoes = $this->normalizar($entrada); [$where,$params] = $this->condicao($opcoes);
        $stmt = $this->db->prepare('SELECT COUNT(*) FROM logs_sistema'.$where); $stmt->execute($params);
        $total = (int) $stmt->fetchColumn(); $paginas = max(1,(int) ceil($total/50)); $pagina = min($opcoes['pagina'],$paginas);
        $offset = ($pagina-1)*50;
        $stmt = $this->db->prepare('SELECT id, tipo, mensagem, ip, criado_em FROM logs_sistema'.$where." ORDER BY id DESC LIMIT 50 OFFSET {$offset}");
        $stmt->execute($params);
        $tipos = $this->db->query("SELECT DISTINCT tipo FROM logs_sistema WHERE tipo <> '' ORDER BY tipo")->fetchAll(PDO::FETCH_COLUMN);
        $tipos = array_values(array_unique(['erro','excecao','fatal','aviso','info','debug',...$tipos])); sort($tipos, SORT_STRING);
        return array_replace($opcoes,['logs'=>$stmt->fetchAll(PDO::FETCH_ASSOC),'total'=>$total,'pagina'=>$pagina,'paginas'=>$paginas,'tipos'=>$tipos]);
    }

    public function obter(mixed $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM logs_sistema WHERE id = ?'); $stmt->execute([$this->validarId($id)]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    public function exportar(array $entrada = []): Generator
    {
        // A validação e o limite superior são apurados antes de devolver o gerador.
        $opcoes = $this->normalizar($entrada); [$where,$params] = $this->condicao($opcoes);
        $stmt = $this->db->prepare('SELECT MAX(id) FROM logs_sistema'.$where); $stmt->execute($params); $maximo = (int) $stmt->fetchColumn();
        return (function () use ($where,$params,$maximo): Generator {
            $ultimo = null;
            do {
                $limite = $ultimo === null ? ' AND id <= ?' : ' AND id < ?';
                $stmt = $this->db->prepare('SELECT id, tipo, mensagem, ficheiro, linha, ip, criado_em FROM logs_sistema'.$where.$limite.' ORDER BY id DESC LIMIT 500');
                $stmt->execute([...$params, $ultimo ?? $maximo]); $lote = $stmt->fetchAll(PDO::FETCH_ASSOC); $stmt->closeCursor();
                foreach ($lote as $log) { $ultimo = (int) $log['id']; yield $log; }
            } while (count($lote) === 500);
        })();
    }

    public static function escreverCSV($stream, iterable $logs): void
    {
        fwrite($stream,"\xEF\xBB\xBF");
        fputcsv($stream,['ID','Tipo','Mensagem','Ficheiro','Linha','IP','Criado em'],',','"','');
        foreach ($logs as $log) {
            $valores = [];
            foreach (['id','tipo','mensagem','ficheiro','linha','ip','criado_em'] as $campo) {
                $valor = (string) ($log[$campo] ?? '');
                if (preg_match('/\A(?:[\x00-\x20]*[=+\-@]|[\t\r\n])/', $valor)) { $valor = "'".$valor; }
                $valores[] = $valor;
            }
            fputcsv($stream,$valores,',','"','');
        }
    }
}
