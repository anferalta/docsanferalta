<?php

namespace App\Core;

use PDO;
use Exception;

abstract class Model
{
    /** Tabela e chave primária */
    protected string $table;
    protected string $primaryKey = 'id';

    /** Atributos dinâmicos */
    protected array $attributes = [];

    /** Casts automáticos estilo Laravel */
    protected array $casts = [
        'id' => 'int',
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /** Query builder */
    protected array $wheres = [];
    protected array $bindings = [];
    protected ?string $order = null;
    protected ?int $limit = null;
    protected ?int $offset = null;
    protected array $joins = [];
    protected array $groupBy = [];
    protected array $having = [];

    /** ---------------------------------------------------------
     *  CONSTRUTOR — com type casting automático
     * --------------------------------------------------------- */
    public function __construct(array $attributes = [])
    {
        foreach ($attributes as $key => $value) {

            // Se existir cast definido, aplica-o
            if (isset($this->casts[$key])) {
                $value = $this->castValue($value, $this->casts[$key]);
            }

            // Guardar no array interno
            $this->attributes[$key] = $value;

            // Se existir propriedade pública, sincroniza
            if (property_exists($this, $key)) {
                $this->$key = $value;
            }
        }
    }

    /** ---------------------------------------------------------
     *  SET ATTRIBUTE — com casting automático
     * --------------------------------------------------------- */
    public function setAttribute(string $key, $value): void
    {
        if (isset($this->casts[$key])) {
            $value = $this->castValue($value, $this->casts[$key]);
        }

        $this->attributes[$key] = $value;

        // Se existir propriedade pública, sincroniza
        if (property_exists($this, $key)) {
            $this->$key = $value;
        }
    }

    /** ---------------------------------------------------------
     *  GET ATTRIBUTE
     * --------------------------------------------------------- */
    public function getAttribute(string $key)
    {
        return $this->attributes[$key] ?? null;
    }

    /** ---------------------------------------------------------
     *  CAST VALUE — estilo Laravel
     * --------------------------------------------------------- */
    protected function castValue($value, string $type)
    {
        if ($value === null) return null;

        return match ($type) {
            'int' => (int)$value,
            'float' => (float)$value,
            'bool' => (bool)$value,
            'datetime' => date('Y-m-d H:i:s', strtotime($value)),
            default => $value,
        };
    }

    /** ---------------------------------------------------------
     *  QUERY BUILDER
     * --------------------------------------------------------- */
    public static function query(): static
    {
        return new static();
    }

    public static function all(): array
    {
        return static::query()->get();
    }

    /** ---------------------------------------------------------
     *  FIND — com casting automático
     * --------------------------------------------------------- */
    public static function find($id): ?static
    {
        $instance = new static();
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("
        SELECT * FROM {$instance->table}
        WHERE {$instance->primaryKey} = :id
        LIMIT 1
    ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) return null;

        $obj = new static();

        foreach ($row as $key => $value) {
            $obj->setAttribute($key, $value);
        }

        return $obj;
    }

    /** ---------------------------------------------------------
     *  WHERE
     * --------------------------------------------------------- */
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

    /** ---------------------------------------------------------
     *  ORDER / LIMIT / OFFSET
     * --------------------------------------------------------- */
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

    /** ---------------------------------------------------------
     *  JOINS
     * --------------------------------------------------------- */
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

    /** ---------------------------------------------------------
     *  GROUP / HAVING
     * --------------------------------------------------------- */
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

    /** ---------------------------------------------------------
     *  GET — devolve array de objetos Model
     * --------------------------------------------------------- */
    public function get(): array
    {
        $sql = "SELECT * FROM {$this->table}";

        if ($this->joins) {
            $sql .= " " . implode(" ", $this->joins);
        }

        if ($this->wheres) {
            $sql .= " WHERE " . implode(" AND ", $this->wheres);
        }

        if ($this->groupBy) {
            $sql .= " GROUP BY " . implode(", ", $this->groupBy);
        }

        if ($this->having) {
            $sql .= " HAVING " . implode(" AND ", $this->having);
        }

        if ($this->order) {
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

        return array_map(function ($row) {
            $obj = new static();
            foreach ($row as $key => $value) {
                $obj->setAttribute($key, $value);
            }
            return $obj;
        }, $rows);
    }

    /** ---------------------------------------------------------
     *  FIRST
     * --------------------------------------------------------- */
    public function first(): ?static
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /** ---------------------------------------------------------
     *  INSERT
     * --------------------------------------------------------- */
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
        return (new static())->insert($data);
    }

    /** ---------------------------------------------------------
     *  UPDATE — versão segura
     * --------------------------------------------------------- */
    public function update(array $data, string $where, array $bindings = []): bool
    {
        $set = implode(", ", array_map(fn($c) => "$c = :$c", array_keys($data)));

        $sql = "UPDATE {$this->table} SET $set WHERE $where";

        $stmt = Conexao::getInstancia()->prepare($sql);
        return $stmt->execute(array_merge($data, $bindings));
    }

    /** ---------------------------------------------------------
     *  DELETE — versão profissional
     * --------------------------------------------------------- */
    public function delete($id = null): bool
    {
        $id = $id ?? $this->id;

        if (!$id) {
            throw new Exception("ID inválido para delete()");
        }

        $db = Conexao::getInstancia();

        // Auditoria
        $stmt = $db->prepare("SELECT * FROM {$this->table} WHERE id = :id");
        $stmt->execute(['id' => $id]);
        $dados = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($dados) {
            \App\Services\Auditoria::registarEliminacao($this->table, $id, $dados);
        }

        // Delete
        $stmt = $db->prepare("DELETE FROM {$this->table} WHERE id = :id");
        return $stmt->execute(['id' => $id]);
    }

    /** ---------------------------------------------------------
     *  COUNT
     * --------------------------------------------------------- */
    public static function count(): int
    {
        $instance = new static();
        $stmt = Conexao::getInstancia()->query("SELECT COUNT(*) FROM {$instance->table}");
        return (int) $stmt->fetchColumn();
    }

    public static function countWhere(string $condicao, array $bindings = []): int
    {
        $instance = new static();
        $stmt = Conexao::getInstancia()->prepare("SELECT COUNT(*) FROM {$instance->table} WHERE {$condicao}");
        $stmt->execute($bindings);
        return (int) $stmt->fetchColumn();
    }

    public static function deleteWhere(string $column, $value): bool
    {
        $instance = new static();
        $db = Conexao::getInstancia();

        $stmt = $db->prepare("DELETE FROM {$instance->table} WHERE {$column} = :v");
        return $stmt->execute(['v' => $value]);
    }

    /** ---------------------------------------------------------
     *  RESET QUERY BUILDER
     * --------------------------------------------------------- */
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
