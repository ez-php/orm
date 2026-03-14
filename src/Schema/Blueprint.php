<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

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
            $parts[] = 'FOREIGN KEY (' . $fk->getColumn() . ')'
                . ' REFERENCES ' . $fk->getReferencedTable() . '(' . $fk->getReferencedColumn() . ')';
        }

        return 'CREATE TABLE ' . $table . ' (' . implode(', ', $parts) . ')';
    }

    /**
     * @param string $table
     *
     * @return list<string>
     */
    public function toAlterSql(string $table): array
    {
        $statements = [];

        foreach ($this->addedColumns as $col) {
            $statements[] = 'ALTER TABLE ' . $table . ' ADD COLUMN ' . $this->compileColumn($col);
        }

        foreach ($this->droppedColumns as $col) {
            $statements[] = 'ALTER TABLE ' . $table . ' DROP COLUMN ' . $col;
        }

        foreach ($this->renamedColumns as $rename) {
            $statements[] = 'ALTER TABLE ' . $table . ' RENAME COLUMN ' . $rename['from'] . ' TO ' . $rename['to'];
        }

        if (!$this->isSqlite()) {
            foreach ($this->foreignKeys as $fk) {
                $statements[] = 'ALTER TABLE ' . $table
                    . ' ADD FOREIGN KEY (' . $fk->getColumn() . ')'
                    . ' REFERENCES ' . $fk->getReferencedTable() . '(' . $fk->getReferencedColumn() . ')';
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

        foreach ($this->indexes as $idx) {
            $name = $idx['name'] !== ''
                ? $idx['name']
                : $table . '_' . implode('_', $idx['columns']) . '_index';

            $cols = implode(', ', $idx['columns']);
            $statements[] = 'CREATE INDEX ' . $name . ' ON ' . $table . ' (' . $cols . ')';
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
     * @param ColumnDefinition $col
     *
     * @return string
     */
    private function compileColumn(ColumnDefinition $col): string
    {
        $def = $col->name . ' ' . $col->sqlType;

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
