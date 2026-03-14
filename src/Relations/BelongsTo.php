<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Orm\Model;
use EzPhp\Orm\ModelQueryBuilder;

/**
 * Class BelongsTo
 *
 * @package EzPhp\Orm\Relations
 */
final class BelongsTo extends Relation
{
    /**
     * @param ModelQueryBuilder<Model> $query
     * @param string                   $foreignKey   FK column on the owning model (e.g. 'user_id' on Post)
     * @param string                   $localKey     PK column on the related model (e.g. 'id' on User)
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
     * The owning model's FK is what we collect for batch loading.
     *
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * @return class-string<Model>
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * @param list<mixed> $ids  Values of the FK column collected from owning models
     *
     * @return list<Model>
     */
    public function eagerLoadFor(array $ids): array
    {
        $class = $this->relatedClass;

        return $class::whereIn($this->localKey, $ids)->get();
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
        $keyed = [];

        foreach ($results as $r) {
            $val = $r->getAttribute($this->localKey);
            $keyed[is_scalar($val) ? (string) $val : ''] = $r;
        }

        foreach ($models as $model) {
            $fkVal = $model->getAttribute($this->foreignKey);
            $fk = is_scalar($fkVal) ? (string) $fkVal : '';
            $model->setRelation($relation, $keyed[$fk] ?? null);
        }
    }
}
