<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;

/**
 * Class EntityHasMany
 *
 * One-to-many relation for the Data Mapper pattern.
 * The FK lives on the related entity; the owning entity's PK is the join key.
 *
 * @package EzPhp\Orm\Relations
 */
final class EntityHasMany extends EntityRelation
{
    /**
     * @param AbstractRepository<Entity> $relatedRepo  Repository managing the related entity
     * @param string                     $foreignKey   FK column on the related entity
     * @param string                     $localKey     PK column on the owning entity
     * @param mixed                      $ownerValue   Owning entity's PK value (for lazy load)
     */
    public function __construct(
        private readonly AbstractRepository $relatedRepo,
        private readonly string $foreignKey,
        private readonly string $localKey,
        private readonly mixed $ownerValue,
    ) {
    }

    /**
     * Lazy-load all related entities for the owning entity.
     *
     * @return list<Entity>
     */
    public function getResults(): array
    {
        return $this->relatedRepo->findBy($this->foreignKey, $this->ownerValue);
    }

    /**
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->localKey;
    }

    /**
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<Entity>
     */
    public function eagerLoadFor(array $ids): array
    {
        return $this->relatedRepo->findWhereIn($this->foreignKey, $ids);
    }

    /**
     * @param list<Entity> $entities
     * @param list<Entity> $results
     * @param string       $relation
     *
     * @return void
     */
    public function match(array $entities, array $results, string $relation): void
    {
        foreach ($entities as $entity) {
            $key = $entity->getAttribute($this->localKey);
            $foreignKey = $this->foreignKey;
            $matched = array_values(
                array_filter($results, static fn (Entity $r) => $r->getAttribute($foreignKey) == $key)
            );
            $entity->setRelation($relation, $matched);
        }
    }

    /**
     * Return count of related entities per owner-key value.
     *
     * @param list<mixed> $ownerIds
     *
     * @return array<mixed, int>
     */
    public function countFor(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        return $this->relatedRepo->countBy($this->foreignKey, $ownerIds);
    }
}
