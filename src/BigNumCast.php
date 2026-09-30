<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\BigNum\BigDecimal;
use EzPhp\BigNum\BigInteger;
use EzPhp\BigNum\RoundingMode;
use EzPhp\Contracts\EzPhpException;

/**
 * Class BigNumCast
 *
 * The `'bigint'` and `'decimal'` / `'decimal:<scale>'` attribute casts, backed by
 * ez-php/bignum (a `suggest`, needed only when an entity declares these casts).
 *
 * Read: PDO's int-or-string column value becomes a BigInteger / BigDecimal (with a
 * scale, extended to it). Write: the object becomes its string form; a decimal with
 * more places than the scale is refused instead of silently rounded.
 *
 * @internal Used by Entity, Hydrator and DirtyTracker.
 * @package EzPhp\Orm
 */
final class BigNumCast
{
    /**
     * @param string $type
     *
     * @return bool
     */
    public static function handles(string $type): bool
    {
        return $type === 'bigint' || $type === 'decimal' || str_starts_with($type, 'decimal:');
    }

    /**
     * @param mixed  $value Non-null storage value (or an object already set on the entity).
     * @param string $type
     *
     * @throws EzPhpException When ez-php/bignum is missing or the value is not a number.
     *
     * @return BigInteger|BigDecimal
     */
    public static function fromStorage(mixed $value, string $type): BigInteger|BigDecimal
    {
        self::assertInstalled($type);

        if ($type === 'bigint') {
            if ($value instanceof BigInteger) {
                return $value;
            }

            return BigInteger::of(self::scalar($value, $type));
        }

        $decimal = $value instanceof BigDecimal ? $value : BigDecimal::of(self::scalar($value, $type));
        $scale = self::scale($type);

        return $scale === null || $decimal->getScale() >= $scale ? $decimal : $decimal->toScale($scale);
    }

    /**
     * @param mixed  $value Non-null attribute value.
     * @param string $type
     *
     * @throws EzPhpException When a decimal has more places than the cast's scale.
     *
     * @return mixed The string form for bignum objects; other values unchanged.
     */
    public static function toStorage(mixed $value, string $type): mixed
    {
        if ($value instanceof BigInteger) {
            return $value->toString();
        }

        if (!$value instanceof BigDecimal) {
            return $value;
        }

        $scale = self::scale($type);

        if ($scale === null) {
            return $value->toString();
        }

        $scaled = $value->toScale($scale, RoundingMode::DOWN);

        if (!$scaled->isEqualTo($value)) {
            throw new EzPhpException("Value {$value->toString()} has more than {$scale} decimal places for a '{$type}' cast.");
        }

        return $scaled->toString();
    }

    /**
     * @param string $type
     *
     * @return int|null
     */
    private static function scale(string $type): ?int
    {
        return str_starts_with($type, 'decimal:') ? max(0, (int) substr($type, 8)) : null;
    }

    /**
     * @param mixed  $value
     * @param string $type
     *
     * @throws EzPhpException
     *
     * @return int|string
     */
    private static function scalar(mixed $value, string $type): int|string
    {
        if (is_int($value) || is_string($value)) {
            return $value;
        }

        throw new EzPhpException('Cannot cast a value of type ' . get_debug_type($value) . " to '{$type}'.");
    }

    /**
     * @param string $type
     *
     * @throws EzPhpException
     *
     * @return void
     */
    private static function assertInstalled(string $type): void
    {
        if (!class_exists(BigInteger::class)) {
            throw new EzPhpException("The '{$type}' cast requires ez-php/bignum (composer require ez-php/bignum).");
        }
    }
}
