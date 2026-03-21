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
        $this->db->getPdo()->exec('DROP TABLE ' . $table);
    }

    /**
     * @param string $table
     *
     * @return void
     */
    public function dropIfExists(string $table): void
    {
        $this->db->getPdo()->exec('DROP TABLE IF EXISTS ' . $table);
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
     * @return bool
     */
    private function isSqlite(): bool
    {
        return $this->driver === 'sqlite';
    }
}
