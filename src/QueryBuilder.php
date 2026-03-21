<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use InvalidArgumentException;

/**
 * Class QueryBuilder
 *
 * @package EzPhp\Orm
 *
 * @phpstan-type BasicWhere       array{type: 'basic',        boolean: string, column: string, operator: string, value: mixed}
 * @phpstan-type InWhere          array{type: 'in',           boolean: string, column: string, values: list<mixed>, not: bool}
 * @phpstan-type NullWhere        array{type: 'null',         boolean: string, column: string, not: bool}
 * @phpstan-type SubqueryWhere    array{type: 'subquery',     boolean: string, column: string, operator: string, subquery: QueryBuilder}
 * @phpstan-type JsonContainsWhere array{type: 'json_contains', boolean: string, column: string, value: mixed}
 * @phpstan-type ExistsWhere      array{type: 'exists',       boolean: string, not: bool, sql: string, bindings: list<mixed>}
 */
final class QueryBuilder
{
    /**
     * @var list<string>
     */
    private array $columns = ['*'];

    /**
     * @var list<BasicWhere|InWhere|NullWhere|SubqueryWhere|JsonContainsWhere|ExistsWhere>
     */
    private array $wheres = [];

    /**
     * @var list<array{type: string, table: string, first: string, operator: string, second: string}>
     */
    private array $joins = [];

    /**
     * @var list<string>
     */
    private array $groupBys = [];

    /**
     * @var list<array{column: string, operator: string, value: mixed}>
     */
    private array $havings = [];

    /**
     * @var list<array{column: string, direction: string}>
     */
    private array $orders = [];

    private ?int $limitValue = null;

    private ?int $offsetValue = null;

    /**
     * Detected PDO driver name (sqlite or mysql).
     */
    private string $driver = '';

    /**
     * Optional FROM expression when using fromSub().
     */
    private ?string $fromExpression = null;

    /**
     * Bindings for the fromSub() derived table.
     *
     * @var list<mixed>
     */
    private array $fromBindings = [];

    /**
     * QueryBuilder Constructor
     *
     * @param DatabaseInterface $db
     * @param string            $table
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $table,
    ) {
        $driverAttr = $db->getPdo()->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $this->driver = is_string($driverAttr) ? $driverAttr : '';
    }

    // -------------------------------------------------------------------------
    // SELECT
    // -------------------------------------------------------------------------

    /**
     * @param string ...$columns
     *
     * @return self
     */
    public function select(string ...$columns): self
    {
        $clone = clone($this, [
            'columns' => $columns === [] ? ['*'] : array_values($columns),
        ]);

        return $clone;
    }

    // -------------------------------------------------------------------------
    // WHERE
    // -------------------------------------------------------------------------

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->addBasicWhere('AND', $column, $operatorOrValue, $value);
    }

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self
     */
    public function orWhere(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return $this->addBasicWhere('OR', $column, $operatorOrValue, $value);
    }

    /**
     * @param string       $column
     * @param array<mixed> $values
     *
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'in', 'boolean' => 'AND', 'column' => $column, 'values' => array_values($values), 'not' => false];

        return $clone;
    }

    /**
     * @param string       $column
     * @param array<mixed> $values
     *
     * @return self
     */
    public function whereNotIn(string $column, array $values): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'in', 'boolean' => 'AND', 'column' => $column, 'values' => array_values($values), 'not' => true];

        return $clone;
    }

    /**
     * @param string $column
     *
     * @return self
     */
    public function whereNull(string $column): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'null', 'boolean' => 'AND', 'column' => $column, 'not' => false];

        return $clone;
    }

    /**
     * @param string $column
     *
     * @return self
     */
    public function whereNotNull(string $column): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'null', 'boolean' => 'AND', 'column' => $column, 'not' => true];

        return $clone;
    }

    /**
     * Add a WHERE EXISTS or WHERE NOT EXISTS condition.
     *
     * @param string      $subquerySql
     * @param list<mixed> $bindings
     *
     * @return self
     */
    public function whereExists(string $subquerySql, array $bindings = []): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'exists', 'boolean' => 'AND', 'not' => false, 'sql' => $subquerySql, 'bindings' => $bindings];

        return $clone;
    }

    /**
     * Add a WHERE NOT EXISTS condition.
     *
     * @param string      $subquerySql
     * @param list<mixed> $bindings
     *
     * @return self
     */
    public function whereNotExists(string $subquerySql, array $bindings = []): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'exists', 'boolean' => 'AND', 'not' => true, 'sql' => $subquerySql, 'bindings' => $bindings];

        return $clone;
    }

    /**
     * Add a WHERE JSON_CONTAINS condition.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return self
     */
    public function whereJsonContains(string $column, mixed $value): self
    {
        $clone = clone $this;
        $clone->wheres[] = ['type' => 'json_contains', 'boolean' => 'AND', 'column' => $column, 'value' => $value];

        return $clone;
    }

    // -------------------------------------------------------------------------
    // JOIN
    // -------------------------------------------------------------------------

    /**
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $clone = clone $this;
        $clone->joins[] = ['type' => 'INNER', 'table' => $table, 'first' => $first, 'operator' => $operator, 'second' => $second];

        return $clone;
    }

    /**
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return self
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        $clone = clone $this;
        $clone->joins[] = ['type' => 'LEFT', 'table' => $table, 'first' => $first, 'operator' => $operator, 'second' => $second];

        return $clone;
    }

    // -------------------------------------------------------------------------
    // GROUP BY / HAVING
    // -------------------------------------------------------------------------

    /**
     * @param string ...$columns
     *
     * @return self
     */
    public function groupBy(string ...$columns): self
    {
        $clone = clone($this, [
            'groupBys' => [...$this->groupBys, ...array_values($columns)],
        ]);

        return $clone;
    }

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self
     */
    public function having(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $clone = clone $this;

        static $operators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];
        $isOperator = is_string($operatorOrValue)
            && in_array(strtolower($operatorOrValue), $operators, true);

        if ($isOperator) {
            $clone->havings[] = ['column' => $column, 'operator' => strtoupper($operatorOrValue), 'value' => $value];
        } else {
            $clone->havings[] = ['column' => $column, 'operator' => '=', 'value' => $operatorOrValue];
        }

        return $clone;
    }

    // -------------------------------------------------------------------------
    // ORDER / LIMIT / OFFSET
    // -------------------------------------------------------------------------

    /**
     * @param string $column
     * @param string $direction
     *
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $clone = clone $this;
        $clone->orders[] = ['column' => $column, 'direction' => strtoupper($direction)];

        return $clone;
    }

    /**
     * @param int $limit
     *
     * @return self
     */
    public function limit(int $limit): self
    {
        $clone = clone($this, [
            'limitValue' => $limit,
        ]);

        return $clone;
    }

    /**
     * @param int $offset
     *
     * @return self
     */
    public function offset(int $offset): self
    {
        $clone = clone($this, [
            'offsetValue' => $offset,
        ]);

        return $clone;
    }

    // -------------------------------------------------------------------------
    // fromSub
    // -------------------------------------------------------------------------

    /**
     * Use a subquery as the FROM source (derived table).
     *
     * @param QueryBuilder $subquery
     * @param string       $alias
     *
     * @return self
     */
    public function fromSub(QueryBuilder $subquery, string $alias): self
    {
        $clone = clone($this, [
            'fromExpression' => '(' . $subquery->toSql() . ') AS ' . $alias,
            'fromBindings' => $subquery->getBindings(),
        ]);

        return $clone;
    }

    // -------------------------------------------------------------------------
    // EXECUTION
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $bindings = [...$this->fromBindings, ...$this->collectWhereBindings(), ...$this->collectHavingBindings()];

        return $this->db->query($this->buildSelectSql(), $bindings);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function first(): ?array
    {
        $results = $this->limit(1)->get();

        return $results[0] ?? null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        $from = $this->fromExpression ?? $this->table;
        $sql = 'SELECT COUNT(*) as aggregate FROM ' . $from . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, [...$this->fromBindings, ...$this->collectWhereBindings()]);

        /** @var int $count */
        $count = $result[0]['aggregate'] ?? 0;

        return $count;
    }

    /**
     * @param string $column
     *
     * @return float
     */
    public function sum(string $column): float
    {
        $sql = 'SELECT SUM(' . $column . ') as aggregate FROM ' . $this->table . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, $this->collectWhereBindings());

        /** @var float|int|string|null $raw */
        $raw = $result[0]['aggregate'] ?? null;

        return (float) ($raw ?? 0);
    }

    /**
     * @param string $column
     *
     * @return float
     */
    public function avg(string $column): float
    {
        $sql = 'SELECT AVG(' . $column . ') as aggregate FROM ' . $this->table . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, $this->collectWhereBindings());

        /** @var float|int|string|null $raw */
        $raw = $result[0]['aggregate'] ?? null;

        return (float) ($raw ?? 0);
    }

    /**
     * @param string $column
     *
     * @return string|int|float|null
     */
    public function min(string $column): string|int|float|null
    {
        $sql = 'SELECT MIN(' . $column . ') as aggregate FROM ' . $this->table . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, $this->collectWhereBindings());

        /** @var string|int|float|null $value */
        $value = $result[0]['aggregate'] ?? null;

        return $value;
    }

    /**
     * @param string $column
     *
     * @return string|int|float|null
     */
    public function max(string $column): string|int|float|null
    {
        $sql = 'SELECT MAX(' . $column . ') as aggregate FROM ' . $this->table . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, $this->collectWhereBindings());

        /** @var string|int|float|null $value */
        $value = $result[0]['aggregate'] ?? null;

        return $value;
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return bool
     */
    public function insert(array $data): bool
    {
        if ($data === []) {
            throw new InvalidArgumentException('insert() requires at least one column.');
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders)";

        $stmt = $this->db->getPdo()->prepare($sql);

        return $stmt->execute(array_values($data));
    }

    /**
     * Insert multiple rows in a single statement.
     *
     * @param list<array<string, mixed>> $rows
     *
     * @return bool
     */
    public function insertBatch(array $rows): bool
    {
        if ($rows === []) {
            throw new InvalidArgumentException('insertBatch() requires at least one row.');
        }

        $keys = array_keys($rows[0]);

        foreach ($rows as $row) {
            if (array_keys($row) !== $keys) {
                throw new InvalidArgumentException('insertBatch() rows must all have the same keys.');
            }
        }

        $columns = implode(', ', $keys);
        $rowPlaceholder = '(' . implode(', ', array_fill(0, count($keys), '?')) . ')';
        $allPlaceholders = implode(', ', array_fill(0, count($rows), $rowPlaceholder));

        $sql = "INSERT INTO $this->table ($columns) VALUES $allPlaceholders";

        $bindings = [];
        foreach ($rows as $row) {
            foreach ($row as $value) {
                $bindings[] = $value;
            }
        }

        $stmt = $this->db->getPdo()->prepare($sql);

        return $stmt->execute($bindings);
    }

    /**
     * Insert a row or update on duplicate key / unique conflict.
     *
     * @param array<string, mixed> $data      Full row data
     * @param list<string>         $uniqueBy  Conflict columns
     * @param list<string>         $update    Columns to update on conflict (defaults to all non-unique columns)
     *
     * @return bool
     */
    public function upsert(array $data, array $uniqueBy, array $update = []): bool
    {
        if ($data === []) {
            throw new InvalidArgumentException('upsert() requires non-empty data.');
        }

        if ($uniqueBy === []) {
            throw new InvalidArgumentException('upsert() requires at least one unique-by column.');
        }

        // Derive update columns if not provided
        if ($update === []) {
            $update = array_values(array_diff(array_keys($data), $uniqueBy));
        }

        $columns = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));

        if ($this->driver === 'mysql') {
            if ($update === []) {
                $firstKey = array_keys($data)[0];
                $noOp = "$firstKey = VALUES($firstKey)";
                $updateSql = $noOp;
            } else {
                $sets = array_map(static fn (string $col) => "$col = VALUES($col)", $update);
                $updateSql = implode(', ', $sets);
            }

            $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders) ON DUPLICATE KEY UPDATE $updateSql";
        } else {
            // SQLite
            $uniqueCols = implode(', ', $uniqueBy);

            if ($update === []) {
                $firstKey = array_keys($data)[0];
                $noOp = "$firstKey = excluded.$firstKey";
                $updateSql = $noOp;
            } else {
                $sets = array_map(static fn (string $col) => "$col = excluded.$col", $update);
                $updateSql = implode(', ', $sets);
            }

            $sql = "INSERT INTO $this->table ($columns) VALUES ($placeholders) ON CONFLICT($uniqueCols) DO UPDATE SET $updateSql";
        }

        $stmt = $this->db->getPdo()->prepare($sql);

        return $stmt->execute(array_values($data));
    }

    /**
     * @param array<string, mixed> $data
     *
     * @return int
     */
    public function update(array $data): int
    {
        $sets = [];
        foreach (array_keys($data) as $col) {
            $sets[] = "$col = ?";
        }

        $sql = 'UPDATE ' . $this->table . ' SET ' . implode(', ', $sets) . $this->buildWhere();
        $bindings = array_merge(array_values($data), $this->collectWhereBindings());

        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($bindings);

        return $stmt->rowCount();
    }

    /**
     * Paginate the query results.
     *
     * @param int $perPage
     * @param int $page
     *
     * @return Paginator<array<string, mixed>>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->count();
        $items = $this->limit($perPage)->offset(($page - 1) * $perPage)->get();

        return new Paginator($items, $total, $perPage, $page);
    }

    /**
     * Chunk through the query results in pages of the given size.
     *
     * @param int                                        $size
     * @param callable(list<array<string, mixed>>): void $callback
     *
     * @return void
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 1;

        do {
            $rows = $this->limit($size)->offset(($page - 1) * $size)->get();

            if ($rows === []) {
                break;
            }

            $callback($rows);
            $page++;
        } while (count($rows) === $size);
    }

    /**
     * @return int
     */
    public function delete(): int
    {
        $sql = 'DELETE FROM ' . $this->table . $this->buildWhere();

        $stmt = $this->db->getPdo()->prepare($sql);
        $stmt->execute($this->collectWhereBindings());

        return $stmt->rowCount();
    }

    // -------------------------------------------------------------------------
    // PUBLIC SQL ACCESS (for subquery support)
    // -------------------------------------------------------------------------

    /**
     * Return the SELECT SQL string without executing.
     *
     * @return string
     */
    public function toSql(): string
    {
        return $this->buildSelectSql();
    }

    /**
     * Return all bindings for the current SELECT query.
     *
     * @return list<mixed>
     */
    public function getBindings(): array
    {
        return [...$this->fromBindings, ...$this->collectWhereBindings(), ...$this->collectHavingBindings()];
    }

    // -------------------------------------------------------------------------
    // PRIVATE HELPERS
    // -------------------------------------------------------------------------

    /**
     * @param string $boolean
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self
     */
    private function addBasicWhere(string $boolean, string $column, mixed $operatorOrValue, mixed $value): self
    {
        $clone = clone $this;

        static $operators = ['=', '!=', '<>', '<', '>', '<=', '>=', 'like', 'not like'];

        // Three-arg form with a QueryBuilder subquery
        if ($value instanceof self) {
            $clone->wheres[] = [
                'type' => 'subquery',
                'boolean' => $boolean,
                'column' => $column,
                'operator' => is_string($operatorOrValue) ? strtoupper($operatorOrValue) : '=',
                'subquery' => $value,
            ];

            return $clone;
        }

        // Two-arg form with a QueryBuilder subquery — defaults to IN
        if ($value === null && $operatorOrValue instanceof self) {
            $clone->wheres[] = [
                'type' => 'subquery',
                'boolean' => $boolean,
                'column' => $column,
                'operator' => 'IN',
                'subquery' => $operatorOrValue,
            ];

            return $clone;
        }

        if ($value !== null) {
            // Three-argument form: operatorOrValue MUST be a valid operator string.
            if (!is_string($operatorOrValue) || !in_array(strtolower($operatorOrValue), $operators, true)) {
                throw new InvalidArgumentException(
                    'Invalid WHERE operator: ' . var_export($operatorOrValue, true) . '. Allowed: ' . implode(', ', $operators)
                );
            }

            $clone->wheres[] = ['type' => 'basic', 'boolean' => $boolean, 'column' => $column, 'operator' => strtoupper($operatorOrValue), 'value' => $value];
        } else {
            // Two-argument form: operatorOrValue IS the value, operator defaults to '='.
            $clone->wheres[] = ['type' => 'basic', 'boolean' => $boolean, 'column' => $column, 'operator' => '=', 'value' => $operatorOrValue];
        }

        return $clone;
    }

    /**
     * @return list<mixed>
     */
    private function collectWhereBindings(): array
    {
        $bindings = [];

        foreach ($this->wheres as $where) {
            if ($where['type'] === 'basic') {
                $bindings[] = $where['value'];
            } elseif ($where['type'] === 'in') {
                foreach ($where['values'] as $v) {
                    $bindings[] = $v;
                }
            } elseif ($where['type'] === 'subquery') {
                foreach ($where['subquery']->getBindings() as $b) {
                    $bindings[] = $b;
                }
            } elseif ($where['type'] === 'json_contains') {
                if ($this->driver === 'mysql') {
                    $bindings[] = json_encode($where['value']);
                } else {
                    $bindings[] = $where['value'];
                }
            } elseif ($where['type'] === 'exists') {
                foreach ($where['bindings'] as $b) {
                    $bindings[] = $b;
                }
            }
            // 'null' type produces no bindings
        }

        return $bindings;
    }

    /**
     * @return list<mixed>
     */
    private function collectHavingBindings(): array
    {
        $bindings = [];

        foreach ($this->havings as $having) {
            $bindings[] = $having['value'];
        }

        return $bindings;
    }

    /**
     * @return string
     */
    private function buildSelectSql(): string
    {
        $from = $this->fromExpression ?? $this->table;

        return 'SELECT ' . implode(', ', $this->columns)
            . ' FROM ' . $from
            . $this->buildJoins()
            . $this->buildWhere()
            . $this->buildGroupBy()
            . $this->buildHaving()
            . $this->buildOrder()
            . $this->buildLimit();
    }

    /**
     * @return string
     */
    private function buildJoins(): string
    {
        if ($this->joins === []) {
            return '';
        }

        $clauses = [];
        foreach ($this->joins as $join) {
            $clauses[] = "{$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        return ' ' . implode(' ', $clauses);
    }

    /**
     * @return string
     */
    private function buildWhere(): string
    {
        if ($this->wheres === []) {
            return '';
        }

        $parts = [];
        foreach ($this->wheres as $i => $where) {
            $clause = $this->buildWhereClause($where);
            $parts[] = $i === 0 ? $clause : $where['boolean'] . ' ' . $clause;
        }

        return ' WHERE ' . implode(' ', $parts);
    }

    /**
     * @param BasicWhere|InWhere|NullWhere|SubqueryWhere|JsonContainsWhere|ExistsWhere $where
     *
     * @return string
     */
    private function buildWhereClause(array $where): string
    {
        if ($where['type'] === 'basic') {
            return "{$where['column']} {$where['operator']} ?";
        }

        if ($where['type'] === 'in') {
            $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
            $not = $where['not'] ? 'NOT ' : '';

            return "{$where['column']} {$not}IN ($placeholders)";
        }

        if ($where['type'] === 'subquery') {
            // IN/NOT IN subquery uses parentheses around the subquery; scalar comparison uses scalar form.
            return "{$where['column']} {$where['operator']} ({$where['subquery']->toSql()})";
        }

        if ($where['type'] === 'json_contains') {
            if ($this->driver === 'mysql') {
                return "JSON_CONTAINS({$where['column']}, ?)";
            }

            // SQLite: check if the value exists in the JSON array
            return "EXISTS (SELECT 1 FROM json_each({$where['column']}) WHERE json_each.value = ?)";
        }

        if ($where['type'] === 'exists') {
            $not = $where['not'] ? 'NOT ' : '';

            return "{$not}EXISTS ({$where['sql']})";
        }

        // type === 'null'
        $not = $where['not'] ? 'NOT ' : '';

        return "{$where['column']} IS {$not}NULL";
    }

    /**
     * @return string
     */
    private function buildGroupBy(): string
    {
        if ($this->groupBys === []) {
            return '';
        }

        return ' GROUP BY ' . implode(', ', $this->groupBys);
    }

    /**
     * @return string
     */
    private function buildHaving(): string
    {
        if ($this->havings === []) {
            return '';
        }

        $clauses = [];
        foreach ($this->havings as $having) {
            $clauses[] = "{$having['column']} {$having['operator']} ?";
        }

        return ' HAVING ' . implode(' AND ', $clauses);
    }

    /**
     * @return string
     */
    private function buildOrder(): string
    {
        if ($this->orders === []) {
            return '';
        }

        $clauses = [];
        foreach ($this->orders as $order) {
            $clauses[] = "{$order['column']} {$order['direction']}";
        }

        return ' ORDER BY ' . implode(', ', $clauses);
    }

    /**
     * @return string
     */
    private function buildLimit(): string
    {
        $sql = '';

        if ($this->limitValue !== null) {
            $sql .= ' LIMIT ' . $this->limitValue;
        } elseif ($this->offsetValue !== null) {
            $sql .= ' LIMIT ' . PHP_INT_MAX;
        }

        if ($this->offsetValue !== null) {
            $sql .= ' OFFSET ' . $this->offsetValue;
        }

        return $sql;
    }
}
