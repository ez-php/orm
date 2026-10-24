<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\RepositoryTestCase;

/**
 * Entity whose counter columns use the bignum casts.
 */
final class BigNumWalletEntity extends Entity
{
    protected static string $table = 'wallets';

    protected static array $casts = [
        'gold' => 'bigint',
        'balance' => 'decimal:2',
    ];
}

/**
 * @extends AbstractRepository<BigNumWalletEntity>
 */
final class BigNumWalletRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return BigNumWalletEntity::class;
    }
}

/**
 * incrementColumn() on 'bigint' / 'decimal' cast columns keeps the in-memory value in step.
 *
 * @package Tests\Entity
 */
#[CoversClass(AbstractRepository::class)]
final class RepositoryBigNumIncrementTest extends RepositoryTestCase
{
    private BigNumWalletRepository $wallets;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wallets = new BigNumWalletRepository($this->db, $this->hydrator);
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE wallets (id INTEGER PRIMARY KEY AUTOINCREMENT, gold INTEGER NOT NULL, balance TEXT NOT NULL)');
        $this->exec("INSERT INTO wallets (gold, balance) VALUES (100, '1.50')");
    }

    public function test_bigint_column_is_incremented_through_the_big_integer(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        $this->wallets->incrementColumn($wallet, 'gold', 25);

        $gold = $wallet->getAttribute('gold');
        self::assertInstanceOf(BigInteger::class, $gold);
        self::assertSame('125', $gold->toString());
    }

    public function test_decimal_column_is_incremented_through_the_big_decimal(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        $this->wallets->incrementColumn($wallet, 'balance', 2);

        $balance = $wallet->getAttribute('balance');
        self::assertInstanceOf(BigDecimal::class, $balance);
        self::assertSame('3.50', $balance->toString());
    }

    public function test_decimal_column_accepts_a_float_amount(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        $this->wallets->incrementColumn($wallet, 'balance', 0.25);

        $balance = $wallet->getAttribute('balance');
        self::assertInstanceOf(BigDecimal::class, $balance);
        self::assertTrue($balance->isEqualTo(BigDecimal::of('1.75')));
    }

    public function test_subsequent_save_does_not_overwrite_the_increment(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        $this->wallets->incrementColumn($wallet, 'gold', 5);
        $this->wallets->save($wallet);

        $fresh = $this->wallets->find(1);
        assert($fresh instanceof BigNumWalletEntity);
        $gold = $fresh->getAttribute('gold');
        self::assertInstanceOf(BigInteger::class, $gold);
        self::assertSame('105', $gold->toString());
    }

    public function test_fractional_amount_on_a_bigint_column_is_refused_before_the_update(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        try {
            $this->wallets->incrementColumn($wallet, 'gold', 1.5);
            self::fail('Expected an EzPhpException.');
        } catch (EzPhpException) {
        }

        $fresh = $this->wallets->find(1);
        assert($fresh instanceof BigNumWalletEntity);
        $gold = $fresh->getAttribute('gold');
        self::assertInstanceOf(BigInteger::class, $gold);
        self::assertSame('100', $gold->toString());
    }

    public function test_amount_beyond_the_decimal_scale_is_refused_before_the_update(): void
    {
        $wallet = $this->wallets->find(1);
        assert($wallet instanceof BigNumWalletEntity);

        try {
            $this->wallets->incrementColumn($wallet, 'balance', 0.125);
            self::fail('Expected an EzPhpException.');
        } catch (EzPhpException) {
        }

        $balance = $wallet->getAttribute('balance');
        self::assertInstanceOf(BigDecimal::class, $balance);
        self::assertSame('1.50', $balance->toString());

        $fresh = $this->wallets->find(1);
        assert($fresh instanceof BigNumWalletEntity);
        $stored = $fresh->getAttribute('balance');
        self::assertInstanceOf(BigDecimal::class, $stored);
        self::assertSame('1.50', $stored->toString());
    }
}
