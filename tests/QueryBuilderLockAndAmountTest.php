<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\Orm\QueryBuilder;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;

/**
 * Row locks (lockForUpdate()/sharedLock()) and bignum increment/decrement amounts.
 *
 * SQLite runs everywhere; the MySQL cases need the root stack (DB_HOST) and skip otherwise.
 *
 * @package Tests
 */
#[CoversClass(QueryBuilder::class)]
final class QueryBuilderLockAndAmountTest extends TestCase
{
    private function sqlite(): PdoDatabase
    {
        $db = new PdoDatabase('sqlite::memory:');
        $db->execute('CREATE TABLE accounts (id INTEGER PRIMARY KEY, balance NUMERIC NOT NULL, points INTEGER NOT NULL)');
        $db->execute("INSERT INTO accounts (id, balance, points) VALUES (1, '10.50', 5)");

        return $db;
    }

    private function mysql(): PdoDatabase
    {
        $host = (string) getenv('DB_HOST');

        if ($host === '') {
            $this->markTestSkipped('MySQL not available — set DB_HOST to run this test.');
        }

        $database = (string) (getenv('DB_TESTING_DATABASE') ?: 'ez-php_testing');
        $db = new PdoDatabase("mysql:host=$host;dbname=$database;charset=utf8mb4", (string) getenv('DB_USERNAME'), (string) getenv('DB_PASSWORD'));
        $db->execute('DROP TABLE IF EXISTS orm_lock_accounts');
        $db->execute('CREATE TABLE orm_lock_accounts (id INT PRIMARY KEY, balance DECIMAL(30, 2) NOT NULL, points BIGINT NOT NULL)');
        $db->execute("INSERT INTO orm_lock_accounts (id, balance, points) VALUES (1, '10.50', 5)");

        return $db;
    }

    public function test_locks_are_a_no_op_on_sqlite(): void
    {
        $query = (new QueryBuilder($this->sqlite(), 'accounts'))->where('id', 1);

        self::assertStringNotContainsString('FOR ', $query->lockForUpdate()->toSql());
        self::assertSame(5, (int) self::text($query->lockForUpdate()->first()['points'] ?? 0));
    }

    public function test_locks_are_appended_after_limit_on_mysql(): void
    {
        $db = $this->mysql();
        $query = (new QueryBuilder($db, 'orm_lock_accounts'))->where('id', 1)->limit(1);

        self::assertStringEndsWith(' LIMIT 1 FOR UPDATE', $query->lockForUpdate()->toSql());
        self::assertStringEndsWith(' LIMIT 1 FOR SHARE', $query->sharedLock()->toSql());
        self::assertStringNotContainsString('FOR ', $query->toSql());

        $row = $db->transaction(static fn (): ?array => $query->lockForUpdate()->first());
        self::assertSame(5, (int) self::text($row['points'] ?? 0));

        $db->execute('DROP TABLE orm_lock_accounts');
    }

    public function test_bignum_amounts_on_sqlite(): void
    {
        $db = $this->sqlite();
        $query = (new QueryBuilder($db, 'accounts'))->where('id', 1);

        $query->increment('balance', BigDecimal::of('0.25'));
        $query->decrement('points', BigInteger::of('2'));

        $row = $query->first();
        self::assertSame('10.75', self::text(($row['balance'] ?? '')));
        self::assertSame(3, (int) self::text($row['points'] ?? 0));
    }

    /**
     * MySQL would add a bound string as a double; the DECIMAL cast keeps it exact.
     */
    public function test_big_decimal_amounts_stay_exact_on_mysql(): void
    {
        $db = $this->mysql();
        $query = (new QueryBuilder($db, 'orm_lock_accounts'))->where('id', 1);

        $query->increment('balance', BigDecimal::of('1234567890123456789012.34'));
        $query->increment('points', BigInteger::of('9000000000000000000'));

        $row = $query->first();
        self::assertSame('1234567890123456789022.84', self::text(($row['balance'] ?? '')));
        self::assertSame('9000000000000000005', self::text(($row['points'] ?? '')));

        $db->execute('DROP TABLE orm_lock_accounts');
    }

    public function test_amounts_that_cannot_be_bound_exactly_are_refused(): void
    {
        $query = (new QueryBuilder($this->sqlite(), 'accounts'))->where('id', 1);

        foreach ([BigInteger::of('99999999999999999999'), BigDecimal::of('0.' . str_repeat('1', 31))] as $amount) {
            try {
                $query->increment('balance', $amount);
                self::fail('Expected InvalidArgumentException for ' . $amount);
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    private static function text(mixed $value): string
    {
        return is_scalar($value) || $value instanceof \Stringable ? (string) $value : '';
    }
}
