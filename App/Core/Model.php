<?php

namespace App\Core;

use PDO;

abstract class Model
{
    protected string $table;
    protected string $primaryKey = 'id';
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $order = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];
    protected array $groupBy = [];
    protected array $having = [];

    public function __construct()
    {
        
    }

    public static function query(): static
    {
        return new static();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * FIND CORRIGIDO — CARREGA OBJETO COMPLETO
     */
    public static function find($id): ?static
    {

        $instance = new static();

        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT * FROM {$instance->table} WHERE {$instance->primaryKey} = :id LIMIT 1");
        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        $obj = new static();

        foreach ($row as $key => $value) {
            if (property_exists($obj, $key)) {
                $obj->$key = $value;
            }
        }

        return $obj;
    }

    public function where(string $column, string $operator, $value): static
    {
        $this->wheres[] = "$column $operator ?";
        $this->bindings[] = $value;
        return $this;
    }

    public function whereRaw(string $raw): static
    {
        $this->wheres[] = $raw;
        return $this;
    }

    public function orderBy(string $column, string $direction = 'ASC'): static
    {
        $this->order = "$column $direction";
        return $this;
    }

    public function limit(int $limit): static
    {
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static
    {
        $this->offset = $offset;
        return $this;
    }

    public function join(string $table, string $left, string $operator, string $right): static
    {
        $this->joins[] = "JOIN $table ON $left $operator $right";
        return $this;
    }

    public function leftJoin(string $table, string $left, string $operator, string $right): static
    {
        $this->joins[] = "LEFT JOIN $table ON $left $operator $right";
        return $this;
    }

    public function groupBy(string $column): static
    {
        $this->groupBy[] = $column;
        return $this;
    }

    public function having(string $condition): static
    {
        $this->having[] = $condition;
        return $this;
    }

    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if (!empty($this->joins)) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if (!empty($this->wheres)) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }

        if (!empty($this->groupBy)) {
            $sql .= " GROUP BY " . implode(", ", $this->groupBy);
        }

        if (!empty($this->having)) {
            $sql .= " HAVING " . implode(" AND ", $this->having);
        }

        if (!empty($this->order)) {
            $sql .= " ORDER BY {$this->order}";
        }

        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        $stmt = Conexao::getInstancia()->prepare($sql);

        foreach ($this->bindings as $i => $value) {
            $stmt->bindValue($i + 1, $value);
        }

        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $this->reset();

        if (empty($rows)) {
            return [];
        }

        $objects = [];

        foreach ($rows as $row) {
            $obj = new static();
            foreach ($row as $key => $value) {
                if (property_exists($obj, $key)) {
                    $obj->$key = $value;
                }
            }
            $objects[] = $obj;
        }

        return $objects;
    }

    public function first(): ?static
    {
        $this->limit(1);
        $results = $this->get();

        if (empty($results)) {
            return null;
        }

        return $results[0];
    }

    public function insert(array $data): int
    {
        $columns = array_keys($data);
        $placeholders = array_map(fn($c) => ":$c", $columns);

        $sql = "INSERT INTO {$this->table} (" . implode(",", $columns) . ")
                VALUES (" . implode(",", $placeholders) . ")";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute($data);

        return (int) Conexao::getInstancia()->lastInsertId();
    }

    public static function create(array $data): int
    {
        $instance = new static();
        return $instance->insert($data);
    }

    public function update(array $data, string $where, array $bindings = []): bool
    {
        $set = implode(", ", array_map(fn($c) => "$c = :$c", array_keys($data)));

        $sql = "UPDATE {$this->table} SET $set WHERE $where";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute(array_merge($data, $bindings));
    }

    public function delete($id)
    {
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dados) {
            \App\Services\Auditoria::registarEliminacao($this->table, $id, $dados);
        }

        $stmt = $db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
    }

    /**
     * MÉTODO count() RESTAURADO
     */
    public static function count(): int
    {
        $instance = new static();
        $db = Conexao::getInstancia();

        $stmt = $db->query("SELECT COUNT(*) FROM {$instance->table}");
        return (int) $stmt->fetchColumn();
    }

    public static function countWhere(string $condicao, array $bindings = []): int
    {
        $instance = new static();
        $sql = "SELECT COUNT(*) FROM {$instance->table} WHERE {$condicao}";

        $stmt = Conexao::getInstancia()->prepare($sql);
        $stmt->execute($bindings);

        return (int) $stmt->fetchColumn();
    }

    protected function reset(): void
    {
        $this->wheres = [];
        $this->bindings = [];
        $this->order = null;
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
        $this->groupBy = [];
        $this->having = [];
    }
}
