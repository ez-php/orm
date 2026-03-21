<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Orm\Relations\Relation;

/**
 * Class ModelQueryBuilder
 *
 * @template TModel of Model
 *
 * @package EzPhp\Orm
 */
final class ModelQueryBuilder
{
    /**
     * @var list<string>
     */
    private array $eagerLoad = [];

    /**
     * @var list<string>
     */
    private array $withCounts = [];

    /**
     * @param class-string<TModel> $modelClass
     * @param QueryBuilder         $builder
     */
    public function __construct(
        private readonly string $modelClass,
        private QueryBuilder $builder,
    ) {
    }

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self<TModel>
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->where($column, $operatorOrValue, $value),
        ]);

        return $clone;
    }

    /**
     * @param string       $column
     * @param list<mixed>  $values
     *
     * @return self<TModel>
     */
    public function whereIn(string $column, array $values): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->whereIn($column, $values),
        ]);

        return $clone;
    }

    /**
     * @param string       $column
     * @param list<mixed>  $values
     *
     * @return self<TModel>
     */
    public function whereNotIn(string $column, array $values): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->whereNotIn($column, $values),
        ]);

        return $clone;
    }

    /**
     * @param string $column
     *
     * @return self<TModel>
     */
    public function whereNull(string $column): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->whereNull($column),
        ]);

        return $clone;
    }

    /**
     * @param string $column
     *
     * @return self<TModel>
     */
    public function whereNotNull(string $column): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->whereNotNull($column),
        ]);

        return $clone;
    }

    /**
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return self<TModel>
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->join($table, $first, $operator, $second),
        ]);

        return $clone;
    }

    /**
     * @param string $column
     * @param string $direction
     *
     * @return self<TModel>
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->orderBy($column, $direction),
        ]);

        return $clone;
    }

    /**
     * @param int $limit
     *
     * @return self<TModel>
     */
    public function limit(int $limit): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->limit($limit),
        ]);

        return $clone;
    }

    /**
     * @param int $offset
     *
     * @return self<TModel>
     */
    public function offset(int $offset): self
    {
        $clone = clone($this, [
            'builder' => $this->builder->offset($offset),
        ]);

        return $clone;
    }

    /**
     * Eager-load the given relations.
     *
     * @param string ...$relations
     *
     * @return self<TModel>
     */
    public function with(string ...$relations): self
    {
        $clone = clone($this, [
            'eagerLoad' => array_merge($this->eagerLoad, array_values($relations)),
        ]);

        return $clone;
    }

    /**
     * Add COUNT sub-queries for the given relation names.
     *
     * @param string ...$relations
     *
     * @return self<TModel>
     */
    public function withCount(string ...$relations): self
    {
        $clone = clone($this, [
            'withCounts' => array_merge($this->withCounts, array_values($relations)),
        ]);

        return $clone;
    }

    /**
     * Add a WHERE EXISTS correlated sub-query via a relation.
     *
     * @param string                                     $relation
     * @param callable(self<Model>): self<Model>|null    $callback
     *
     * @return self<TModel>
     */
    public function whereHas(string $relation, ?callable $callback = null): self
    {
        return $this->buildHasClause($relation, $callback, false);
    }

    /**
     * Add a WHERE NOT EXISTS correlated sub-query via a relation.
     *
     * @param string                                     $relation
     * @param callable(self<Model>): self<Model>|null    $callback
     *
     * @return self<TModel>
     */
    public function doesntHave(string $relation, ?callable $callback = null): self
    {
        return $this->buildHasClause($relation, $callback, true);
    }

    /**
     * Expose the underlying QueryBuilder (read-only use only).
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    /**
     * @return list<TModel>
     */
    public function get(): array
    {
        $models = [];

        foreach ($this->builder->get() as $row) {
            $models[] = $this->hydrate($row);
        }

        if ($this->eagerLoad !== [] && $models !== []) {
            $this->loadEagerRelations($models);
        }

        if ($this->withCounts !== [] && $models !== []) {
            $this->loadCounts($models);
        }

        return $models;
    }

    /**
     * @return TModel|null
     */
    public function first(): ?Model
    {
        $row = $this->builder->first();

        return $row !== null ? $this->hydrate($row) : null;
    }

    /**
     * @return int
     */
    public function count(): int
    {
        return $this->builder->count();
    }

    /**
     * Paginate the query results as hydrated model instances.
     *
     * @param int $perPage
     * @param int $page
     *
     * @return Paginator<TModel>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->builder->count();
        $rows = $this->builder->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $models = [];
        foreach ($rows as $row) {
            $models[] = $this->hydrate($row);
        }

        return new Paginator($models, $total, $perPage, $page);
    }

    /**
     * Chunk through the query results in pages of the given size.
     *
     * @param int                       $size
     * @param callable(list<TModel>): void $callback
     *
     * @return void
     */
    public function chunk(int $size, callable $callback): void
    {
        $page = 1;

        do {
            $rows = $this->builder->limit($size)->offset(($page - 1) * $size)->get();

            if ($rows === []) {
                break;
            }

            $models = [];
            foreach ($rows as $row) {
                $models[] = $this->hydrate($row);
            }

            $callback($models);
            $page++;
        } while (count($rows) === $size);
    }

    /**
     * @param array<string, mixed> $row
     *
     * @return TModel
     */
    private function hydrate(array $row): Model
    {
        $modelClass = $this->modelClass;

        /** @var TModel */
        return $modelClass::fromRaw($row);
    }

    /**
     * @param list<TModel> $models
     *
     * @return void
     */
    private function loadEagerRelations(array $models): void
    {
        foreach ($this->eagerLoad as $relation) {
            if (!method_exists($models[0], $relation)) {
                continue;
            }

            /** @var callable(): mixed $callable */
            $callable = [$models[0], $relation];
            /** @var mixed $rel */
            $rel = $callable();

            if (!($rel instanceof Relation)) {
                continue;
            }

            $ownerKey = $rel->getOwnerKey();
            $ids = [];

            foreach ($models as $model) {
                $val = $model->getAttribute($ownerKey);

                if ($val !== null) {
                    $ids[] = $val;
                }
            }

            $ids = array_values(array_unique($ids, SORT_REGULAR));

            if ($ids === []) {
                continue;
            }

            $results = $rel->eagerLoadFor($ids);
            $rel->match($models, $results, $relation);
        }
    }

    /**
     * Load relation counts onto each model.
     *
     * @param list<TModel> $models
     *
     * @return void
     */
    private function loadCounts(array $models): void
    {
        if ($models === []) {
            return;
        }

        $firstModel = $models[0];

        foreach ($this->withCounts as $relationName) {
            if (!method_exists($firstModel, $relationName)) {
                continue;
            }

            /** @var callable(): mixed $callable */
            $callable = [$firstModel, $relationName];
            /** @var mixed $rel */
            $rel = $callable();

            if (!($rel instanceof Relation)) {
                continue;
            }

            $ownerKey = $rel->getOwnerKey();
            $ownerIds = [];

            foreach ($models as $model) {
                $val = $model->getAttribute($ownerKey);

                if ($val !== null) {
                    $ownerIds[] = $val;
                }
            }

            $ownerIds = array_values(array_unique($ownerIds, SORT_REGULAR));
            $counts = $rel->countFor($ownerIds);

            foreach ($models as $model) {
                $id = $model->getAttribute($ownerKey);
                if (!is_int($id) && !is_string($id)) {
                    $model->setAttribute($relationName . '_count', 0);
                    continue;
                }

                $model->setAttribute($relationName . '_count', $counts[$id] ?? 0);
            }
        }
    }

    /**
     * Build a whereHas or doesntHave clause.
     *
     * @param string                                     $relation
     * @param callable(self<Model>): self<Model>|null    $callback
     * @param bool                                       $not
     *
     * @return self<TModel>
     */
    private function buildHasClause(string $relation, ?callable $callback, bool $not): self
    {
        $modelClass = $this->modelClass;
        $ghostModel = new $modelClass();

        if (!method_exists($ghostModel, $relation)) {
            return $this;
        }

        /** @var callable(): mixed $callable */
        $callable = [$ghostModel, $relation];
        /** @var mixed $relInstance */
        $relInstance = $callable();

        if (!($relInstance instanceof Relation)) {
            return $this;
        }

        $relatedClass = $relInstance->getRelatedClass();
        $foreignKey = $relInstance->getForeignKey();
        $ownerKey = $relInstance->getLocalKey();
        $ownerTable = $modelClass::resolveTable();
        $relatedTable = $relatedClass::resolveTable();

        // Build inner QB for the correlated sub-query
        $innerQb = new QueryBuilder($modelClass::database(), $relatedTable);
        $innerQb = $innerQb->select('1');

        if ($callback !== null) {
            /** @var ModelQueryBuilder<Model> $innerMqb */
            $innerMqb = new self($relatedClass, $innerQb);
            /** @var ModelQueryBuilder<Model> $innerMqb */
            $innerMqb = $callback($innerMqb);
            $innerQb = $innerMqb->getQueryBuilder();
        }

        // The correlated WHERE links related.fk = owner.pk
        $correlatedWhere = "$relatedTable.$foreignKey = $ownerTable.$ownerKey";

        $subSql = $innerQb->toSql();
        // Inject the correlated WHERE into the sub-query SQL
        if (str_contains($subSql, ' WHERE ')) {
            $subSql .= " AND $correlatedWhere";
        } else {
            $subSql .= " WHERE $correlatedWhere";
        }

        $subBindings = $innerQb->getBindings();

        if ($not) {
            $newBuilder = $this->builder->whereNotExists($subSql, $subBindings);
        } else {
            $newBuilder = $this->builder->whereExists($subSql, $subBindings);
        }

        return clone($this, ['builder' => $newBuilder]);
    }
}
