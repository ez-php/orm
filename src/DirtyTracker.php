<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use SplObjectStorage;

/**
 * Class DirtyTracker
 *
 * Repository-side dirty tracking: remembers each entity's attributes at load/save
 * time (keyed by object identity, so the entity itself carries no tracking state)
 * and reports which attributes changed since.
 *
 * @package EzPhp\Orm
 */
final class DirtyTracker
{
    /**
     * Attribute snapshots, keyed by entity object identity; the value is the
     * attributes array at the time the entity was last tracked.
     *
     * @var SplObjectStorage<Entity, array<string, mixed>>
     */
    private SplObjectStorage $snapshots;

    /**
     * DirtyTracker Constructor
     */
    public function __construct()
    {
        /** @var SplObjectStorage<Entity, array<string, mixed>> $snapshots */
        $snapshots = new SplObjectStorage();
        $this->snapshots = $snapshots;
    }

    /**
     * Whether a baseline snapshot exists for the entity.
     *
     * @param Entity $entity
     *
     * @return bool
     */
    public function isTracked(Entity $entity): bool
    {
        return $this->snapshots->offsetExists($entity);
    }

    /**
     * Drop the entity's baseline (it is no longer persisted, or is about to be re-tracked).
     *
     * @param Entity $entity
     *
     * @return void
     */
    public function forget(Entity $entity): void
    {
        $this->snapshots->offsetUnset($entity);
    }

    /**
     * Record the current attributes of an entity as its dirty-tracking baseline.
     *
     * @param Entity $entity
     *
     * @return void
     */
    public function track(Entity $entity): void
    {
        $this->snapshots[$entity] = $entity->getAttributes();
    }

    /**
     * Compute the dirty attributes by comparing current state against the snapshot.
     *
     * When no snapshot exists all attributes are considered dirty (new entity).
     *
     * @param Entity $entity
     *
     * @return array<string, mixed>
     */
    public function dirty(Entity $entity): array
    {
        if (!$this->isTracked($entity)) {
            return $entity->getAttributes();
        }

        $snapshot = $this->snapshots[$entity];
        $dirty = [];
        $casts = $entity::getCasts();

        foreach ($entity->getAttributes() as $key => $value) {
            $current = $this->normalizeForComparison($key, $value, $casts);
            $original = $this->normalizeForComparison($key, $snapshot[$key] ?? null, $casts);

            if (!array_key_exists($key, $snapshot) || $current !== $original) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Normalize a value for dirty comparison.
     *
     * CastableInterface values are reduced to their storage form; arrays are
     * JSON-encoded so that array/JSON round-trips compare as equal.
     *
     * @param string               $key
     * @param mixed                $value
     * @param array<string, string> $casts
     *
     * @return mixed
     */
    private function normalizeForComparison(string $key, mixed $value, array $casts): mixed
    {
        if ($value === null) {
            return $value;
        }

        if (array_key_exists($key, $casts)) {
            $cast = $casts[$key];

            if (is_a($cast, CastableInterface::class, true) && $value instanceof CastableInterface) {
                return $value->castTo();
            }
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
