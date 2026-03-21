<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Interface ScopeInterface
 *
 * Implement to define a reusable query constraint that is automatically applied
 * to every query for a model via Model::addGlobalScope().
 *
 * @package EzPhp\Orm
 */
interface ScopeInterface
{
    /**
     * Apply the scope to the given query builder.
     *
     * @param ModelQueryBuilder<Model> $builder
     *
     * @return ModelQueryBuilder<Model>
     */
    public function apply(ModelQueryBuilder $builder): ModelQueryBuilder;
}
