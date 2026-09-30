<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\DirtyTracker;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Entity with ez-php/bignum casts.
 */
final class OrmBigNumCastHero extends Entity
{
    protected static array $casts = [
        'gold' => 'bigint',
        'modifier' => 'decimal:2',
        'ratio' => 'decimal',
    ];
}

/**
 * 'bigint' and 'decimal[:scale]' casts backed by ez-php/bignum.
 *
 * @package Tests\Entity
 */
#[CoversClass(Entity::class)]
#[CoversClass(Hydrator::class)]
#[CoversClass(DirtyTracker::class)]
final class OrmBigNumCastTest extends TestCase
{
    public function test_bigint_reads_ints_and_numeric_strings_as_big_integer(): void
    {
        $hero = (new Hydrator())->hydrate(OrmBigNumCastHero::class, ['gold' => '92233720368547758070']);
        $small = (new Hydrator())->hydrate(OrmBigNumCastHero::class, ['gold' => 15]);

        $gold = $hero->getAttribute('gold');
        self::assertInstanceOf(BigInteger::class, $gold);
        self::assertSame('92233720368547758070', $gold->toString());
        self::assertInstanceOf(BigInteger::class, $small->getAttribute('gold'));
    }

    public function test_decimal_with_scale_reads_as_big_decimal_at_that_scale(): void
    {
        $hero = (new Hydrator())->hydrate(OrmBigNumCastHero::class, ['modifier' => '1.5', 'ratio' => '0.125']);

        $modifier = $hero->getAttribute('modifier');
        self::assertInstanceOf(BigDecimal::class, $modifier);
        self::assertSame('1.50', $modifier->toString());
        $ratio = $hero->getAttribute('ratio');
        self::assertInstanceOf(BigDecimal::class, $ratio);
        self::assertSame('0.125', $ratio->toString());
    }

    public function test_null_stays_null(): void
    {
        $hero = (new Hydrator())->hydrate(OrmBigNumCastHero::class, ['gold' => null]);

        self::assertNull($hero->getAttribute('gold'));
    }

    public function test_objects_set_on_the_entity_are_returned_as_is_and_extracted_as_strings(): void
    {
        $hero = new OrmBigNumCastHero();
        $hero->setAttribute('gold', BigInteger::of('12345678901234567890'));
        $hero->setAttribute('modifier', BigDecimal::of('2.5'));

        self::assertInstanceOf(BigInteger::class, $hero->getAttribute('gold'));
        self::assertSame(
            ['gold' => '12345678901234567890', 'modifier' => '2.50'],
            (new Hydrator())->extract($hero),
        );
    }

    public function test_extract_refuses_to_round_a_decimal_with_too_many_places(): void
    {
        $hero = new OrmBigNumCastHero();
        $hero->setAttribute('modifier', BigDecimal::of('2.555'));

        $this->expectException(EzPhpException::class);
        (new Hydrator())->extract($hero);
    }

    public function test_dirty_tracking_compares_the_storage_form(): void
    {
        $hydrator = new Hydrator();
        $tracker = new DirtyTracker();
        $hero = $hydrator->hydrate(OrmBigNumCastHero::class, ['gold' => '100', 'modifier' => '1.50']);
        $tracker->track($hero);

        $hero->setAttribute('gold', BigInteger::of(100));
        $hero->setAttribute('modifier', BigDecimal::of('1.5'));
        self::assertSame([], $tracker->dirty($hero));

        $hero->setAttribute('gold', BigInteger::of(101));
        self::assertSame(['gold'], array_keys($tracker->dirty($hero)));
    }
}
