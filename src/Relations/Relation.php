<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Model;

/**
 * Class Relation
 *
 * @package EzPhp\Orm\Relations
 */
abstract class Relation
{
    /**
     * Execute the lazy query and return results.
     *
     * @return mixed
     */
    abstract public function getResults(): mixed;

    /**
     * The key to collect from owner/parent models for batch eager loading.
     *
     * @return string
     */
    abstract public function getOwnerKey(): string;

    /**
     * @return class-string<Model>
     */
    abstract public function getRelatedClass(): string;

    /**
     * Return the FK column name on the related table (pointing back to the owner).
     * For BelongsTo this is the FK on the owning model; for BelongsToMany it is
     * the FK in the pivot that points to the owner.
     *
     * @return string
     */
    abstract public function getForeignKey(): string;

    /**
     * Return the local key (PK) of the owning model used for correlated subqueries.
     *
     * @return string
     */
    abstract public function getLocalKey(): string;

    /**
     * Load all related models for a set of owner-key values.
     *
     * @param list<mixed> $ids
     *
     * @return list<Model>
     */
    abstract public function eagerLoadFor(array $ids): array;

    /**
     * Match eager-loaded results back onto parent models.
     *
     * @param list<Model> $models
     * @param list<Model> $results
     * @param string      $relation
     *
     * @return void
     */
    abstract public function match(array $models, array $results, string $relation): void;

    /**
     * Return a count of related models keyed by owner-key value.
     *
     * @param list<mixed> $ownerIds
     *
     * @return array<mixed, int>
     */
    abstract public function countFor(array $ownerIds): array;
}
