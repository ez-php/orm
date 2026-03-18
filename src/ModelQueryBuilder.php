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
     * Eager-load the given relations when calling get().
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
}
