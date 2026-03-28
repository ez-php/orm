<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Orm\Relations\EntityRelation;

/**
 * Class EntityQueryBuilder
 *
 * Typed query builder for Data Mapper entities. Wraps a raw QueryBuilder and
 * hydrates results into Entity instances via the associated AbstractRepository.
 *
 * Entities loaded through get() and first() are tracked for dirty checking
 * by the repository. Entities loaded through relation eager-loading are not
 * automatically tracked (they are hydrated via hydrateOne on the related repo).
 *
 * Eager loading works analogously to ModelQueryBuilder: with('relation') queues
 * a relation name; get() calls $repository->$relation($firstEntity) to obtain
 * an EntityRelation instance, then batch-loads and matches results.
 *
 * @template T of Entity
 *
 * @package EzPhp\Orm
 */
final class EntityQueryBuilder
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
     * @param QueryBuilder  $builder
     * @param AbstractRepository<T> $repository
     */
    public function __construct(
        private QueryBuilder $builder,
        private readonly AbstractRepository $repository,
    ) {
    }

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return self<T>
     */
    public function where(string $column, mixed $operatorOrValue, mixed $value = null): self
    {
        return clone($this, [
            'builder' => $this->builder->where($column, $operatorOrValue, $value),
        ]);
    }

    /**
     * @param string      $column
     * @param list<mixed> $values
     *
     * @return self<T>
     */
    public function whereIn(string $column, array $values): self
    {
        return clone($this, [
            'builder' => $this->builder->whereIn($column, $values),
        ]);
    }

    /**
     * @param string      $column
     * @param list<mixed> $values
     *
     * @return self<T>
     */
    public function whereNotIn(string $column, array $values): self
    {
        return clone($this, [
            'builder' => $this->builder->whereNotIn($column, $values),
        ]);
    }

    /**
     * @param string $column
     *
     * @return self<T>
     */
    public function whereNull(string $column): self
    {
        return clone($this, [
            'builder' => $this->builder->whereNull($column),
        ]);
    }

    /**
     * @param string $column
     *
     * @return self<T>
     */
    public function whereNotNull(string $column): self
    {
        return clone($this, [
            'builder' => $this->builder->whereNotNull($column),
        ]);
    }

    /**
     * @param string $table
     * @param string $first
     * @param string $operator
     * @param string $second
     *
     * @return self<T>
     */
    public function join(string $table, string $first, string $operator, string $second): self
    {
        return clone($this, [
            'builder' => $this->builder->join($table, $first, $operator, $second),
        ]);
    }

    /**
     * @param string $column
     * @param string $direction
     *
     * @return self<T>
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        return clone($this, [
            'builder' => $this->builder->orderBy($column, $direction),
        ]);
    }

    /**
     * @param int $limit
     *
     * @return self<T>
     */
    public function limit(int $limit): self
    {
        return clone($this, [
            'builder' => $this->builder->limit($limit),
        ]);
    }

    /**
     * @param int $offset
     *
     * @return self<T>
     */
    public function offset(int $offset): self
    {
        return clone($this, [
            'builder' => $this->builder->offset($offset),
        ]);
    }

    /**
     * Eager-load the given relations.
     *
     * @param string ...$relations
     *
     * @return self<T>
     */
    public function with(string ...$relations): self
    {
        return clone($this, [
            'eagerLoad' => array_merge($this->eagerLoad, array_values($relations)),
        ]);
    }

    /**
     * Add COUNT sub-queries for the given relation names.
     *
     * @param string ...$relations
     *
     * @return self<T>
     */
    public function withCount(string ...$relations): self
    {
        return clone($this, [
            'withCounts' => array_merge($this->withCounts, array_values($relations)),
        ]);
    }

    /**
     * Expose the underlying QueryBuilder (read-only use).
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder(): QueryBuilder
    {
        return $this->builder;
    }

    /**
     * @return list<T>
     */
    public function get(): array
    {
        $entities = [];

        foreach ($this->builder->get() as $row) {
            $entities[] = $this->hydrate($row);
        }

        if ($this->eagerLoad !== [] && $entities !== []) {
            $this->loadEagerRelations($entities);
        }

        if ($this->withCounts !== [] && $entities !== []) {
            $this->loadCounts($entities);
        }

        return $entities;
    }

    /**
     * @return T|null
     */
    public function first(): ?Entity
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
     * Paginate the results as hydrated entity instances.
     *
     * @param int $perPage
     * @param int $page
     *
     * @return Paginator<T>
     */
    public function paginate(int $perPage = 15, int $page = 1): Paginator
    {
        $total = $this->builder->count();
        $rows = $this->builder->limit($perPage)->offset(($page - 1) * $perPage)->get();

        $entities = [];
        foreach ($rows as $row) {
            $entities[] = $this->hydrate($row);
        }

        return new Paginator($entities, $total, $perPage, $page);
    }

    /**
     * Chunk through the results in pages of the given size.
     *
     * @param int                          $size
     * @param callable(list<T>): void      $callback
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

            $entities = [];
            foreach ($rows as $row) {
                $entities[] = $this->hydrate($row);
            }

            $callback($entities);
            $page++;
        } while (count($rows) === $size);
    }

    /**
     * Hydrate a single entity row via the repository (with dirty tracking).
     *
     * @param array<string, mixed> $row
     *
     * @return T
     */
    private function hydrate(array $row): Entity
    {
        /** @var T */
        return $this->repository->hydrateTracked($row);
    }

    /**
     * @param list<T> $entities
     *
     * @return void
     */
    private function loadEagerRelations(array $entities): void
    {
        foreach ($this->eagerLoad as $relation) {
            if (!method_exists($this->repository, $relation)) {
                continue;
            }

            /** @var callable(Entity): mixed $callable */
            $callable = [$this->repository, $relation];
            /** @var mixed $rel */
            $rel = $callable($entities[0]);

            if (!($rel instanceof EntityRelation)) {
                continue;
            }

            $ownerKey = $rel->getOwnerKey();
            $ids = [];

            foreach ($entities as $entity) {
                $val = $entity->getAttribute($ownerKey);

                if ($val !== null) {
                    $ids[] = $val;
                }
            }

            $ids = array_values(array_unique($ids, SORT_REGULAR));

            if ($ids === []) {
                continue;
            }

            $results = $rel->eagerLoadFor($ids);
            $rel->match($entities, $results, $relation);
        }
    }

    /**
     * Load relation counts onto each entity.
     *
     * @param list<T> $entities
     *
     * @return void
     */
    private function loadCounts(array $entities): void
    {
        $firstEntity = $entities[0];

        foreach ($this->withCounts as $relationName) {
            if (!method_exists($this->repository, $relationName)) {
                continue;
            }

            /** @var callable(Entity): mixed $callable */
            $callable = [$this->repository, $relationName];
            /** @var mixed $rel */
            $rel = $callable($firstEntity);

            if (!($rel instanceof EntityRelation)) {
                continue;
            }

            $ownerKey = $rel->getOwnerKey();
            $ownerIds = [];

            foreach ($entities as $entity) {
                $val = $entity->getAttribute($ownerKey);

                if ($val !== null) {
                    $ownerIds[] = $val;
                }
            }

            $ownerIds = array_values(array_unique($ownerIds, SORT_REGULAR));
            $counts = $rel->countFor($ownerIds);

            foreach ($entities as $entity) {
                $id = $entity->getAttribute($ownerKey);
                if (!is_int($id) && !is_string($id)) {
                    $entity->setAttribute($relationName . '_count', 0);
                    continue;
                }

                $entity->setAttribute($relationName . '_count', $counts[$id] ?? 0);
            }
        }
    }
}
