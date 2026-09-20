<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\DuplicateKeyException;
use EzPhp\Orm\QueryBuilder;
use PDOException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class DuplicateKeyExceptionTest
 *
 * @package Tests
 */
#[CoversClass(DuplicateKeyException::class)]
#[UsesClass(QueryBuilder::class)]
final class DuplicateKeyExceptionTest extends TestCase
{
    private PdoDatabase $db;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new PdoDatabase('sqlite::memory:');
        $this->db->getPdo()->exec('CREATE TABLE users (id INTEGER PRIMARY KEY, email TEXT NOT NULL UNIQUE, name TEXT)');
    }

    public function test_insert_throws_on_unique_violation(): void
    {
        $qb = new QueryBuilder($this->db, 'users');
        $qb->insert(['email' => 'a@example.com']);

        try {
            (new QueryBuilder($this->db, 'users'))->insert(['email' => 'a@example.com']);
            self::fail('Expected DuplicateKeyException.');
        } catch (DuplicateKeyException $e) {
            self::assertInstanceOf(PDOException::class, $e->getPrevious());
        }
    }

    public function test_insert_throws_on_primary_key_violation(): void
    {
        (new QueryBuilder($this->db, 'users'))->insert(['id' => 1, 'email' => 'a@example.com']);

        $this->expectException(DuplicateKeyException::class);

        (new QueryBuilder($this->db, 'users'))->insert(['id' => 1, 'email' => 'b@example.com']);
    }

    public function test_insert_batch_throws_on_unique_violation(): void
    {
        $this->expectException(DuplicateKeyException::class);

        (new QueryBuilder($this->db, 'users'))->insertBatch([
            ['email' => 'a@example.com'],
            ['email' => 'a@example.com'],
        ]);
    }

    public function test_other_constraint_violations_stay_pdo_exceptions(): void
    {
        $this->expectException(PDOException::class);

        (new QueryBuilder($this->db, 'users'))->insert(['name' => 'no email']);
    }

    /**
     * @param array{0: string, 1: int|null}|null $errorInfo
     */
    #[DataProvider('driverErrors')]
    public function test_from_pdo_recognises_driver_errors(?array $errorInfo, string $message, bool $expected): void
    {
        $e = new PDOException($message);
        $e->errorInfo = $errorInfo;

        self::assertSame($expected, DuplicateKeyException::fromPdo($e) !== null);
    }

    /**
     * @return array<string, array{0: array{0: string, 1: int|null}|null, 1: string, 2: bool}>
     */
    public static function driverErrors(): array
    {
        return [
            'mysql duplicate entry' => [['23000', 1062, "Duplicate entry 'x' for key 'email'"], 'SQLSTATE[23000]', true],
            'postgres unique violation' => [['23505', 7, 'duplicate key value violates unique constraint'], 'SQLSTATE[23505]', true],
            'sqlite unique' => [['23000', 19, 'UNIQUE constraint failed: users.email'], 'SQLSTATE[23000]: UNIQUE constraint failed: users.email', true],
            'mysql not null' => [['23000', 1048, "Column 'x' cannot be null"], 'SQLSTATE[23000]', false],
            'mysql foreign key' => [['23000', 1452, 'Cannot add or update a child row'], 'SQLSTATE[23000]', false],
            'sqlite not null' => [['23000', 19, 'NOT NULL constraint failed: users.email'], 'SQLSTATE[23000]: NOT NULL constraint failed', false],
            'no error info' => [null, 'boom', false],
        ];
    }
}
