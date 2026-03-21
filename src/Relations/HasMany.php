<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;
use EzPhp\Orm\QueryBuilder;

/**
 * Class HasMany
 *
 * @package EzPhp\Orm\Relations
 */
final class HasMany extends Relation
{
    /**
     * @param ModelQueryBuilder<Model> $query
     * @param string                   $foreignKey  FK column on the related model
     * @param string                   $localKey    PK column on the owning model
     * @param class-string<Model>      $relatedClass
     */
    public function __construct(
        private readonly ModelQueryBuilder $query,
        private readonly string $foreignKey,
        private readonly string $localKey,
        private readonly string $relatedClass,
    ) {
    }

    /**
     * @return array<Model>
     */
    public function getResults(): array
    {
        return $this->query->get();
    }

    /**
     * @return list<Model>
     */
    public function get(): array
    {
        return $this->query->get();
    }

    /**
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->localKey;
    }

    /**
     * @return class-string<Model>
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * Return the FK column on the related model that points back to the owner.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Return the local key (PK) on the owning model.
     *
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<Model>
     */
    public function eagerLoadFor(array $ids): array
    {
        $class = $this->relatedClass;

        return $class::whereIn($this->foreignKey, $ids)->get();
    }

    /**
     * @param list<Model> $models
     * @param list<Model> $results
     * @param string      $relation
     *
     * @return void
     */
    public function match(array $models, array $results, string $relation): void
    {
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $foreignKey = $this->foreignKey;
            $matched = array_values(
                array_filter($results, static fn (Model $r) => $r->getAttribute($foreignKey) == $key)
            );
            $model->setRelation($relation, $matched);
        }
    }

    /**
     * Return count of related models per owner-key value.
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

        $relatedClass = $this->relatedClass;
        $rows = (new QueryBuilder($relatedClass::database(), $relatedClass::getTable()))
            ->select($this->foreignKey, 'COUNT(*) as count')
            ->whereIn($this->foreignKey, $ownerIds)
            ->groupBy($this->foreignKey)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $keyVal = $row[$this->foreignKey];
            if (!is_int($keyVal) && !is_string($keyVal)) {
                continue;
            }

            $countVal = $row['count'];
            $result[$keyVal] = is_numeric($countVal) ? (int) $countVal : 0;
        }

        return $result;
    }
}
