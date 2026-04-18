<?php

declare(strict_types=1);

namespace EzPhp\Orm\Schema;

use Closure;
use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\Schema\SchemaInterface;
use PDO;

/**
 * Class Schema
 *
 * @package EzPhp\Orm\Schema
 */
final readonly class Schema implements SchemaInterface
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

        $alterStatements = $blueprint->toAlterSql($table);

        // RENAME COLUMN requires SQLite 3.25+ (released 2018-09-15).
        // Throw a descriptive error rather than letting the PDO call fail silently.
        if ($this->isSqlite()) {
            foreach ($alterStatements as $sql) {
                if (str_contains($sql, 'RENAME COLUMN')) {
                    $this->assertSqliteMinVersion('3.25.0', 'RENAME COLUMN');
                    break;
                }
            }
        }

        foreach ($alterStatements as $sql) {
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
     * @param string $from
     * @param string $to
     *
     * @return void
     */
    public function rename(string $from, string $to): void
    {
        if ($this->isSqlite()) {
            $this->db->getPdo()->exec(
                'ALTER TABLE ' . $this->quoteIdentifier($from) . ' RENAME TO ' . $this->quoteIdentifier($to)
            );
        } else {
            $this->db->getPdo()->exec(
                'RENAME TABLE ' . $this->quoteIdentifier($from) . ' TO ' . $this->quoteIdentifier($to)
            );
        }
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
     * Dump the current database schema to a SQL file.
     *
     * The file contains CREATE TABLE and CREATE INDEX statements that can be
     * loaded instead of replaying all migrations from scratch.
     *
     * @param string $path Absolute path to the output file
     *
     * @return void
     */
    public function dump(string $path): void
    {
        $lines = ['-- Schema dump generated at ' . date('Y-m-d H:i:s'), ''];

        foreach ($this->buildDumpStatements() as $sql) {
            $lines[] = $sql . ';';
            $lines[] = '';
        }

        file_put_contents($path, implode("\n", $lines));
    }

    /**
     * @return list<string>
     */
    private function buildDumpStatements(): array
    {
        if ($this->isSqlite()) {
            return $this->buildSqliteDump();
        }

        return $this->buildMysqlDump();
    }

    /**
     * @return list<string>
     */
    private function buildSqliteDump(): array
    {
        $rows = $this->db->query(
            'SELECT type, sql FROM sqlite_master'
            . " WHERE type IN ('table', 'index')"
            . ' AND sql IS NOT NULL'
            . " AND name NOT LIKE 'sqlite_%'"
            . " ORDER BY CASE type WHEN 'table' THEN 0 ELSE 1 END, name"
        );

        $statements = [];
        foreach ($rows as $row) {
            $sql = $row['sql'];
            if (is_string($sql) && $sql !== '') {
                $statements[] = $sql;
            }
        }

        return $statements;
    }

    /**
     * @return list<string>
     */
    private function buildMysqlDump(): array
    {
        $tableRows = $this->db->query('SHOW TABLES');
        $statements = [];

        foreach ($tableRows as $row) {
            $first = array_values($row)[0];
            $table = is_string($first) ? $first : '';
            $createRows = $this->db->query('SHOW CREATE TABLE ' . $this->quoteIdentifier($table));
            $create = $createRows[0]['Create Table'] ?? null;
            if (is_string($create) && $create !== '') {
                $statements[] = $create;
            }
        }

        return $statements;
    }

    /**
     * @param string $minVersion e.g. '3.25.0'
     * @param string $feature    Human-readable feature name for the error message
     *
     * @return void
     */
    private function assertSqliteMinVersion(string $minVersion, string $feature): void
    {
        /** @var string $version */
        $version = $this->db->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION);

        if (version_compare($version, $minVersion, '<')) {
            throw new \RuntimeException(
                "SQLite $version does not support $feature (requires $minVersion+)."
            );
        }
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
