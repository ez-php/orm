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
 * @phpstan-type BasicWhere array{type: 'basic', boolean: string, column: string, operator: string, value: mixed}
 * @phpstan-type InWhere    array{type: 'in',    boolean: string, column: string, values: list<mixed>, not: bool}
 * @phpstan-type NullWhere  array{type: 'null',  boolean: string, column: string, not: bool}
 */
final class QueryBuilder
{
    /**
     * @var list<string>
     */
    private array $columns = ['*'];

    /**
     * @var list<BasicWhere|InWhere|NullWhere>
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
     * QueryBuilder Constructor
     *
     * @param DatabaseInterface $db
     * @param string            $table
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $table,
    ) {
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
    // EXECUTION
    // -------------------------------------------------------------------------

    /**
     * @return list<array<string, mixed>>
     */
    public function get(): array
    {
        $bindings = [...$this->collectWhereBindings(), ...$this->collectHavingBindings()];

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
        $sql = 'SELECT COUNT(*) as aggregate FROM ' . $this->table . $this->buildJoins() . $this->buildWhere();
        $result = $this->db->query($sql, $this->collectWhereBindings());

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
        return 'SELECT ' . implode(', ', $this->columns)
            . ' FROM ' . $this->table
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
     * @param BasicWhere|InWhere|NullWhere $where
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
