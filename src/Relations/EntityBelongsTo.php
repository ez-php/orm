<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;

/**
 * Class EntityBelongsTo
 *
 * Inverse of HasMany/HasOne for the Data Mapper pattern.
 * The FK lives on the owning entity (e.g. 'user_id' on Post); the related
 * entity's PK is the join key (e.g. 'id' on User).
 *
 * @template TRelated of Entity
 *
 * @package EzPhp\Orm\Relations
 */
final class EntityBelongsTo extends EntityRelation
{
    /**
     * @param AbstractRepository<TRelated> $relatedRepo  Repository managing the related entity
     * @param string                       $foreignKey   FK column on the owning entity (e.g. 'user_id')
     * @param string                       $localKey     PK column on the related entity (e.g. 'id')
     * @param mixed                        $fkValue      FK value on the owning entity (for lazy load)
     */
    public function __construct(
        private readonly AbstractRepository $relatedRepo,
        private readonly string $foreignKey,
        private readonly string $localKey,
        private readonly mixed $fkValue,
    ) {
    }

    /**
     * Lazy-load the related entity.
     *
     * @return Entity|null
     */
    public function getResult(): ?Entity
    {
        if (!is_int($this->fkValue) && !is_string($this->fkValue)) {
            return null;
        }

        return $this->relatedRepo->find($this->fkValue);
    }

    /**
     * The owning entity's FK column is collected for batch eager loading.
     *
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->foreignKey;
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
     * @param list<mixed> $ids  FK values collected from owning entities
     *
     * @return list<Entity>
     */
    public function eagerLoadFor(array $ids): array
    {
        return $this->relatedRepo->findWhereIn($this->localKey, $ids);
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
        $keyed = [];

        foreach ($results as $result) {
            $val = $result->getAttribute($this->localKey);
            $keyed[is_scalar($val) ? (string) $val : ''] = $result;
        }

        foreach ($entities as $entity) {
            $fkVal = $entity->getAttribute($this->foreignKey);
            $fk = is_scalar($fkVal) ? (string) $fkVal : '';
            $entity->setRelation($relation, $keyed[$fk] ?? null);
        }
    }

    /**
     * Return count of related entities per FK value.
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

        return $this->relatedRepo->countBy($this->localKey, $ownerIds);
    }
}
