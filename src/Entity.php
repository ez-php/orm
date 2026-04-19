<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Contracts\EzPhpException;
use ReflectionClass;
use ReflectionException;

/**
 * Class Entity
 *
 * Abstract base for Data Mapper entities. Entities are pure data containers —
 * they hold attributes, apply casts on read, and carry loaded relations, but
 * have no database awareness. All persistence is delegated to AbstractRepository.
 *
 * Schema configuration is declared via static properties on each subclass,
 * mirroring the Model convention to keep migration paths straightforward.
 *
 * @package EzPhp\Orm
 */
abstract class Entity
{
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
     * Per-class database registry used by AbstractRepository when no explicit DatabaseInterface
     * is injected via constructor. Keyed by class-string (e.g. Entity::class or User::class).
     *
     * Resolution order (see database()):
     *   1. Entry for the concrete class (e.g. UserEntity::class) — per-entity override
     *   2. Entry for Entity::class — the shared default wired by EntityServiceProvider::boot()
     *   3. Neither found → throws EzPhpException
     *
     * Tests: always call resetDatabase() in tearDown to prevent leaks across test classes.
     *
     * @var array<class-string, DatabaseInterface>
     */
    private static array $databases = [];

    /**
     * Register a DatabaseInterface instance for this entity class.
     * When called as Entity::setDatabase($db) it acts as the shared default for all repositories.
     * When called as UserEntity::setDatabase($db) it registers a connection specific to UserEntity.
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
     * Remove the DatabaseInterface registration for this entity class.
     *
     * @return void
     */
    public static function resetDatabase(): void
    {
        unset(self::$databases[static::class]);
    }

    /**
     * Resolve the DatabaseInterface for this entity class.
     * Falls back to the Entity::class entry (the shared default).
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

        throw new EzPhpException("No database resolver set for [{$class}]. Call Entity::setDatabase() or inject DatabaseInterface into the repository constructor.");
    }

    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

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

    // ─── Factory ─────────────────────────────────────────────────────────────

    /**
     * Create an entity instance with all given attributes set unconditionally,
     * bypassing fillable / guarded guards — mirrors what Hydrator::hydrate() does internally.
     *
     * The constructor is intentionally skipped so that subclasses with non-standard
     * constructor signatures remain compatible. Default property values (empty arrays
     * for $attributes and $relations) are still initialised by PHP.
     *
     * Use this in tests to construct entities with known IDs or protected attributes
     * without calling setAttribute() for each column individually.
     *
     * @param array<string, mixed> $attributes
     *
     * @return static
     * @throws ReflectionException
     */
    public static function fromRaw(array $attributes): static
    {
        /** @var static $entity */
        $entity = (new ReflectionClass(static::class))->newInstanceWithoutConstructor();

        foreach ($attributes as $key => $value) {
            $entity->setAttribute($key, $value);
        }

        return $entity;
    }

    // ─── Attribute access ────────────────────────────────────────────────────

    /**
     * Mass-assign fillable attributes.
     *
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
     * Return the value for a given attribute key, applying casts on read.
     *
     * Relation values (set via setRelation) take precedence over raw attributes.
     *
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
     * Set an attribute value directly (bypasses fillable guards).
     *
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
     * Return all raw attributes.
     *
     * @return array<string, mixed>
     */
    public function getAttributes(): array
    {
        return $this->attributes;
    }

    /**
     * Return the primary key value(s) for this entity instance.
     *
     * Returns an array keyed by column name for composite PKs;
     * a scalar value for simple PKs.
     *
     * @return mixed
     */
    public function getKey(): mixed
    {
        if (static::isPrimaryKeyComposite()) {
            $result = [];
            foreach ((array) static::$primaryKey as $pkCol) {
                $result[$pkCol] = $this->attributes[$pkCol] ?? null;
            }

            return $result;
        }

        return $this->attributes[static::scalarPrimaryKey()] ?? null;
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    /**
     * Store an already-loaded relation result on this entity.
     *
     * @param string $key
     * @param mixed  $value
     *
     * @return void
     */
    public function setRelation(string $key, mixed $value): void
    {
        $this->relations[$key] = $value;
    }

    // ─── Soft deletes ────────────────────────────────────────────────────────

    /**
     * Return whether this entity has been soft-deleted.
     *
     * @return bool
     */
    public function trashed(): bool
    {
        $deletedAt = $this->attributes['deleted_at'] ?? null;

        return $deletedAt !== null && $deletedAt !== '0000-00-00 00:00:00';
    }

    // ─── Static schema helpers ───────────────────────────────────────────────

    /**
     * Return the resolved table name.
     *
     * @return string
     */
    public static function getTable(): string
    {
        return static::resolveTable();
    }

    /**
     * Return the fillable column list.
     *
     * @return list<string>
     */
    public static function getFillable(): array
    {
        return static::$fillable;
    }

    /**
     * Return the primary key column name(s).
     *
     * @return string|list<string>
     */
    public static function getPrimaryKey(): string|array
    {
        return static::$primaryKey;
    }

    /**
     * Return the casts map.
     *
     * @return array<string, string>
     */
    public static function getCasts(): array
    {
        return static::$casts;
    }

    /**
     * Return whether soft deletes are enabled.
     *
     * @return bool
     */
    public static function hasSoftDeletes(): bool
    {
        return static::$softDeletes;
    }

    /**
     * Return whether automatic timestamps are enabled.
     *
     * @return bool
     */
    public static function hasTimestamps(): bool
    {
        return static::$timestamps;
    }

    /**
     * Check whether this entity uses a composite primary key.
     *
     * @return bool
     */
    public static function isPrimaryKeyComposite(): bool
    {
        return is_array(static::$primaryKey);
    }

    /**
     * Return the primary key column as a scalar string.
     *
     * Only call after confirming the PK is not composite (isPrimaryKeyComposite() === false).
     *
     * @return string
     */
    public static function scalarPrimaryKey(): string
    {
        $pk = static::$primaryKey;

        return is_array($pk) ? $pk[0] : $pk;
    }

    /**
     * Resolve the table name from the static property or class name.
     *
     * When $table is empty the class short name is converted to snake_case + 's'.
     *
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

    // ─── Private helpers ─────────────────────────────────────────────────────

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
}
