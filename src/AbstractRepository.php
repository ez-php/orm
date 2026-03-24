<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\RepositoryInterface;
use EzPhp\Orm\Relations\EntityBelongsTo;
use EzPhp\Orm\Relations\EntityBelongsToMany;
use EzPhp\Orm\Relations\EntityHasMany;
use EzPhp\Orm\Relations\EntityHasOne;
use SplObjectStorage;

/**
 * Class AbstractRepository
 *
 * Base class for all Data Mapper repositories. Handles persistence (INSERT/UPDATE/DELETE)
 * with repository-side dirty tracking via SplObjectStorage: a snapshot of each entity's
 * attributes is stored at load time and diffed at save() to determine which columns to UPDATE.
 *
 * Why repository-side dirty tracking instead of entity-side?
 * Keeping $original inside the entity couples the entity to its own persistence history,
 * which contradicts the Data Mapper principle of entities being unaware of the DB.
 * SplObjectStorage keyed by entity identity keeps entities clean.
 *
 * Relation helper methods (hasMany, hasOne, belongsTo, belongsToMany) are protected
 * and intended to be called from public relation methods on concrete repository subclasses.
 *
 * @template T of Entity
 * @implements RepositoryInterface<T>
 *
 * @package EzPhp\Orm
 */
abstract class AbstractRepository implements RepositoryInterface
{
    protected readonly DatabaseInterface $db;

    protected readonly Hydrator $hydrator;

    /**
     * Attribute snapshots for dirty tracking.
     * Keyed by entity object identity; value is the attributes array at load time.
     *
     * @var SplObjectStorage<Entity, array<string, mixed>>
     */
    private SplObjectStorage $snapshots;

    /**
     * @param DatabaseInterface|null $db       Explicit connection; falls back to Entity::database() when null.
     * @param Hydrator|null          $hydrator Custom hydrator; defaults to a generic Hydrator instance.
     */
    public function __construct(
        ?DatabaseInterface $db = null,
        ?Hydrator $hydrator = null,
    ) {
        $this->db = $db ?? Entity::database();
        $this->hydrator = $hydrator ?? new Hydrator();

        /** @var SplObjectStorage<Entity, array<string, mixed>> $snapshots */
        $snapshots = new SplObjectStorage();
        $this->snapshots = $snapshots;
    }

    /**
     * Return the entity class name this repository manages.
     *
     * @return class-string<T>
     */
    abstract protected function entityClass(): string;

    // ─── RepositoryInterface ────────────────────────────────────────────────

    /**
     * Find an entity by its primary key (applies soft-delete filter when enabled).
     *
     * @param int|string $id
     *
     * @return T|null
     */
    public function find(int|string $id): ?object
    {
        $entityClass = $this->entityClass();
        $pk = $entityClass::scalarPrimaryKey();

        $row = $this->newQueryBuilder()->where($pk, $id)->getQueryBuilder()->first();

        if ($row === null) {
            return null;
        }

        return $this->hydrateTracked($row);
    }

    /**
     * Persist an entity.
     *
     * INSERT when the entity has no PK or no snapshot exists yet;
     * UPDATE only dirty columns otherwise.
     *
     * @param T $entity
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $entityClass = $this->entityClass();
        $table = $entityClass::resolveTable();

        if ($entityClass::isPrimaryKeyComposite()) {
            if ($this->snapshots->offsetExists($entity)) {
                $this->performUpdateComposite($entity, $table);
            } else {
                $this->performInsertComposite($entity, $table);
            }

            return;
        }

        $pk = $entityClass::scalarPrimaryKey();
        $id = $entity->getAttribute($pk);

        if ($id === null || !$this->snapshots->offsetExists($entity)) {
            $this->performInsert($entity, $table, $pk);
        } else {
            $this->performUpdate($entity, $table, $pk, $id);
        }
    }

    /**
     * Delete an entity from storage.
     *
     * Soft-delete (sets deleted_at) when $softDeletes is enabled; hard-delete otherwise.
     *
     * @param T $entity
     *
     * @return void
     */
    public function delete(object $entity): void
    {
        $entityClass = $this->entityClass();
        $table = $entityClass::resolveTable();

        if ($entityClass::isPrimaryKeyComposite()) {
            $qb = new QueryBuilder($this->db, $table);

            foreach ((array) $entityClass::getPrimaryKey() as $pkCol) {
                $id = $entity->getAttribute($pkCol);

                if ($id === null) {
                    return;
                }

                $qb = $qb->where($pkCol, $id);
            }

            $qb->delete();
            $this->snapshots->offsetUnset($entity);

            return;
        }

        $pk = $entityClass::scalarPrimaryKey();
        $id = $entity->getAttribute($pk);

        if ($id === null) {
            return;
        }

        if ($entityClass::hasSoftDeletes()) {
            $now = date('Y-m-d H:i:s');
            (new QueryBuilder($this->db, $table))
                ->where($pk, $id)
                ->update(['deleted_at' => $now]);
            $entity->setAttribute('deleted_at', $now);
            $this->trackSnapshot($entity);
        } else {
            (new QueryBuilder($this->db, $table))->where($pk, $id)->delete();
            $this->snapshots->offsetUnset($entity);
        }
    }

    // ─── Additional query methods ────────────────────────────────────────────

    /**
     * Return all entities (applies soft-delete filter when enabled).
     *
     * @return list<T>
     */
    public function findAll(): array
    {
        return $this->query()->get();
    }

    /**
     * Find entities where the given column equals the given value.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return list<T>
     */
    public function findBy(string $column, mixed $value): array
    {
        return $this->query()->where($column, $value)->get();
    }

    /**
     * Find the first entity where the given column equals the given value, or null.
     *
     * @param string $column
     * @param mixed  $value
     *
     * @return T|null
     */
    public function findOneBy(string $column, mixed $value): ?object
    {
        return $this->query()->where($column, $value)->first();
    }

    /**
     * Find entities where the given column value is in the provided list.
     *
     * Note: soft-delete filter is intentionally NOT applied here because this method
     * is used by relation eagerLoadFor() which must retrieve exactly the rows that
     * were selected (including already-trashed related entities when relevant).
     * Apply soft-delete filtering explicitly at the application layer when needed.
     *
     * @param string      $column
     * @param list<mixed> $values
     *
     * @return list<T>
     */
    public function findWhereIn(string $column, array $values): array
    {
        if ($values === []) {
            return [];
        }

        return $this->newQueryBuilder(false)->whereIn($column, $values)->get();
    }

    /**
     * Count entities grouped by a column value.
     *
     * @internal Used by EntityRelation::countFor() implementations; not part of the public repository API.
     *
     * @param string      $column
     * @param list<mixed> $ids
     *
     * @return array<mixed, int>
     */
    public function countBy(string $column, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $entityClass = $this->entityClass();
        $table = $entityClass::resolveTable();

        $rows = (new QueryBuilder($this->db, $table))
            ->select($column, 'COUNT(*) as count')
            ->whereIn($column, $ids)
            ->groupBy($column)
            ->get();

        $result = [];

        foreach ($rows as $row) {
            $keyVal = $row[$column];

            if (!is_int($keyVal) && !is_string($keyVal)) {
                continue;
            }

            $countVal = $row['count'];
            $result[$keyVal] = is_numeric($countVal) ? (int) $countVal : 0;
        }

        return $result;
    }

    /**
     * Return a new EntityQueryBuilder for this repository (with soft-delete filter).
     *
     * @return EntityQueryBuilder<T>
     */
    public function query(): EntityQueryBuilder
    {
        return $this->newQueryBuilder();
    }

    /**
     * Hydrate a single entity from a raw row without dirty tracking.
     *
     * Intended for use by relation classes that build entities outside the
     * normal query path (e.g. EntityBelongsToMany which issues raw SQL).
     *
     * @internal Called by relation classes; not part of the public repository API.
     *
     * @param array<string, mixed> $row
     *
     * @return T
     */
    public function hydrateOne(array $row): Entity
    {
        /** @var T */
        return $this->hydrator->hydrate($this->entityClass(), $row);
    }

    /**
     * Hydrate a single entity from a raw row and register it for dirty tracking.
     *
     * Called by EntityQueryBuilder::hydrate() so that all entities returned
     * through the main query path are automatically tracked.
     *
     * @internal Called by EntityQueryBuilder; not part of the public repository API.
     *
     * @param array<string, mixed> $row
     *
     * @return T
     */
    public function hydrateTracked(array $row): Entity
    {
        $entity = $this->hydrator->hydrate($this->entityClass(), $row);
        $this->trackSnapshot($entity);

        /** @var T */
        return $entity;
    }

    // ─── Relation helpers ────────────────────────────────────────────────────

    /**
     * Create a HasMany relation instance.
     *
     * Typical usage from a concrete repository method:
     *   public function posts(User $user): EntityHasMany
     *   {
     *       return $this->hasMany($this->postRepo, 'user_id', 'id', $user->getAttribute('id'));
     *   }
     *
     * @template TRelated of Entity
     *
     * @param AbstractRepository<TRelated> $relatedRepo
     * @param string                       $foreignKey   FK on the related entity
     * @param string                       $localKey     PK on the owning entity
     * @param mixed                        $ownerValue   Owning entity's PK value
     *
     * @return EntityHasMany
     */
    protected function hasMany(
        AbstractRepository $relatedRepo,
        string $foreignKey,
        string $localKey,
        mixed $ownerValue,
    ): EntityHasMany {
        return new EntityHasMany($relatedRepo, $foreignKey, $localKey, $ownerValue);
    }

    /**
     * Create a HasOne relation instance.
     *
     * @template TRelated of Entity
     *
     * @param AbstractRepository<TRelated> $relatedRepo
     * @param string                       $foreignKey   FK on the related entity
     * @param string                       $localKey     PK on the owning entity
     * @param mixed                        $ownerValue   Owning entity's PK value
     *
     * @return EntityHasOne
     */
    protected function hasOne(
        AbstractRepository $relatedRepo,
        string $foreignKey,
        string $localKey,
        mixed $ownerValue,
    ): EntityHasOne {
        return new EntityHasOne($relatedRepo, $foreignKey, $localKey, $ownerValue);
    }

    /**
     * Create a BelongsTo relation instance.
     *
     * @template TRelated of Entity
     *
     * @param AbstractRepository<TRelated> $relatedRepo
     * @param string                       $foreignKey   FK on the owning entity (e.g. 'user_id')
     * @param string                       $localKey     PK on the related entity (e.g. 'id')
     * @param mixed                        $fkValue      FK value on the owning entity
     *
     * @return EntityBelongsTo
     */
    protected function belongsTo(
        AbstractRepository $relatedRepo,
        string $foreignKey,
        string $localKey,
        mixed $fkValue,
    ): EntityBelongsTo {
        return new EntityBelongsTo($relatedRepo, $foreignKey, $localKey, $fkValue);
    }

    /**
     * Create a BelongsToMany relation instance.
     *
     * @template TRelated of Entity
     *
     * @param AbstractRepository<TRelated> $relatedRepo
     * @param string                       $relatedTable
     * @param string                       $pivotTable
     * @param string                       $foreignKey       FK in pivot pointing to owning entity
     * @param string                       $relatedKey       FK in pivot pointing to related entity
     * @param string                       $localKey         PK on owning entity
     * @param string                       $relatedLocalKey  PK on related entity
     * @param mixed                        $localValue       Owning entity's PK value
     *
     * @return EntityBelongsToMany
     */
    protected function belongsToMany(
        AbstractRepository $relatedRepo,
        string $relatedTable,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $localKey,
        string $relatedLocalKey,
        mixed $localValue,
    ): EntityBelongsToMany {
        return new EntityBelongsToMany(
            $this->db,
            $relatedRepo,
            $relatedTable,
            $pivotTable,
            $foreignKey,
            $relatedKey,
            $localKey,
            $relatedLocalKey,
            $localValue,
        );
    }

    // ─── Private helpers ─────────────────────────────────────────────────────

    /**
     * Build a new EntityQueryBuilder. Applies the soft-delete WHERE clause when
     * $applySoftDeleteFilter is true (default) and the entity has soft-deletes enabled.
     *
     * @param bool $applySoftDeleteFilter
     *
     * @return EntityQueryBuilder<T>
     */
    private function newQueryBuilder(bool $applySoftDeleteFilter = true): EntityQueryBuilder
    {
        $entityClass = $this->entityClass();
        $table = $entityClass::resolveTable();
        $qb = new QueryBuilder($this->db, $table);

        if ($applySoftDeleteFilter && $entityClass::hasSoftDeletes()) {
            $qb = $qb->whereNull('deleted_at');
        }

        return new EntityQueryBuilder($qb, $this);
    }

    /**
     * Record the current attributes of an entity as its dirty-tracking baseline.
     *
     * @param Entity $entity
     *
     * @return void
     */
    private function trackSnapshot(Entity $entity): void
    {
        $this->snapshots[$entity] = $entity->getAttributes();
    }

    /**
     * Compute the dirty attributes by comparing current state against the snapshot.
     *
     * When no snapshot exists all attributes are considered dirty (new entity).
     *
     * @param Entity $entity
     *
     * @return array<string, mixed>
     */
    private function getDirty(Entity $entity): array
    {
        if (!$this->snapshots->offsetExists($entity)) {
            return $entity->getAttributes();
        }

        $snapshot = $this->snapshots[$entity];
        $dirty = [];
        $casts = $entity::getCasts();

        foreach ($entity->getAttributes() as $key => $value) {
            $current = $this->normalizeForComparison($key, $value, $casts);
            $original = $this->normalizeForComparison($key, $snapshot[$key] ?? null, $casts);

            if (!array_key_exists($key, $snapshot) || $current !== $original) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    /**
     * Normalize a value for dirty comparison.
     *
     * CastableInterface values are reduced to their storage form; arrays are
     * JSON-encoded so that array/JSON round-trips compare as equal.
     *
     * @param string               $key
     * @param mixed                $value
     * @param array<string, string> $casts
     *
     * @return mixed
     */
    private function normalizeForComparison(string $key, mixed $value, array $casts): mixed
    {
        if ($value === null) {
            return $value;
        }

        if (array_key_exists($key, $casts)) {
            $cast = $casts[$key];

            if (is_a($cast, CastableInterface::class, true) && $value instanceof CastableInterface) {
                return $value->castTo();
            }
        }

        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    /**
     * @param Entity $entity
     * @param string $table
     * @param string $primaryKey
     *
     * @return void
     */
    private function performInsert(Entity $entity, string $table, string $primaryKey): void
    {
        $entityClass = $this->entityClass();
        $data = $this->hydrator->extract($entity);
        unset($data[$primaryKey]);

        if ($entityClass::hasTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $entity->setAttribute('created_at', $now);
            $entity->setAttribute('updated_at', $now);
        }

        if ($data === []) {
            return;
        }

        $result = (new QueryBuilder($this->db, $table))->insert($data);

        if ($result) {
            $lastId = $this->db->getPdo()->lastInsertId();

            if ($lastId !== false && $lastId !== '') {
                $entity->setAttribute($primaryKey, is_numeric($lastId) ? (int) $lastId : $lastId);
            }

            $this->trackSnapshot($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param string $table
     *
     * @return void
     */
    private function performInsertComposite(Entity $entity, string $table): void
    {
        $entityClass = $this->entityClass();
        $data = $this->hydrator->extract($entity);

        foreach ((array) $entityClass::getPrimaryKey() as $pkCol) {
            if (($data[$pkCol] ?? null) === null) {
                unset($data[$pkCol]);
            }
        }

        if ($entityClass::hasTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $entity->setAttribute('created_at', $now);
            $entity->setAttribute('updated_at', $now);
        }

        if ($data === []) {
            return;
        }

        $result = (new QueryBuilder($this->db, $table))->insert($data);

        if ($result) {
            $this->trackSnapshot($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param string $table
     * @param string $primaryKey
     * @param mixed  $id
     *
     * @return void
     */
    private function performUpdate(Entity $entity, string $table, string $primaryKey, mixed $id): void
    {
        $entityClass = $this->entityClass();
        $dirty = $this->getDirty($entity);
        unset($dirty[$primaryKey]);

        if ($entityClass::hasTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $dirty['updated_at'] = $now;
            $entity->setAttribute('updated_at', $now);
        }

        if ($dirty === []) {
            return;
        }

        // Apply inverse casts to the dirty subset only
        $allExtracted = $this->hydrator->extract($entity);
        $dirtyExtracted = array_intersect_key($allExtracted, $dirty);

        // Timestamps are set on the entity and already in $allExtracted via re-extract,
        // but we add them explicitly to handle the case where the key was not yet in attributes
        if ($entityClass::hasTimestamps() && isset($dirty['updated_at'])) {
            $dirtyExtracted['updated_at'] = $dirty['updated_at'];
        }

        $affected = (new QueryBuilder($this->db, $table))
            ->where($primaryKey, $id)
            ->update($dirtyExtracted);

        if ($affected > 0) {
            $this->trackSnapshot($entity);
        }
    }

    /**
     * @param Entity $entity
     * @param string $table
     *
     * @return void
     */
    private function performUpdateComposite(Entity $entity, string $table): void
    {
        $entityClass = $this->entityClass();
        $dirty = $this->getDirty($entity);

        foreach ((array) $entityClass::getPrimaryKey() as $pkCol) {
            unset($dirty[$pkCol]);
        }

        if ($entityClass::hasTimestamps()) {
            $now = date('Y-m-d H:i:s');
            $dirty['updated_at'] = $now;
            $entity->setAttribute('updated_at', $now);
        }

        if ($dirty === []) {
            return;
        }

        $allExtracted = $this->hydrator->extract($entity);
        $dirtyExtracted = array_intersect_key($allExtracted, $dirty);

        if ($entityClass::hasTimestamps() && isset($dirty['updated_at'])) {
            $dirtyExtracted['updated_at'] = $dirty['updated_at'];
        }

        $qb = new QueryBuilder($this->db, $table);

        foreach ((array) $entityClass::getPrimaryKey() as $pkCol) {
            $qb = $qb->where($pkCol, $entity->getAttribute($pkCol));
        }

        $affected = $qb->update($dirtyExtracted);

        if ($affected > 0) {
            $this->trackSnapshot($entity);
        }
    }
}
