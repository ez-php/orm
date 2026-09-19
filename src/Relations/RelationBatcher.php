<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\DataLoader\DataLoader;
use EzPhp\DataLoader\Deferred;
use EzPhp\Orm\Entity;

/**
 * Class RelationBatcher
 *
 * Opt-in batching layer for *lazy* relation access, built on `ez-php/dataloader`.
 * `with()` eager loading already issues one query per relation for a whole result
 * set; the N+1 that remains is lazy access — looping over entities and calling
 * `getResult()` / `getResults()` on each one's relation. Passing those relations
 * through a batcher instead returns a `Deferred` per relation; the first `get()`
 * resolves every queued key of the same relation with a single `findWhereIn` query.
 *
 *   $batcher = new RelationBatcher();
 *   $pending = array_map(fn ($post) => $batcher->belongsTo($posts->user($post)), $list);
 *   $authors = array_map(fn ($d) => $d->get(), $pending); // one users query
 *
 * Loaders are keyed by relation kind, related repository and join keys, and memoize
 * per owner key, so repeated keys never re-query. Create one batcher per request/unit
 * of work and discard it — a long-lived instance would serve stale cached rows.
 * Existing relation classes and `with()` are unchanged.
 *
 * @package EzPhp\Orm\Relations
 */
final class RelationBatcher
{
    /** @var array<string, DataLoader> */
    private array $loaders = [];

    /**
     * Queue a belongs-to relation; resolves to the related entity or null.
     *
     * @template T of Entity
     *
     * @param EntityBelongsTo<T> $relation
     *
     * @return Deferred
     */
    public function belongsTo(EntityBelongsTo $relation): Deferred
    {
        $key = $relation->getLazyKey();

        if (!is_int($key) && !is_string($key)) {
            return Deferred::resolved(null);
        }

        $localKey = $relation->getLocalKey();

        return $this->loader('belongsTo', $relation, static function (array $keys) use ($relation, $localKey): array {
            $byKey = [];

            foreach ($relation->eagerLoadFor($keys) as $entity) {
                $value = $entity->getAttribute($localKey);

                if (is_int($value) || is_string($value)) {
                    $byKey[$value] = $entity;
                }
            }

            return self::fill($keys, $byKey, null);
        })->load($key);
    }

    /**
     * Queue a has-one relation; resolves to the related entity or null.
     *
     * @template T of Entity
     *
     * @param EntityHasOne<T> $relation
     *
     * @return Deferred
     */
    public function hasOne(EntityHasOne $relation): Deferred
    {
        $key = $relation->getLazyKey();

        if (!is_int($key) && !is_string($key)) {
            return Deferred::resolved(null);
        }

        $foreignKey = $relation->getForeignKey();

        return $this->loader('hasOne', $relation, static function (array $keys) use ($relation, $foreignKey): array {
            $byKey = [];

            foreach ($relation->eagerLoadFor($keys) as $entity) {
                $value = $entity->getAttribute($foreignKey);

                if ((is_int($value) || is_string($value)) && !isset($byKey[$value])) {
                    $byKey[$value] = $entity;
                }
            }

            return self::fill($keys, $byKey, null);
        })->load($key);
    }

    /**
     * Queue a has-many relation; resolves to a list of related entities (possibly empty).
     *
     * @template T of Entity
     *
     * @param EntityHasMany<T> $relation
     *
     * @return Deferred
     */
    public function hasMany(EntityHasMany $relation): Deferred
    {
        $key = $relation->getLazyKey();

        if (!is_int($key) && !is_string($key)) {
            return Deferred::resolved([]);
        }

        $foreignKey = $relation->getForeignKey();

        return $this->loader('hasMany', $relation, static function (array $keys) use ($relation, $foreignKey): array {
            $groups = [];

            foreach ($relation->eagerLoadFor($keys) as $entity) {
                $value = $entity->getAttribute($foreignKey);

                if (is_int($value) || is_string($value)) {
                    $groups[$value][] = $entity;
                }
            }

            return self::fill($keys, $groups, []);
        })->load($key);
    }

    /**
     * @param string                                               $kind
     * @param EntityRelation                                       $relation
     * @param callable(list<int|string>): array<int|string, mixed> $batchFn
     *
     * @return DataLoader
     */
    private function loader(string $kind, EntityRelation $relation, callable $batchFn): DataLoader
    {
        $repository = match (true) {
            $relation instanceof EntityBelongsTo, $relation instanceof EntityHasOne, $relation instanceof EntityHasMany => $relation->getRelatedRepository(),
            default => throw new \InvalidArgumentException('Unsupported relation type: ' . $relation::class),
        };
        $id = $kind
            . ':' . spl_object_id($repository)
            . ':' . $relation->getForeignKey()
            . ':' . $relation->getLocalKey();

        return $this->loaders[$id] ??= new DataLoader($batchFn);
    }

    /**
     * Every requested key must be present in a DataLoader batch result.
     *
     * @param list<int|string>          $keys
     * @param array<int|string, mixed>  $found
     * @param mixed                     $default
     *
     * @return array<int|string, mixed>
     */
    private static function fill(array $keys, array $found, mixed $default): array
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $found[$key] ?? $default;
        }

        return $result;
    }
}
