<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\DatabaseInterface;
use PDO;
use Throwable;

/**
 * Minimal DatabaseInterface implementation backed by a raw PDO connection.
 *
 * Used in ORM tests to avoid depending on EzPhp\Database\Database from
 * ez-php/framework. Provides only what the ORM and Schema layers need.
 *
 * @package Tests
 */
final class PdoDatabase implements DatabaseInterface
{
    private PDO $pdo;

    /**
     * @param string $dsn
     * @param string $username
     * @param string $password
     */
    public function __construct(string $dsn, string $username = '', string $password = '')
    {
        $this->pdo = new PDO($dsn, $username, $password);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return list<array<string, mixed>>
     */
    public function query(string $sql, array $bindings = []): array
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($bindings as $index => $value) {
            $paramIndex = is_int($index) ? $index + 1 : $index;
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($paramIndex, $value, $paramType);
        }

        $stmt->execute();

        /** @var list<array<string, mixed>> */
        return $stmt->fetchAll();
    }

    /**
     * @param string                   $sql
     * @param array<int|string, mixed> $bindings
     *
     * @return int
     */
    public function execute(string $sql, array $bindings = []): int
    {
        $stmt = $this->pdo->prepare($sql);

        foreach ($bindings as $index => $value) {
            $paramIndex = is_int($index) ? $index + 1 : $index;
            $paramType = is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR;
            $stmt->bindValue($paramIndex, $value, $paramType);
        }

        $stmt->execute();

        return $stmt->rowCount();
    }

    /**
     * @template T
     *
     * @param callable(): T $fn
     *
     * @return T
     * @throws Throwable
     */
    public function transaction(callable $fn): mixed
    {
        $this->pdo->beginTransaction();

        try {
            $result = $fn();
            $this->pdo->commit();

            return $result;
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }

    /**
     * @return PDO
     */
    public function getPdo(): PDO
    {
        return $this->pdo;
    }
}
