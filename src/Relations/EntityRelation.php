<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Entity;

/**
 * Class EntityRelation
 *
 * Abstract base for all Data Mapper entity relations. Relation instances are
 * created by AbstractRepository subclass methods and carry the metadata needed
 * for both lazy loading (getResults/getResult) and batch eager loading.
 *
 * @package EzPhp\Orm\Relations
 */
abstract class EntityRelation
{
    /**
     * The key to collect from owning entities for batch eager loading.
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @return string
     */
    abstract public function getOwnerKey(): string;

    /**
     * Return the FK column name.
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @return string
     */
    abstract public function getForeignKey(): string;

    /**
     * Return the local key column name on the owning side.
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @return string
     */
    abstract public function getLocalKey(): string;

    /**
     * Load all related entities for a set of owner-key values (one batch query).
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @param list<mixed> $ids
     *
     * @return list<Entity>
     */
    abstract public function eagerLoadFor(array $ids): array;

    /**
     * Match eager-loaded results back onto parent entities.
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @param list<Entity> $entities
     * @param list<Entity> $results
     * @param string       $relation
     *
     * @return void
     */
    abstract public function match(array $entities, array $results, string $relation): void;

    /**
     * Return a count of related entities keyed by owner-key value.
     *
     * @internal Called by EntityQueryBuilder during eager loading; not part of the public relation API.
     *
     * @param list<mixed> $ownerIds
     *
     * @return array<mixed, int>
     */
    abstract public function countFor(array $ownerIds): array;
}
