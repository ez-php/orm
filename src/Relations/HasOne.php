<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;

/**
 * Class HasOne
 *
 * @package EzPhp\Orm\Relations
 */
final class HasOne extends Relation
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
     * @return Model|null
     */
    public function getResults(): ?Model
    {
        return $this->query->first();
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
            $matched = null;

            foreach ($results as $r) {
                if ($r->getAttribute($this->foreignKey) == $key) {
                    $matched = $r;
                    break;
                }
            }

            $model->setRelation($relation, $matched);
        }
    }
}
