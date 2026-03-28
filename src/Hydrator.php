<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Class Hydrator
 *
 * Converts raw database rows into Entity instances and extracts entity attributes
 * back into storage-compatible arrays with inverse cast application.
 *
 * @package EzPhp\Orm
 */
final class Hydrator
{
    /**
     * Hydrate an Entity instance from a raw database row.
     *
     * All columns from the row are set directly via setAttribute(), bypassing
     * fillable guards. Casts are applied lazily on read via Entity::getAttribute().
     *
     * @template T of Entity
     *
     * @param class-string<T>      $entityClass
     * @param array<string, mixed> $row
     *
     * @return T
     */
    public function hydrate(string $entityClass, array $row): Entity
    {
        $entity = new $entityClass();

        foreach ($row as $key => $value) {
            $entity->setAttribute($key, $value);
        }

        /** @var T */
        return $entity;
    }

    /**
     * Extract an Entity's attributes into a storage-compatible array.
     *
     * Applies inverse casts before returning:
     * - array/json cast columns are JSON-encoded
     * - CastableInterface columns are serialised via castTo()
     *
     * @param Entity $entity
     *
     * @return array<string, mixed>
     */
    public function extract(Entity $entity): array
    {
        $casts = $entity::getCasts();
        $result = [];

        foreach ($entity->getAttributes() as $key => $value) {
            if ($value !== null && array_key_exists($key, $casts)) {
                $cast = $casts[$key];

                if (is_a($cast, CastableInterface::class, true) && $value instanceof CastableInterface) {
                    $value = $value->castTo();
                } elseif (in_array($cast, ['array', 'json'], true) && is_array($value)) {
                    $value = json_encode($value);
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
