<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\EzPhpException;
use EzPhp\Orm\Relations\BelongsTo;
use EzPhp\Orm\Relations\BelongsToMany;
use EzPhp\Orm\Relations\HasMany;
use EzPhp\Orm\Relations\HasOne;
use ReflectionClass;

/**
 * Class Model
 *
 * @phpstan-consistent-constructor
 *
 * @package EzPhp\Orm
 */
abstract class Model
{
    /**
     * Per-class database registry. Keyed by class-string (e.g. Model::class or User::class).
     *
     * Why not a plain `protected static ?DatabaseInterface $db = null`?
     * PHP static properties are defined once on the class that declares them. Writing
     * `User::$db = $conn` via late-static-binding does NOT create a per-subclass slot —
     * it writes to the single property slot on `Model`, so every subclass immediately sees
     * the same value. Keying an array on `static::class` gives true per-class isolation:
     * `User::setDatabase($conn)` only affects User, never Post or any other sibling.
     *
     * Resolution order (see database()):
     *   1. Entry for the concrete class (e.g. User::class) — per-model override
     *   2. Entry for Model::class — the shared default wired by ModelServiceProvider::boot()
     *   3. Neither found → throws EzPhpException
     *
     * Tests: always call resetDatabase() (or use ModelTestCase, which calls it in tearDown)
     * after any test that sets a custom connection to prevent leaks across test classes.
     *
     * @var array<class-string, DatabaseInterface>
     */
    private static array $databases = [];

    protected static string $table = '';

    /**
     * @var string|list<string>
     */
    protected static string|array $primaryKey = 'id';

    /**
     * @var list<string>
     */
    protected static array $fillable = [];

    /**
     * @var list<string>
     */
    protected static array $guarded = [];

    /**
     * @var array<string, string>
     */
    protected static array $casts = [];

    protected static bool $timestamps = false;

    protected static bool $softDeletes = false;

    /**
     * Per-class model event listeners.
     *
     * @var array<class-string, array<string, list<callable(static): mixed>>>
     */
    private static array $modelListeners = [];

    /**
     * Per-class global scopes.
     *
     * @var array<class-string, list<ScopeInterface>>
     */
    private static array $globalScopes = [];

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @var array<string, mixed>
     */
    private array $original = [];

    /**
     * @var array<string, mixed>
     */
    private array $relations = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(array $attributes = [])
    {
        $this->fill($attributes);
    }

    // -------------------------------------------------------------------------
    // Database setup
    // -------------------------------------------------------------------------

    /**
     * Register a DatabaseInterface instance for this model class.
     * When called as Model::setDatabase($db) it acts as the shared default for all models.
     * When called as User::setDatabase($db) it registers a connection specific to User.
     *
     * @param DatabaseInterface $db
     *
     * @return void
     */
    public static function setDatabase(DatabaseInterface $db): void
    {
        self::$databases[static::class] = $db;
    }

    /**
     * Remove the database registration for this model class.
     * Intended for use in tests to restore a clean state between test runs.
     *
     * @return void
     */
    public static function resetDatabase(): void
    {
        unset(self::$databases[static::class]);
    }

    // -------------------------------------------------------------------------
    // Hydration
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $attributes
     *
     * @return static
     */
    public static function fromRaw(array $attributes): static
    {
        $model = new static();
        $model->attributes = $attributes;
        $model->syncOriginal();

        return $model;
    }

    // -------------------------------------------------------------------------
    // Attribute access
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $attributes
     *
     * @return void
     */
    public function fill(array $attributes): void
    {
        foreach ($attributes as $key => $value) {
            if ($this->isFillable($key)) {
                $this->attributes[$key] = $value;
            }
        }
    }

    /**
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute(string $key): mixed
    {
        if (array_key_exists($key, $this->relations)) {
            return $this->relations[$key];
        }

        $value = $this->attributes[$key] ?? null;

        if (array_key_exists($key, static::$casts) && $value !== null) {
            return $this->castValue($value, static::$casts[$key]);
        }

        return $value;
    }

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * @param string $name
     *
     * @return mixed
     */
    public function __get(string $name): mixed
    {
        return $this->getAttribute($name);
    }

    /**
     * @param string $name
     * @param mixed  $value
     *
     * @return void
     */
    public function __set(string $name, mixed $value): void
    {
        $this->setAttribute($name, $value);
    }

    /**
     * @param string $name
     *
     * @return bool
     */
    public function __isset(string $name): bool
    {
        return isset($this->attributes[$name]);
    }

    /**
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return the primary key value(s) for this model instance.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        if (self::isPrimaryKeyComposite()) {
            $result = [];
            foreach ((array) static::$primaryKey as $pkCol) {
                $result[$pkCol] = $this->attributes[$pkCol] ?? null;
            }

            return $result;
        }

        return $this->attributes[static::scalarPrimaryKey()] ?? null;
    }

    // -------------------------------------------------------------------------
    // Dirty tracking
    // -------------------------------------------------------------------------

    /**
     * @param string|null $key
     *
     * @return bool
     */
    public function isDirty(?string $key = null): bool
    {
        if ($key !== null) {
            $current = $this->normalizeForComparison($key, $this->attributes[$key] ?? null);
            $original = $this->normalizeForComparison($key, $this->original[$key] ?? null);

            return $current !== $original;
        }

        return $this->getDirty() !== [];
    }

    /**
     * @param string|null $key
     *
     * @return bool
     */
    public function isClean(?string $key = null): bool
    {
        return !$this->isDirty($key);
    }

    /**
     * @return array<string, mixed>
     */
    public function getDirty(): array
    {
        $dirty = [];

        foreach ($this->attributes as $key => $value) {
            $current = $this->normalizeForComparison($key, $value);
            $original = $this->normalizeForComparison($key, $this->original[$key] ?? null);

            if (!array_key_exists($key, $this->original) || $current !== $original) {
                $dirty[$key] = $value;
            }
        }

        return $dirty;
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    /**
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function setRelation(string $key, mixed $value): void
    {
        $this->relations[$key] = $value;
    }

    // -------------------------------------------------------------------------
    // Soft Deletes
    // -------------------------------------------------------------------------

    /**
     * @return bool
     */
    public function trashed(): bool
    {
        $deletedAt = $this->attributes['deleted_at'] ?? null;

        return $deletedAt !== null && $deletedAt !== '0000-00-00 00:00:00';
    }

    /**
     * Hard-delete the model regardless of $softDeletes.
     *
     * @return bool
     */
    public function forceDelete(): bool
    {
        $db = self::database();
        $qb = new QueryBuilder($db, static::resolveTable());

        if (self::isPrimaryKeyComposite()) {
            foreach ((array) static::$primaryKey as $pkCol) {
                $id = $this->attributes[$pkCol] ?? null;

                if ($id === null) {
                    return false;
                }

                $qb = $qb->where($pkCol, $id);
            }
        } else {
            $primaryKey = static::scalarPrimaryKey();
            $id = $this->attributes[$primaryKey] ?? null;

            if ($id === null) {
                return false;
            }

            $qb = $qb->where($primaryKey, $id);
        }

        $affected = $qb->delete();

        return $affected > 0;
    }

    /**
     * Restore a soft-deleted model.
     *
     * @return bool
     */
    public function restore(): bool
    {
        $primaryKey = static::scalarPrimaryKey();
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $affected = new QueryBuilder(self::database(), static::resolveTable())
            ->where($primaryKey, $id)
            ->update(['deleted_at' => null]);

        if ($affected > 0) {
            $this->attributes['deleted_at'] = null;
            $this->syncOriginal();
        }

        return $affected > 0;
    }

    // -------------------------------------------------------------------------
    // Querying
    // -------------------------------------------------------------------------

    /**
     * @param int|string $id
     *
     * @return static|null
     */
    public static function find(int|string $id): ?static
    {
        if (self::isPrimaryKeyComposite()) {
            throw new \LogicException('find() does not support composite primary keys. Use query()->where()->first() instead.');
        }

        return static::query()->where(static::scalarPrimaryKey(), $id)->first();
    }

    /**
     * @return list<static>
     */
    public static function all(): array
    {
        return static::query()->get();
    }

    /**
     * @param string $column
     * @param mixed  $operatorOrValue
     * @param mixed  $value
     *
     * @return ModelQueryBuilder<static>
     */
    public static function where(string $column, mixed $operatorOrValue, mixed $value = null): ModelQueryBuilder
    {
        return static::query()->where($column, $operatorOrValue, $value);
    }

    /**
     * @param string      $column
     * @param list<mixed> $values
     *
     * @return ModelQueryBuilder<static>
     */
    public static function whereIn(string $column, array $values): ModelQueryBuilder
    {
        return static::query()->whereIn($column, $values);
    }

    /**
     * Eager-load the given relations.
     *
     * @param string ...$relations
     *
     * @return ModelQueryBuilder<static>
     */
    public static function with(string ...$relations): ModelQueryBuilder
    {
        return static::query()->with(...$relations);
    }

    /**
     * Query including soft-deleted records.
     *
     * @return ModelQueryBuilder<static>
     */
    public static function withTrashed(): ModelQueryBuilder
    {
        /** @var ModelQueryBuilder<static> */
        $builder = new ModelQueryBuilder(static::class, new QueryBuilder(self::database(), static::resolveTable()));

        return static::applyGlobalScopes($builder);
    }

    /**
     * Query returning only soft-deleted records.
     *
     * @return ModelQueryBuilder<static>
     */
    public static function onlyTrashed(): ModelQueryBuilder
    {
        /** @var ModelQueryBuilder<static> */
        $builder = new ModelQueryBuilder(static::class, new QueryBuilder(self::database(), static::resolveTable()));
        $builder = static::applyGlobalScopes($builder);

        return $builder->whereNotNull('deleted_at');
    }

    /**
     * Public entry point for a new base query (with soft-delete filter applied).
     *
     * @return ModelQueryBuilder<static>
     */
    public static function query(): ModelQueryBuilder
    {
        return static::newQuery();
    }

    /**
     * Return the resolved table name (public for relation use).
     *
     * @return string
     */
    public static function getTable(): string
    {
        return static::resolveTable();
    }

    /**
     * Return the fillable columns (used by pivot and other utilities).
     *
     * @return list<string>
     */
    public static function getFillable(): array
    {
        return static::$fillable;
    }

    /**
     * Return the primary key column as a scalar string.
     *
     * Only call this after confirming the PK is not composite (i.e. isPrimaryKeyComposite() === false).
     *
     * @return string
     */
    protected static function scalarPrimaryKey(): string
    {
        $pk = static::$primaryKey;

        return is_array($pk) ? $pk[0] : $pk;
    }

    // -------------------------------------------------------------------------
    // Global Scopes
    // -------------------------------------------------------------------------

    /**
     * Register a global scope for this model class.
     *
     * @param ScopeInterface $scope
     *
     * @return void
     */
    public static function addGlobalScope(ScopeInterface $scope): void
    {
        self::$globalScopes[static::class][] = $scope;
    }

    /**
     * Remove all global scopes registered for this model class.
     *
     * @return void
     */
    public static function removeGlobalScopes(): void
    {
        unset(self::$globalScopes[static::class]);
    }

    /**
     * Apply all registered global scopes to a query builder.
     *
     * @param ModelQueryBuilder<static> $builder
     *
     * @return ModelQueryBuilder<static>
     */
    protected static function applyGlobalScopes(ModelQueryBuilder $builder): ModelQueryBuilder
    {
        foreach (self::$globalScopes[static::class] ?? [] as $scope) {
            /** @var ModelQueryBuilder<Model> $genericBuilder */
            $genericBuilder = $builder;
            /** @var ModelQueryBuilder<static> $builder */
            $builder = $scope->apply($genericBuilder);
        }

        return $builder;
    }

    // -------------------------------------------------------------------------
    // Model Events
    // -------------------------------------------------------------------------

    /**
     * Register a listener for the given model event.
     *
     * Valid events: creating, created, updating, updated, deleting, deleted.
     *
     * @param string                    $event
     * @param callable(static): mixed   $listener
     *
     * @return void
     */
    public static function on(string $event, callable $listener): void
    {
        $validEvents = ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted'];

        if (!in_array($event, $validEvents, true)) {
            throw new \InvalidArgumentException("Invalid model event '$event'. Valid: " . implode(', ', $validEvents));
        }

        self::$modelListeners[static::class][$event][] = $listener;
    }

    /**
     * Remove all event listeners for this model class.
     *
     * @return void
     */
    public static function flushListeners(): void
    {
        unset(self::$modelListeners[static::class]);
    }

    // -------------------------------------------------------------------------
    // Persistence
    // -------------------------------------------------------------------------

    /**
     * @param array<string, mixed> $data
     *
     * @return static
     */
    public static function create(array $data): static
    {
        $model = new static($data);
        $model->save();

        return $model;
    }

    /**
     * @return bool
     */
    public function save(): bool
    {
        $this->beforeSave();

        $table = static::resolveTable();

        if (self::isPrimaryKeyComposite()) {
            if ($this->hasAllCompositePrimaryKeys()) {
                return $this->performUpdateComposite($table);
            }

            return $this->performInsertComposite($table);
        }

        $primaryKey = static::scalarPrimaryKey();
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return $this->performInsert($table, $primaryKey);
        }

        return $this->performUpdate($table, $primaryKey, $id);
    }

    /**
     * @return bool
     */
    public function delete(): bool
    {
        if (!$this->fireEvent('deleting')) {
            return false;
        }

        $db = self::database();

        if (self::isPrimaryKeyComposite()) {
            $qb = new QueryBuilder($db, static::resolveTable());

            foreach ((array) static::$primaryKey as $pkCol) {
                $id = $this->attributes[$pkCol] ?? null;

                if ($id === null) {
                    return false;
                }

                $qb = $qb->where($pkCol, $id);
            }

            $affected = $qb->delete();
            $this->fireEvent('deleted');

            return $affected > 0;
        }

        $primaryKey = static::scalarPrimaryKey();
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $this->beforeDelete();

        if (static::$softDeletes) {
            $now = date('Y-m-d H:i:s');
            $affected = new QueryBuilder($db, static::resolveTable())
                ->where($primaryKey, $id)
                ->update(['deleted_at' => $now]);

            if ($affected > 0) {
                $this->attributes['deleted_at'] = $now;
                $this->syncOriginal();
            }

            $this->afterDelete();
            $this->fireEvent('deleted');

            return $affected > 0;
        }

        $affected = new QueryBuilder($db, static::resolveTable())->where($primaryKey, $id)->delete();

        $this->afterDelete();
        $this->fireEvent('deleted');

        return $affected > 0;
    }

    // -------------------------------------------------------------------------
    // Hooks (override in subclasses)
    // -------------------------------------------------------------------------

    /**
     * @return void
     */
    protected function beforeSave(): void
    {
    }

    /**
     * @return void
     */
    protected function afterSave(): void
    {
    }

    /**
     * @return void
     */
    protected function beforeCreate(): void
    {
    }

    /**
     * @return void
     */
    protected function afterCreate(): void
    {
    }

    /**
     * @return void
     */
    protected function beforeUpdate(): void
    {
    }

    /**
     * @return void
     */
    protected function afterUpdate(): void
    {
    }

    /**
     * @return void
     */
    protected function beforeDelete(): void
    {
    }

    /**
     * @return void
     */
    protected function afterDelete(): void
    {
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * @param class-string<Model> $related
     * @param string              $foreignKey  FK on the related model
     * @param string              $localKey    PK on this model
     *
     * @return HasMany
     */
    protected function hasMany(string $related, string $foreignKey, string $localKey = 'id'): HasMany
    {
        $localValue = $this->getAttribute($localKey);
        $query = $related::query()->where($foreignKey, $localValue);

        return new HasMany($query, $foreignKey, $localKey, $related);
    }

    /**
     * @param class-string<Model> $related
     * @param string              $foreignKey  FK on the related model
     * @param string              $localKey    PK on this model
     *
     * @return HasOne
     */
    protected function hasOne(string $related, string $foreignKey, string $localKey = 'id'): HasOne
    {
        $localValue = $this->getAttribute($localKey);
        $query = $related::query()->where($foreignKey, $localValue);

        return new HasOne($query, $foreignKey, $localKey, $related);
    }

    /**
     * @param class-string<Model> $related
     * @param string              $foreignKey  FK on this model
     * @param string              $localKey    PK on the related model
     *
     * @return BelongsTo
     */
    protected function belongsTo(string $related, string $foreignKey, string $localKey = 'id'): BelongsTo
    {
        $fkValue = $this->getAttribute($foreignKey);
        $query = $related::query()->where($localKey, $fkValue);

        return new BelongsTo($query, $foreignKey, $localKey, $related);
    }

    /**
     * @param class-string<Model> $related
     * @param string              $pivotTable
     * @param string              $foreignKey      FK in pivot for this model
     * @param string              $relatedKey      FK in pivot for the related model
     * @param string              $localKey        PK on this model
     * @param string              $relatedLocalKey PK on the related model
     *
     * @return BelongsToMany
     */
    protected function belongsToMany(
        string $related,
        string $pivotTable,
        string $foreignKey,
        string $relatedKey,
        string $localKey = 'id',
        string $relatedLocalKey = 'id',
    ): BelongsToMany {
        $localValue = $this->getAttribute($localKey);
        $relatedTable = $related::getTable();

        return new BelongsToMany(
            self::database(),
            $relatedTable,
            $pivotTable,
            $foreignKey,
            $relatedKey,
            $localKey,
            $relatedLocalKey,
            $related,
            $localValue,
        );
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * @return string
     */
    public static function resolveTable(): string
    {
        if (static::$table !== '') {
            return static::$table;
        }

        $name = (new ReflectionClass(static::class))->getShortName();
        $snaked = strtolower((string) preg_replace('/(?<!^)[A-Z]/', '_$0', $name));

        return $snaked . 's';
    }

    /**
     * @return ModelQueryBuilder<static>
     */
    protected static function newQuery(): ModelQueryBuilder
    {
        /** @var ModelQueryBuilder<static> */
        $builder = new ModelQueryBuilder(static::class, new QueryBuilder(self::database(), static::resolveTable()));

        if (static::$softDeletes) {
            $builder = $builder->whereNull('deleted_at');
        }

        return static::applyGlobalScopes($builder);
    }

    /**
     * Resolve the Database instance for the calling model class.
     * Looks up the registry by class name, then falls back to the Model::class entry
     * (which is the "shared default" set by Model::setDatabase()).
     *
     * @return DatabaseInterface
     * @throws EzPhpException
     */
    public static function database(): DatabaseInterface
    {
        $class = static::class;

        if (isset(self::$databases[$class])) {
            return self::$databases[$class];
        }

        if (isset(self::$databases[self::class])) {
            return self::$databases[self::class];
        }

        throw new EzPhpException('No database resolver set. Register ModelServiceProvider first.');
    }

    // -------------------------------------------------------------------------
    // Private helpers
    // -------------------------------------------------------------------------

    /**
     * Check whether the model uses a composite primary key.
     *
     * @return bool
     */
    private static function isPrimaryKeyComposite(): bool
    {
        return is_array(static::$primaryKey);
    }

    /**
     * Check whether all composite PK columns are set AND the model has been persisted.
     *
     * A model is considered persisted when $original is populated (set by syncOriginal()
     * after fromRaw() or after a successful INSERT).  A freshly-constructed model always
     * has an empty $original and must use INSERT, even when all PK attributes are present.
     *
     * @return bool
     */
    private function hasAllCompositePrimaryKeys(): bool
    {
        if (!self::isPrimaryKeyComposite()) {
            return false;
        }

        if ($this->original === []) {
            return false;
        }

        foreach ((array) static::$primaryKey as $pkCol) {
            if (!isset($this->attributes[$pkCol])) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param string $table
     * @param string $primaryKey
     *
     * @return bool
     */
    private function performInsert(string $table, string $primaryKey): bool
    {
        $data = $this->attributes;
        unset($data[$primaryKey]);

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $this->attributes['created_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        if ($data === []) {
            return false;
        }

        if (!$this->fireEvent('creating')) {
            return false;
        }

        $this->beforeCreate();

        try {
            $result = new QueryBuilder(self::database(), $table)->insert($this->prepareForStorage($data));
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw new DuplicateKeyException(
                    "Duplicate key violation in table '$table'.",
                    0,
                    $e
                );
            }

            throw $e;
        }

        if ($result) {
            $lastId = self::database()->getPdo()->lastInsertId();

            if ($lastId !== false && $lastId !== '') {
                $this->attributes[$primaryKey] = is_numeric($lastId) ? (int) $lastId : $lastId;
            }

            $this->syncOriginal();
        }

        $this->afterCreate();
        $this->afterSave();
        $this->fireEvent('created');

        return $result;
    }

    /**
     * Insert with composite primary key — PK columns are kept in data.
     *
     * @param string $table
     *
     * @return bool
     */
    private function performInsertComposite(string $table): bool
    {
        $data = $this->attributes;

        // Remove individual PK columns from data if they have no value
        foreach ((array) static::$primaryKey as $pkCol) {
            if (($data[$pkCol] ?? null) === null) {
                unset($data[$pkCol]);
            }
        }

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $data['created_at'] = $now;
            $data['updated_at'] = $now;
            $this->attributes['created_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        if ($data === []) {
            return false;
        }

        if (!$this->fireEvent('creating')) {
            return false;
        }

        $this->beforeCreate();

        try {
            $result = new QueryBuilder(self::database(), $table)->insert($this->prepareForStorage($data));
        } catch (\PDOException $e) {
            if (str_starts_with((string) $e->getCode(), '23')) {
                throw new DuplicateKeyException(
                    "Duplicate key violation in table '$table'.",
                    0,
                    $e
                );
            }

            throw $e;
        }

        if ($result) {
            $this->syncOriginal();
        }

        $this->afterCreate();
        $this->afterSave();
        $this->fireEvent('created');

        return $result;
    }

    /**
     * @param string     $table
     * @param string     $primaryKey
     * @param mixed      $id
     *
     * @return bool
     */
    private function performUpdate(string $table, string $primaryKey, mixed $id): bool
    {
        $dirty = $this->getDirty();
        unset($dirty[$primaryKey]);

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $dirty['updated_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        if ($dirty === []) {
            $this->afterSave();

            return true;
        }

        if (!$this->fireEvent('updating')) {
            return false;
        }

        $this->beforeUpdate();

        $affected = new QueryBuilder(self::database(), $table)
            ->where($primaryKey, $id)
            ->update($this->prepareForStorage($dirty));

        if ($affected > 0) {
            $this->syncOriginal();
        }

        $this->afterUpdate();
        $this->afterSave();
        $this->fireEvent('updated');

        return $affected > 0;
    }

    /**
     * Update with composite primary key.
     *
     * @param string $table
     *
     * @return bool
     */
    private function performUpdateComposite(string $table): bool
    {
        $dirty = $this->getDirty();

        foreach ((array) static::$primaryKey as $pkCol) {
            unset($dirty[$pkCol]);
        }

        if (static::$timestamps) {
            $now = date('Y-m-d H:i:s');
            $dirty['updated_at'] = $now;
            $this->attributes['updated_at'] = $now;
        }

        if ($dirty === []) {
            $this->afterSave();

            return true;
        }

        if (!$this->fireEvent('updating')) {
            return false;
        }

        $this->beforeUpdate();

        $qb = new QueryBuilder(self::database(), $table);

        foreach ((array) static::$primaryKey as $pkCol) {
            $qb = $qb->where($pkCol, $this->attributes[$pkCol]);
        }

        $affected = $qb->update($this->prepareForStorage($dirty));

        if ($affected > 0) {
            $this->syncOriginal();
        }

        $this->afterUpdate();
        $this->afterSave();
        $this->fireEvent('updated');

        return $affected > 0;
    }

    /**
     * Convert PHP values to storage-compatible values (e.g. arrays → JSON).
     *
     * @param array<string, mixed> $data
     *
     * @return array<string, mixed>
     */
    private function prepareForStorage(array $data): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            if (array_key_exists($key, static::$casts) && $value !== null) {
                $cast = static::$casts[$key];

                if (is_a($cast, CastableInterface::class, true) && $value instanceof CastableInterface) {
                    $value = $value->castTo();
                } elseif (in_array($cast, ['array', 'json'], true) && is_array($value)) {
                    $value = json_encode($value);
                }
            }

            $result[$key] = $value;
        }

        return $result;
    }

    /**
     * @param mixed  $value
     * @param string $type
     *
     * @return mixed
     */
    private function castValue(mixed $value, string $type): mixed
    {
        if (is_a($type, CastableInterface::class, true)) {
            /** @var class-string<CastableInterface> $type */
            return $type::castFrom($value);
        }

        if (in_array($type, ['int', 'integer'], true)) {
            return is_scalar($value) ? intval($value) : 0;
        }

        if (in_array($type, ['float', 'double'], true)) {
            return is_scalar($value) ? floatval($value) : 0.0;
        }

        if (in_array($type, ['bool', 'boolean'], true)) {
            return (bool) $value;
        }

        if ($type === 'string') {
            return is_scalar($value) ? strval($value) : '';
        }

        if (in_array($type, ['array', 'json'], true)) {
            return is_string($value) ? json_decode($value, true) : $value;
        }

        return $value;
    }

    /**
     * @param string $key
     *
     * @return bool
     */
    private function isFillable(string $key): bool
    {
        if (static::$fillable !== []) {
            return in_array($key, static::$fillable, true);
        }

        if (static::$guarded !== []) {
            return !in_array($key, static::$guarded, true);
        }

        return true;
    }

    /**
     * @return void
     */
    private function syncOriginal(): void
    {
        $this->original = $this->attributes;
    }

    /**
     * Normalize a value for dirty comparison (e.g. array → JSON string for cast fields).
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return mixed
     */
    private function normalizeForComparison(string $key, mixed $value): mixed
    {
        if ($value === null) {
            return $value;
        }

        if (array_key_exists($key, static::$casts)) {
            $cast = static::$casts[$key];

            if (is_a($cast, CastableInterface::class, true) && $value instanceof CastableInterface) {
                return $value->castTo();
            }
        }

        // Normalize arrays to JSON regardless of cast declaration so that
        // a PHP array and its JSON-string round-trip compare as equal.
        if (is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }

    /**
     * Fire a model event, returning false if any listener cancels it.
     *
     * @param string $event
     *
     * @return bool
     */
    private function fireEvent(string $event): bool
    {
        foreach (self::$modelListeners[static::class][$event] ?? [] as $listener) {
            if ($listener($this) === false) {
                return false;
            }
        }

        return true;
    }
}
