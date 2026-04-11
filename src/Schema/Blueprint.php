<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use InvalidArgumentException;

/**
 * Class Blueprint
 *
 * @package EzPhp\Orm\Schema
 */
final class Blueprint
{
    /**
     * @var list<ColumnDefinition>
     */
    private array $columns = [];

    /**
     * @var list<ColumnDefinition>
     */
    private array $addedColumns = [];

    /**
     * @var list<string>
     */
    private array $droppedColumns = [];

    /**
     * @var list<array{from: string, to: string}>
     */
    private array $renamedColumns = [];

    /**
     * @var list<ForeignKeyDefinition>
     */
    private array $foreignKeys = [];

    /**
     * @var list<array{columns: list<string>, name: string}>
     */
    private array $indexes = [];

    /**
     * Blueprint Constructor
     *
     * @param string $driver
     * @param string $mode   'create' or 'alter'
     */
    public function __construct(
        private readonly string $driver,
        private readonly string $mode = 'create',
    ) {
    }

    /**
     * @return ColumnDefinition
     */
    public function id(): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'INT UNSIGNED AUTO_INCREMENT';

        return $this->addColumn(new ColumnDefinition('id', $type, primaryKey: true));
    }

    /**
     * @param string $column
     * @param int    $length
     *
     * @return ColumnDefinition
     */
    public function string(string $column, int $length = 255): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : "VARCHAR($length)";

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function text(string $column): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($column, 'TEXT'));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function integer(string $column): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($column, 'INTEGER'));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function bigInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'BIGINT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function unsignedInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'INT UNSIGNED';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function float(string $column): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($column, 'FLOAT'));
    }

    /**
     * @param string $column
     * @param int    $precision
     * @param int    $scale
     *
     * @return ColumnDefinition
     */
    public function decimal(string $column, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $type = $this->isSqlite()
            ? "NUMERIC($precision,$scale)"
            : "DECIMAL($precision,$scale)";

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function date(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'DATE';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function json(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'JSON';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function boolean(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'TINYINT(1)';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function timestamp(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'TIMESTAMP';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @return void
     */
    public function timestamps(): void
    {
        $type = $this->isSqlite() ? 'TEXT' : 'TIMESTAMP';

        $this->addColumn(new ColumnDefinition('created_at', $type))->nullable()->default(null);
        $this->addColumn(new ColumnDefinition('updated_at', $type))->nullable()->default(null);
    }

    /**
     * Define an ENUM column.
     *
     * MySQL produces a native ENUM type; SQLite produces TEXT with a CHECK constraint.
     *
     * @param string       $column
     * @param list<string> $values
     *
     * @return ColumnDefinition
     */
    public function enum(string $column, array $values): ColumnDefinition
    {
        if ($values === []) {
            throw new InvalidArgumentException('enum() requires at least one value.');
        }

        foreach ($values as $v) {
            if ($v === '') {
                throw new InvalidArgumentException('enum() values must not be empty strings.');
            }

            if (!preg_match('/^[a-zA-Z0-9_\-]+$/', $v)) {
                throw new InvalidArgumentException(
                    "enum() value '$v' contains unsafe characters. Only alphanumeric characters, underscores, and hyphens are allowed."
                );
            }
        }

        $escapedValues = array_map(static fn (string $v) => "'" . str_replace("'", "''", $v) . "'", $values);

        if ($this->isSqlite()) {
            $col = $this->addColumn(new ColumnDefinition($column, 'TEXT'));
            $col->withCheck($this->quoteIdentifier($column) . ' IN (' . implode(', ', $escapedValues) . ')');

            return $col;
        }

        $type = 'ENUM(' . implode(', ', $escapedValues) . ')';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return void
     */
    public function dropColumn(string $column): void
    {
        $this->droppedColumns[] = $column;
    }

    /**
     * @param string $from
     * @param string $to
     *
     * @return void
     */
    public function renameColumn(string $from, string $to): void
    {
        $this->renamedColumns[] = ['from' => $from, 'to' => $to];
    }

    /**
     * @param string $column
     *
     * @return ForeignKeyDefinition
     */
    public function foreign(string $column): ForeignKeyDefinition
    {
        $fk = new ForeignKeyDefinition($column);
        $this->foreignKeys[] = $fk;

        return $fk;
    }

    /**
     * @param string|list<string> $columns
     * @param string              $name
     *
     * @return void
     */
    public function index(string|array $columns, string $name = ''): void
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->indexes[] = ['columns' => $columns, 'name' => $name];
    }

    /**
     * @param string $table
     *
     * @return string
     */
    public function toCreateSql(string $table): string
    {
        $parts = [];

        foreach ($this->columns as $col) {
            $parts[] = $this->compileColumn($col);
        }

        foreach ($this->foreignKeys as $fk) {
            $parts[] = 'FOREIGN KEY (' . $this->quoteIdentifier($fk->getColumn()) . ')'
                . ' REFERENCES ' . $this->quoteIdentifier($fk->getReferencedTable())
                . '(' . $this->quoteIdentifier($fk->getReferencedColumn()) . ')';
        }

        return 'CREATE TABLE ' . $this->quoteIdentifier($table) . ' (' . implode(', ', $parts) . ')';
    }

    /**
     * @param string $table
     *
     * @return list<string>
     */
    public function toAlterSql(string $table): array
    {
        $statements = [];
        $quotedTable = $this->quoteIdentifier($table);

        foreach ($this->addedColumns as $col) {
            if ($col->isChanged()) {
                // MODIFY COLUMN — MySQL only
                if (!$this->isSqlite()) {
                    $compiled = $this->compileColumn($col);
                    $suffix = $this->buildPositionSuffix($col);
                    $statements[] = 'ALTER TABLE ' . $quotedTable . ' MODIFY COLUMN ' . $compiled . $suffix;
                }
                // SQLite: skip MODIFY COLUMN silently (SQLite does not support it)
                continue;
            }

            $compiled = $this->compileColumn($col);
            $suffix = $this->buildPositionSuffix($col);
            $statements[] = 'ALTER TABLE ' . $quotedTable . ' ADD COLUMN ' . $compiled . $suffix;
        }

        foreach ($this->droppedColumns as $col) {
            $statements[] = 'ALTER TABLE ' . $quotedTable . ' DROP COLUMN ' . $this->quoteIdentifier($col);
        }

        foreach ($this->renamedColumns as $rename) {
            $statements[] = 'ALTER TABLE ' . $quotedTable
                . ' RENAME COLUMN ' . $this->quoteIdentifier($rename['from'])
                . ' TO ' . $this->quoteIdentifier($rename['to']);
        }

        if (!$this->isSqlite()) {
            foreach ($this->foreignKeys as $fk) {
                $statements[] = 'ALTER TABLE ' . $quotedTable
                    . ' ADD FOREIGN KEY (' . $this->quoteIdentifier($fk->getColumn()) . ')'
                    . ' REFERENCES ' . $this->quoteIdentifier($fk->getReferencedTable())
                    . '(' . $this->quoteIdentifier($fk->getReferencedColumn()) . ')';
            }
        }

        return $statements;
    }

    /**
     * @param string $table
     *
     * @return list<string>
     */
    public function toIndexSql(string $table): array
    {
        $statements = [];
        $quotedTable = $this->quoteIdentifier($table);

        foreach ($this->indexes as $idx) {
            if ($idx['name'] !== '') {
                $name = $this->quoteIdentifier($idx['name']);
            } else {
                // Auto-generated name uses unquoted segments joined with underscores.
                $name = $this->quoteIdentifier($table . '_' . implode('_', $idx['columns']) . '_index');
            }

            $cols = implode(', ', array_map($this->quoteIdentifier(...), $idx['columns']));
            $statements[] = 'CREATE INDEX ' . $name . ' ON ' . $quotedTable . ' (' . $cols . ')';
        }

        return $statements;
    }

    /**
     * @return bool
     */
    private function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }

    /**
     * Quote and validate a DDL identifier (table or column name).
     *
     * Throws InvalidArgumentException for names containing characters outside
     * `[a-zA-Z0-9_]` to prevent DDL injection.
     *
     * @param string $name
     *
     * @return string
     */
    private function quoteIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new InvalidArgumentException(
                "Invalid SQL identifier: '$name'. Only alphanumeric characters and underscores are allowed."
            );
        }

        return '`' . $name . '`';
    }

    /**
     * @param ColumnDefinition $col
     *
     * @return ColumnDefinition
     */
    private function addColumn(ColumnDefinition $col): ColumnDefinition
    {
        if ($this->mode === 'alter') {
            $this->addedColumns[] = $col;
        } else {
            $this->columns[] = $col;
        }

        return $col;
    }

    /**
     * Build the AFTER/FIRST position suffix for MySQL ALTER statements.
     *
     * @param ColumnDefinition $col
     *
     * @return string
     */
    private function buildPositionSuffix(ColumnDefinition $col): string
    {
        if ($this->isSqlite()) {
            return '';
        }

        if ($col->isFirstColumn()) {
            return ' FIRST';
        }

        if ($col->getAfterColumn() !== null) {
            return ' AFTER ' . $this->quoteIdentifier($col->getAfterColumn());
        }

        return '';
    }

    /**
     * @param ColumnDefinition $col
     *
     * @return string
     */
    private function compileColumn(ColumnDefinition $col): string
    {
        $def = $this->quoteIdentifier($col->name) . ' ' . $col->sqlType;

        if ($col->isPrimaryKey()) {
            return $def . ' PRIMARY KEY';
        }

        $def .= $col->isNullable() ? ' NULL' : ' NOT NULL';

        if ($col->hasDefaultValue()) {
            $def .= $this->compileDefault($col->getDefaultValue());
        }

        if ($col->isUnique()) {
            $def .= ' UNIQUE';
        }

        if ($col->getCheck() !== null) {
            $def .= ' CHECK (' . $col->getCheck() . ')';
        }

        return $def;
    }

    /**
     * @param mixed $value
     *
     * @return string
     */
    private function compileDefault(mixed $value): string
    {
        if (is_null($value)) {
            return ' DEFAULT NULL';
        }

        if (is_bool($value)) {
            return ' DEFAULT ' . ($value ? '1' : '0');
        }

        if (is_int($value) || is_float($value)) {
            return ' DEFAULT ' . $value;
        }

        if (is_string($value)) {
            return " DEFAULT '" . str_replace("'", "''", $value) . "'";
        }

        return '';
    }
}
