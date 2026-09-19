<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Logging\LoggerInterface;
use EzPhp\Logging\LogLevel;
use PDO;

/**
 * Class LoggingDatabase
 *
 * Decorator implementing DatabaseInterface itself, so it composes anywhere a
 * database connection is expected — including as the `$db` passed to
 * QueryBuilder / AbstractRepository. Logs SQL, bindings, and wall-clock
 * duration for every query()/execute() call before delegating to the
 * wrapped connection.
 *
 * `ez-php/logging` names this pairing directly: "Database query logging →
 * ez-php/orm module (optional query log decorator)." — this class is that
 * decorator. It requires ez-php/logging (soft dependency — require-dev
 * only; this class is only autoloaded when actually referenced).
 *
 * @package EzPhp\Orm
 */
final class LoggingDatabase implements DatabaseInterface
{
    /**
     * @param DatabaseInterface $db       The connection to log queries for.
     * @param LoggerInterface   $logger
     * @param LogLevel          $level    Level each query/execute call is logged at (default: DEBUG).
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly LoggerInterface $logger,
        private readonly LogLevel $level = LogLevel::DEBUG,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function query(string $sql, array $bindings = []): array
    {
        $start = microtime(true);
        $result = $this->db->query($sql, $bindings);
        $this->logQuery($sql, $bindings, $start);

        return $result;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $start = microtime(true);
        $result = $this->db->execute($sql, $bindings);
        $this->logQuery($sql, $bindings, $start);

        return $result;
    }

    /**
     * {@inheritDoc}
     *
     * Not logged per query — a transaction may run an arbitrary number of
     * inner query()/execute() calls, each already logged individually if the
     * caller routes them through this same LoggingDatabase instance.
     */
    public function transaction(callable $fn): mixed
    {
        return $this->db->transaction($fn);
    }

    /**
     * {@inheritDoc}
     */
    public function getPdo(): PDO
    {
        return $this->db->getPdo();
    }

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     * @param float                    $start
     *
     * @return void
     */
    private function logQuery(string $sql, array $bindings, float $start): void
    {
        $durationMs = (microtime(true) - $start) * 1000;

        $this->logger->log($this->level, $sql, [
            'bindings' => $bindings,
            'duration_ms' => round($durationMs, 2),
        ]);
    }
}
