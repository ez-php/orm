<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Database\Database;
use EzPhp\Exceptions\EzPhpException;
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
     * Allows separate DB connections for different model classes without relying on
     * PHP's unintuitive late-static-binding static property write behaviour.
     *
     * @var array<class-string, Database>
     */
    private static array $databases = [];

    protected static string $table = '';

    protected static string $primaryKey = 'id';

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
     * Register a Database instance for this model class.
     * When called as Model::setDatabase($db) it acts as the shared default for all models.
     * When called as User::setDatabase($db) it registers a connection specific to User.
     *
     * @param Database $db
     *
     * @return void
     */
    public static function setDatabase(Database $db): void
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
        return ($this->attributes['deleted_at'] ?? null) !== null;
    }

    /**
     * Hard-delete the model regardless of $softDeletes.
     *
     * @return bool
     */
    public function forceDelete(): bool
    {
        $primaryKey = static::$primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $affected = self::database()->table(static::resolveTable())
            ->where($primaryKey, $id)
            ->delete();

        return $affected > 0;
    }

    /**
     * Restore a soft-deleted model.
     *
     * @return bool
     */
    public function restore(): bool
    {
        $primaryKey = static::$primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $affected = self::database()->table(static::resolveTable())
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
        return static::query()->where(static::$primaryKey, $id)->first();
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
        return new ModelQueryBuilder(static::class, self::database()->table(static::resolveTable()));
    }

    /**
     * Query returning only soft-deleted records.
     *
     * @return ModelQueryBuilder<static>
     */
    public static function onlyTrashed(): ModelQueryBuilder
    {
        /** @var ModelQueryBuilder<static> */
        $builder = new ModelQueryBuilder(static::class, self::database()->table(static::resolveTable()));

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

        $primaryKey = static::$primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;
        $table = static::resolveTable();

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
        $primaryKey = static::$primaryKey;
        $id = $this->attributes[$primaryKey] ?? null;

        if ($id === null) {
            return false;
        }

        $this->beforeDelete();

        if (static::$softDeletes) {
            $now = date('Y-m-d H:i:s');
            $affected = self::database()->table(static::resolveTable())
                ->where($primaryKey, $id)
                ->update(['deleted_at' => $now]);

            if ($affected > 0) {
                $this->attributes['deleted_at'] = $now;
                $this->syncOriginal();
            }

            $this->afterDelete();

            return $affected > 0;
        }

        $affected = self::database()->table(static::resolveTable())->where($primaryKey, $id)->delete();

        $this->afterDelete();

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
    protected static function resolveTable(): string
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
        $builder = new ModelQueryBuilder(static::class, self::database()->table(static::resolveTable()));

        if (static::$softDeletes) {
            return $builder->whereNull('deleted_at');
        }

        return $builder;
    }

    /**
     * Resolve the Database instance for the calling model class.
     * Looks up the registry by class name, then falls back to the Model::class entry
     * (which is the "shared default" set by Model::setDatabase()).
     *
     * @return Database
     * @throws EzPhpException
     */
    protected static function database(): Database
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

        $this->beforeCreate();

        $result = self::database()->table($table)->insert($this->prepareForStorage($data));

        if ($result) {
            $lastId = self::database()->getPdo()->lastInsertId();

            if ($lastId !== false && $lastId !== '') {
                $this->attributes[$primaryKey] = is_numeric($lastId) ? (int) $lastId : $lastId;
            }

            $this->syncOriginal();
        }

        $this->afterCreate();
        $this->afterSave();

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

        $this->beforeUpdate();

        $affected = self::database()->table($table)
            ->where($primaryKey, $id)
            ->update($this->prepareForStorage($dirty));

        if ($affected > 0) {
            $this->syncOriginal();
        }

        $this->afterUpdate();
        $this->afterSave();

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

                if (in_array($cast, ['array', 'json'], true) && is_array($value)) {
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
        if (!array_key_exists($key, static::$casts) || $value === null) {
            return $value;
        }

        $cast = static::$casts[$key];

        if (in_array($cast, ['array', 'json'], true) && is_array($value)) {
            return json_encode($value);
        }

        return $value;
    }
}
