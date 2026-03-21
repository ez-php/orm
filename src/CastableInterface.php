<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Interface CastableInterface
 *
 * Implement this on a value-object class to support custom attribute casting in
 * Model. The ORM will call castFrom() when reading from storage and castTo()
 * when writing back.
 *
 * @package EzPhp\Orm
 */
interface CastableInterface
{
    /**
     * Create an instance from a raw storage value.
     *
     * @param mixed $value
     *
     * @return static
     */
    public static function castFrom(mixed $value): static;

    /**
     * Convert the instance back to a storage-compatible scalar.
     *
     * @return mixed
     */
    public function castTo(): mixed;
}
