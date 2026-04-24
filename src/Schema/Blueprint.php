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
     * @var list<array{columns: list<string>, name: string}>
     */
    private array $uniqueIndexes = [];

    /**
     * @var list<string>
     */
    private array $droppedIndexes = [];

    /**
     * @var list<string>
     */
    private array $droppedForeignKeys = [];

    private bool $dropPrimary = false;

    /**
     * @var list<string>
     */
    private array $droppedMorphs = [];

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
     * @return ColumnDefinition
     */
    public function uuid(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'CHAR(36)';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function ulid(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'CHAR(26)';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function tinyInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'TINYINT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function smallInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'SMALLINT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function mediumInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'MEDIUMINT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function unsignedBigInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'BIGINT UNSIGNED';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function unsignedTinyInteger(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'TINYINT UNSIGNED';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function longText(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'LONGTEXT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function mediumText(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'MEDIUMTEXT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function tinyText(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'TINYTEXT';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function binary(string $column): ColumnDefinition
    {
        return $this->addColumn(new ColumnDefinition($column, 'BLOB'));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function ipAddress(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'VARCHAR(45)';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function macAddress(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'VARCHAR(17)';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function year(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'YEAR';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function time(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'TIME';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * @param string $column
     *
     * @return ColumnDefinition
     */
    public function dateTime(string $column): ColumnDefinition
    {
        $type = $this->isSqlite() ? 'TEXT' : 'DATETIME';

        return $this->addColumn(new ColumnDefinition($column, $type));
    }

    /**
     * Add a nullable `deleted_at` timestamp column for soft deletes.
     *
     * @return void
     */
    public function softDeletes(): void
    {
        $type = $this->isSqlite() ? 'TEXT' : 'TIMESTAMP';

        $this->addColumn(new ColumnDefinition('deleted_at', $type))->nullable()->default(null);
    }

    /**
     * Add a nullable `remember_token` column for "remember me" authentication.
     *
     * @return void
     */
    public function rememberToken(): void
    {
        $type = $this->isSqlite() ? 'TEXT' : 'VARCHAR(100)';

        $this->addColumn(new ColumnDefinition('remember_token', $type))->nullable()->default(null);
    }

    /**
     * Add polymorphic `{name}_type VARCHAR(255)` + `{name}_id BIGINT UNSIGNED` columns with a composite index.
     *
     * @param string $name
     *
     * @return void
     */
    public function morphs(string $name): void
    {
        $typeColSql = $this->isSqlite() ? 'TEXT' : 'VARCHAR(255)';
        $idColSql = $this->isSqlite() ? 'INTEGER' : 'BIGINT UNSIGNED';

        $this->addColumn(new ColumnDefinition($name . '_type', $typeColSql));
        $this->addColumn(new ColumnDefinition($name . '_id', $idColSql));
        $this->index([$name . '_type', $name . '_id']);
    }

    /**
     * Add nullable polymorphic `{name}_type` + `{name}_id` columns with a composite index.
     *
     * @param string $name
     *
     * @return void
     */
    public function nullableMorphs(string $name): void
    {
        $typeColSql = $this->isSqlite() ? 'TEXT' : 'VARCHAR(255)';
        $idColSql = $this->isSqlite() ? 'INTEGER' : 'BIGINT UNSIGNED';

        $this->addColumn(new ColumnDefinition($name . '_type', $typeColSql))->nullable()->default(null);
        $this->addColumn(new ColumnDefinition($name . '_id', $idColSql))->nullable()->default(null);
        $this->index([$name . '_type', $name . '_id']);
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
     * @param list<string> $columns
     *
     * @return void
     */
    public function dropColumns(array $columns): void
    {
        foreach ($columns as $col) {
            $this->dropColumn($col);
        }
    }

    /**
     * @param string $name Index name
     *
     * @return void
     */
    public function dropIndex(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    /**
     * @param string $name Unique index name
     *
     * @return void
     */
    public function dropUnique(string $name): void
    {
        $this->droppedIndexes[] = $name;
    }

    /**
     * Drop a named foreign key constraint (MySQL only; silently skipped on SQLite).
     *
     * @param string $name Constraint name
     *
     * @return void
     */
    public function dropForeign(string $name): void
    {
        $this->droppedForeignKeys[] = $name;
    }

    /**
     * Drop the primary key (MySQL only; silently skipped on SQLite).
     *
     * @return void
     */
    public function dropPrimary(): void
    {
        $this->dropPrimary = true;
    }

    /**
     * Drop the `created_at` and `updated_at` timestamp columns.
     *
     * @return void
     */
    public function dropTimestamps(): void
    {
        $this->dropColumn('created_at');
        $this->dropColumn('updated_at');
    }

    /**
     * Drop the `deleted_at` soft-delete column.
     *
     * @return void
     */
    public function dropSoftDeletes(): void
    {
        $this->dropColumn('deleted_at');
    }

    /**
     * Drop both morph columns and their composite index.
     *
     * @param string $name
     *
     * @return void
     */
    public function dropMorphs(string $name): void
    {
        $this->dropColumn($name . '_type');
        $this->dropColumn($name . '_id');
        $this->droppedMorphs[] = $name;
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
     * Add a BIGINT UNSIGNED NOT NULL column and register a foreign key for it.
     *
     * Chain ->constrained() to auto-infer the referenced table/column, or
     * use ->references()->on() for explicit configuration.
     *
     * @param string $column
     *
     * @return ForeignKeyDefinition
     */
    public function foreignId(string $column): ForeignKeyDefinition
    {
        $type = $this->isSqlite() ? 'INTEGER' : 'BIGINT UNSIGNED';
        $this->addColumn(new ColumnDefinition($column, $type));

        return $this->foreign($column);
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
     * Add a composite UNIQUE INDEX across one or more columns.
     *
     * @param string|list<string> $columns
     * @param string              $name
     *
     * @return void
     */
    public function uniqueIndex(string|array $columns, string $name = ''): void
    {
        if (is_string($columns)) {
            $columns = [$columns];
        }

        $this->uniqueIndexes[] = ['columns' => $columns, 'name' => $name];
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
            $parts[] = $this->compileForeignKey($table, $fk);
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
                    . ' ADD ' . $this->compileForeignKey($table, $fk);
            }
        }

        foreach ($this->droppedIndexes as $name) {
            if ($this->isSqlite()) {
                $statements[] = 'DROP INDEX ' . $this->quoteIdentifier($name);
            } else {
                $statements[] = 'ALTER TABLE ' . $quotedTable . ' DROP INDEX ' . $this->quoteIdentifier($name);
            }
        }

        if (!$this->isSqlite()) {
            foreach ($this->droppedForeignKeys as $name) {
                $statements[] = 'ALTER TABLE ' . $quotedTable . ' DROP FOREIGN KEY ' . $this->quoteIdentifier($name);
            }

            if ($this->dropPrimary) {
                $statements[] = 'ALTER TABLE ' . $quotedTable . ' DROP PRIMARY KEY';
            }
        }

        foreach ($this->droppedMorphs as $morphName) {
            $indexName = $table . '_' . $morphName . '_type_' . $morphName . '_id_index';
            if ($this->isSqlite()) {
                $statements[] = 'DROP INDEX ' . $this->quoteIdentifier($indexName);
            } else {
                $statements[] = 'ALTER TABLE ' . $quotedTable . ' DROP INDEX ' . $this->quoteIdentifier($indexName);
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
                $name = $this->quoteIdentifier($table . '_' . implode('_', $idx['columns']) . '_index');
            }

            $cols = implode(', ', array_map($this->quoteIdentifier(...), $idx['columns']));
            $statements[] = 'CREATE INDEX ' . $name . ' ON ' . $quotedTable . ' (' . $cols . ')';
        }

        foreach ($this->uniqueIndexes as $idx) {
            if ($idx['name'] !== '') {
                $name = $this->quoteIdentifier($idx['name']);
            } else {
                $name = $this->quoteIdentifier($table . '_' . implode('_', $idx['columns']) . '_unique');
            }

            $cols = implode(', ', array_map($this->quoteIdentifier(...), $idx['columns']));
            $statements[] = 'CREATE UNIQUE INDEX ' . $name . ' ON ' . $quotedTable . ' (' . $cols . ')';
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
     * @param string              $table
     * @param ForeignKeyDefinition $fk
     *
     * @return string
     */
    private function compileForeignKey(string $table, ForeignKeyDefinition $fk): string
    {
        $constraintName = $this->quoteIdentifier(
            'fk_' . $table . '_' . $fk->getColumn()
        );

        $sql = 'CONSTRAINT ' . $constraintName
            . ' FOREIGN KEY (' . $this->quoteIdentifier($fk->getColumn()) . ')'
            . ' REFERENCES ' . $this->quoteIdentifier($fk->getReferencedTable())
            . '(' . $this->quoteIdentifier($fk->getReferencedColumn()) . ')';

        if ($fk->getOnDelete() !== null) {
            $sql .= ' ON DELETE ' . $fk->getOnDelete();
        }

        if ($fk->getOnUpdate() !== null) {
            $sql .= ' ON UPDATE ' . $fk->getOnUpdate();
        }

        return $sql;
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

        if ($col->hasOnUpdateCurrent()) {
            $def .= ' ON UPDATE CURRENT_TIMESTAMP';
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
        if ($value instanceof Expression) {
            return ' DEFAULT ' . $value->getValue();
        }

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
