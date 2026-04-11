<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use Closure;
use EzPhp\Contracts\DatabaseInterface;
use PDO;

/**
 * Class Schema
 *
 * @package EzPhp\Orm\Schema
 */
final readonly class Schema
{
    private string $driver;

    /**
     * Schema Constructor
     *
     * @param DatabaseInterface $db
     */
    public function __construct(private DatabaseInterface $db)
    {
        /** @var string $driver */
        $driver = $db->getPdo()->getAttribute(PDO::ATTR_DRIVER_NAME);
        $this->driver = $driver;
    }

    /**
     * @param string                   $table
     * @param Closure(Blueprint): void $callback
     *
     * @return void
     */
    public function create(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($this->driver);
        $callback($blueprint);
        $this->db->getPdo()->exec($blueprint->toCreateSql($table));

        foreach ($blueprint->toIndexSql($table) as $sql) {
            $this->db->getPdo()->exec($sql);
        }
    }

    /**
     * @param string                   $table
     * @param Closure(Blueprint): void $callback
     *
     * @return void
     */
    public function table(string $table, Closure $callback): void
    {
        $blueprint = new Blueprint($this->driver, 'alter');
        $callback($blueprint);

        foreach ($blueprint->toAlterSql($table) as $sql) {
            $this->db->getPdo()->exec($sql);
        }

        foreach ($blueprint->toIndexSql($table) as $sql) {
            $this->db->getPdo()->exec($sql);
        }
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function drop(string $table): void
    {
        $this->db->getPdo()->exec('DROP TABLE ' . $this->quoteIdentifier($table));
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function dropIfExists(string $table): void
    {
        $this->db->getPdo()->exec('DROP TABLE IF EXISTS ' . $this->quoteIdentifier($table));
    }

    /**
     * @param string $table
     *
     * @return bool
     */
    public function hasTable(string $table): bool
    {
        if ($this->isSqlite()) {
            $rows = $this->db->query(
                "SELECT name FROM sqlite_master WHERE type='table' AND name=?",
                [$table]
            );

            return $rows !== [];
        }

        // Use INFORMATION_SCHEMA for portable MySQL/PostgreSQL support
        $rows = $this->db->query(
            'SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?',
            [$table]
        );

        return $rows !== [];
    }

    /**
     * Check whether a column exists in the given table.
     *
     * @param string $table
     * @param string $column
     *
     * @return bool
     */
    public function hasColumn(string $table, string $column): bool
    {
        if ($this->isSqlite()) {
            $stmt = $this->db->getPdo()->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            foreach ($rows as $row) {
                if ($row['name'] === $column) {
                    return true;
                }
            }

            return false;
        }

        $rows = $this->db->query(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? AND COLUMN_NAME = ?',
            [$table, $column]
        );

        return $rows !== [];
    }

    /**
     * Return column definitions for a table.
     *
     * Each entry has keys: name (string), type (string), nullable (bool).
     *
     * @param string $table
     *
     * @return list<array{name: string, type: string, nullable: bool}>
     */
    public function getColumns(string $table): array
    {
        if ($this->isSqlite()) {
            $stmt = $this->db->getPdo()->query('PRAGMA table_info(' . $this->quoteIdentifier($table) . ')');
            $rows = $stmt !== false ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

            $result = [];
            foreach ($rows as $row) {
                $result[] = [
                    'name' => (string) $row['name'],
                    'type' => (string) $row['type'],
                    'nullable' => (int) $row['notnull'] === 0 && (string) $row['pk'] === '0',
                ];
            }

            return $result;
        }

        $rows = $this->db->query(
            'SELECT COLUMN_NAME as name, COLUMN_TYPE as type, IS_NULLABLE as nullable_str'
            . ' FROM INFORMATION_SCHEMA.COLUMNS'
            . ' WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?'
            . ' ORDER BY ORDINAL_POSITION',
            [$table]
        );

        $result = [];
        foreach ($rows as $row) {
            $name = $row['name'];
            $type = $row['type'];
            $nullableStr = $row['nullable_str'];
            $result[] = [
                'name' => is_string($name) ? $name : '',
                'type' => is_string($type) ? $type : '',
                'nullable' => strtoupper(is_string($nullableStr) ? $nullableStr : '') === 'YES',
            ];
        }

        return $result;
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
     * DDL statements cannot be parameterized, so identifiers must be
     * validated and quoted explicitly. Throws InvalidArgumentException
     * for names containing characters outside `[a-zA-Z0-9_]`.
     *
     * @param string $name
     *
     * @return string
     */
    private function quoteIdentifier(string $name): string
    {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $name)) {
            throw new \InvalidArgumentException(
                "Invalid SQL identifier: '$name'. Only alphanumeric characters and underscores are allowed."
            );
        }

        return '`' . $name . '`';
    }
}
