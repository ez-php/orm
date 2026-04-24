<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use DateTimeImmutable;

/**
 * Trait TypedAttributes
 *
 * Typed attribute getters for Entity subclasses. Satisfies PHPStan level 9
 * without requiring manual type-narrowing in every entity.
 *
 * @package EzPhp\Orm
 */
trait TypedAttributes
{
    /**
     * @param string $key
     * @param int    $default
     *
     * @return int
     */
    public function getInt(string $key, int $default = 0): int
    {
        $value = $this->getAttribute($key);

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param string $key
     * @param string $default
     *
     * @return string
     */
    public function getString(string $key, string $default = ''): string
    {
        $value = $this->getAttribute($key);

        return is_scalar($value) ? (string) $value : $default;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    public function getBool(string $key): bool
    {
        return (bool) $this->getAttribute($key);
    }

    /**
     * @param string $key
     *
     * @return int|null
     */
    public function getNullableInt(string $key): ?int
    {
        $value = $this->getAttribute($key);

        if ($value === null) {
            return null;
        }

        return is_numeric($value) ? (int) $value : null;
    }

    /**
     * @param string $key
     *
     * @return string|null
     */
    public function getNullableString(string $key): ?string
    {
        $value = $this->getAttribute($key);

        if ($value === null) {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }

    /**
     * @param string $key
     *
     * @return DateTimeImmutable|null
     */
    public function getNullableDatetime(string $key): ?DateTimeImmutable
    {
        $value = $this->getAttribute($key);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value) && !is_int($value)) {
            return null;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $value);

        return $dt !== false ? $dt : null;
    }
}
