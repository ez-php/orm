# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.0"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/orm

Data Mapper ORM, fluent Query Builder, and Schema Builder.

---

## Source Structure

```
src/
├── Entity.php                        — Abstract Data Mapper entity base; attributes, casts, fillable guards, relation storage
├── AbstractRepository.php            — Abstract repository base; persistence (INSERT/UPDATE/DELETE), dirty tracking via SplObjectStorage, relations, eager-load
├── EntityQueryBuilder.php            — Typed query builder for entities; wraps QueryBuilder; eager-load with(), withCount()
├── EntityServiceProvider.php         — Calls Entity::setDatabase($db) in boot()
├── Hydrator.php                      — Converts raw DB rows → Entity instances and Entity attributes → storage arrays
├── CastableInterface.php             — Interface for custom value-object casts: castFrom(mixed)/castTo(): mixed
├── DuplicateKeyException.php         — Thrown by save() on duplicate-key violations (detects via PDOException code)
├── Paginator.php                     — Immutable value object wrapping a page of results with total/lastPage/hasMorePages/firstItem/lastItem/isFirstPage/isLastPage/from/to
├── QueryBuilder.php                  — Fluent SQL builder for raw row queries; all WHERE/JOIN/ORDER/LIMIT/aggregates/paginate/chunk/cache
├── Console/
│   ├── MakeEntityCommand.php         — Scaffolds an Entity subclass in src/Entities/
│   └── MakeRepositoryCommand.php     — Scaffolds an AbstractRepository subclass in src/Repositories/
├── Relations/
│   ├── EntityRelation.php            — Abstract base; contracts for getResults/getResult/eagerLoadFor/match/getOwnerKey/getForeignKey/getLocalKey
│   ├── EntityHasMany.php             — One-to-many: FK on related entity
│   ├── EntityHasOne.php              — One-to-one: FK on related entity
│   ├── EntityBelongsTo.php           — Inverse of HasMany/HasOne: FK on owning entity
│   └── EntityBelongsToMany.php       — Many-to-many via pivot table; raw SQL join
└── Schema/
    ├── Schema.php                    — DDL façade: create/table/drop/dropIfExists/hasTable/hasColumn/rename/dump; SQLite version guard for RENAME COLUMN
    ├── Blueprint.php                 — Column and constraint definitions; generates CREATE/ALTER/INDEX/DROP SQL; driver-aware type mapping
    ├── ColumnDefinition.php          — Fluent column spec: nullable/default/unique/useCurrent/useCurrentOnUpdate/after/first/change
    ├── Expression.php                — Raw SQL fragment for DEFAULT clauses (e.g. CURRENT_TIMESTAMP); emitted verbatim, not quoted
    ├── ForeignKeyDefinition.php      — FK constraint: references/on/onDelete/onUpdate/constrained; validated cascade actions
    └── SchemaServiceProvider.php     — Binds Schema lazily to the container

tests/
├── TestCase.php                      — Base PHPUnit test case
├── DatabaseTestCase.php              — In-memory SQLite DB; fresh per-test method; override setUpDatabase()
├── RepositoryTestCase.php            — Extends DatabaseTestCase; wires Entity::setDatabase(); resets in tearDown
├── QueryBuilderTest.php              — Covers all QB clauses and execution methods
├── QueryBuilderCacheTest.php         — Covers QB cache() integration with CacheInterface
├── PaginatorTest.php                 — Unit tests for Paginator value object (all accessors, edge cases)
├── PaginationTest.php                — Integration tests for QB/EQB paginate() and chunk()
├── Entity/EntityTest.php             — Covers Entity CRUD, dirty tracking, casts, soft deletes, relations
├── Entity/EntityQueryBuilderTest.php — Covers EQB clauses, get/first/count/paginate/with/withCount
├── Entity/HydratorTest.php           — Unit tests for Hydrator hydrate() and dehydrate()
├── Entity/RepositoryTest.php         — Covers AbstractRepository find/save/delete/query/relations/eager-load
├── Entity/EntityServiceProviderTest.php — Covers provider boot wiring
├── ORM/EntityServiceProviderTest.php — Application-level provider boot test
├── Schema/BlueprintTest.php          — Covers Blueprint SQL generation for all column types and modes
├── Schema/SchemaTest.php             — Covers Schema DDL methods against real SQLite
├── Schema/SchemaServiceProviderTest.php
└── Console/MakeEntityCommand + MakeRepositoryCommandTest
```

---

## Architecture: Data Mapper Pattern

The ORM uses the **Data Mapper** pattern. Responsibilities are separated across three layers:

| Layer | Class | Role |
|---|---|---|
| Entity | `Entity` | Pure data container — attributes, casts, relation slots. No DB awareness. |
| Repository | `AbstractRepository<T>` | Persistence logic — INSERT, UPDATE, DELETE, dirty tracking, relations |
| Query | `EntityQueryBuilder<T>` | Fluent query building with typed hydration |

**Why Data Mapper instead of Active Record:**
Active Record merges persistence into the domain object (`$user->save()`), which couples business logic to the database. Data Mapper keeps entities as plain objects that carry no persistence state — they do not know whether they are new or loaded, saved or dirty. The repository holds that knowledge. This makes entities easier to test in isolation and supports use cases where the same entity type might be persisted to different data stores.

**Tradeoff vs Active Record:**
Data Mapper requires more boilerplate (a separate repository class per entity). For simple CRUD applications, Active Record is more concise. The Data Mapper approach pays off when the domain grows complex or when testability without a database is important.

---

## Key Classes and Responsibilities

### Entity (`src/Entity.php`)

Abstract base for all domain entities. Entities are pure data containers — they have no static query methods and no `save()` method.

**Static properties (configure per entity):**

| Property | Default | Meaning |
|---|---|---|
| `$table` | `''` (auto-derived) | Table name; if empty, derived as `snake_case + s` from class name |
| `$primaryKey` | `'id'` | Primary key column; `list<string>` for composite PKs |
| `$fillable` | `[]` | Allowed mass-assignment columns; if set, `$guarded` is ignored |
| `$guarded` | `[]` | Blocked mass-assignment columns; used only when `$fillable` is empty |
| `$casts` | `[]` | Column → type map: `int\|integer\|float\|double\|bool\|boolean\|string\|array\|json` or a `CastableInterface` class-string |
| `$timestamps` | `false` | Auto-set `created_at` / `updated_at` on insert/update (managed by repository) |
| `$softDeletes` | `false` | `delete()` sets `deleted_at`; query filters `WHERE deleted_at IS NULL` |

**Database registry** — `Entity::setDatabase($db)` stores under `static::class` key. `Entity::database()` resolves: subclass entry → `Entity::class` entry → throws `EzPhpException`. `EntityServiceProvider::boot()` registers the shared default.

**Attribute access** — `__get`/`__set`/`__isset` delegate to `getAttribute`/`setAttribute`. `getAttribute` applies casts on read; `setAttribute` stores the raw value. The `fill(array)` method applies fillable/guarded guards.

**Relation slots** — `setRelation(key, value)` stores loaded relation results. Accessed via `__get` the same way as attributes.

`trashed()` — returns `true` if `deleted_at` is set (soft-delete check, entity-side).

---

### AbstractRepository (`src/AbstractRepository.php`)

`@template T of Entity`. Concrete repositories extend this and implement `entityClass(): string`.

**Constructor:**

```php
public function __construct(?DatabaseInterface $db = null, ?Hydrator $hydrator = null)
```

Both dependencies are optional — `$db` falls back to `Entity::database()`, `$hydrator` defaults to `new Hydrator()`.

**Core persistence methods:**

| Method | Returns | Behaviour |
|---|---|---|
| `find(id)` | `T\|null` | Looks up by primary key; returns hydrated entity or null |
| `save(T)` | `void` | INSERT if new entity (no PK); UPDATE dirty columns only if PK is set |
| `delete(T)` | `void` | Soft-delete if `$softDeletes`; hard-delete otherwise |
| `findAll(limit, offset)` | `list<T>` | Returns all rows, optionally bounded; `limit=0` = no limit; soft-delete filter applied |
| `findAllWhere(criteria, limit, offset)` | `list<T>` | AND-equality filter on a column map, optionally bounded |
| `findBy(col, val)` | `list<T>` | Single-column equality filter |
| `findOneBy(col, val)` | `T\|null` | Returns first match |
| `findWhereIn(col, vals)` | `list<T>` | Batch lookup |
| `countBy(col, ids)` | `array<mixed, int>` | Returns id → count map (used for `withCount`) |
| `query()` | `EntityQueryBuilder<T>` | Fluent builder with soft-delete filter |

**Dirty tracking (repository-side)** — `SplObjectStorage<Entity, array<string, mixed>>` stores an attribute snapshot at load time (`hydrateTracked()`). `getDirty()` diffs current attributes against the snapshot; only changed columns are included in `UPDATE`.

**Composite primary keys** — `$primaryKey` may be a `list<string>`. `isPrimaryKeyComposite()` checks; `performInsertComposite()` and `performUpdateComposite()` handle the multi-column case.

**Relation helpers** (call from concrete repository public methods):

| Method | Returns |
|---|---|
| `hasMany(relRepo, fk, localKey)` | `EntityHasMany` |
| `hasOne(relRepo, fk, localKey)` | `EntityHasOne` |
| `belongsTo(relRepo, fk, ownerKey)` | `EntityBelongsTo` |
| `belongsToMany(relRepo, pivotTable, fk, relatedFk, localKey)` | `EntityBelongsToMany` |

---

### EntityQueryBuilder (`src/EntityQueryBuilder.php`)

`@template T of Entity`. Typed wrapper over `QueryBuilder` that hydrates rows into entity instances.

**Clause methods** (all return `self<T>`): `select`, `where`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull`, `join`, `orderBy`, `limit`, `offset`.

**Execution methods:**

| Method | Returns |
|---|---|
| `get()` | `list<T>` — hydrates all rows; resolves eager-loaded relations |
| `first()` | `T\|null` |
| `count()` | `int` |
| `paginate(perPage, page)` | `Paginator<T>` |
| `chunk(size, callback)` | `void` |

**Eager loading** — `with(...relations)` queues relation names. `get()` calls `$repository->$relation($firstEntity)` to obtain an `EntityRelation`, batch-loads via `eagerLoadFor(ids)`, then calls `match()` to assign results. N+1 avoided; one extra query per eager-loaded relation.

**`withCount(...relations)`** — queries `countBy()` on each relation's repository and sets `{relation}_count` as a synthetic attribute on each entity.

---

### Hydrator (`src/Hydrator.php`)

Converts raw rows to entities and entities to storage arrays.

- `hydrate(entityClass, row): Entity` — sets all row columns directly via `setAttribute()`, bypassing fillable guards
- `dehydrate(entity, casts): array` — applies inverse casts (`CastableInterface::castTo()`, `array` → JSON) and returns a storage-compatible attribute map

---

### CastableInterface (`src/CastableInterface.php`)

Implement on a value-object class for custom casting:

```php
class Money implements CastableInterface
{
    public static function castFrom(mixed $value): static { /* ... */ }
    public function castTo(): mixed { /* ... */ }
}

protected static array $casts = ['price' => Money::class];
```

`getAttribute()` calls `castFrom()` on read; `Hydrator::dehydrate()` calls `castTo()` before writing to the DB.

---

### QueryBuilder (`src/QueryBuilder.php`)

Fluent builder for raw SQL. All clause methods return a clone — the original is never mutated.

**Clause methods** (all return `self`):

| Category | Methods |
|---|---|
| Select | `select(string ...$columns)` |
| Where | `where`, `orWhere`, `whereIn`, `whereNotIn`, `whereNull`, `whereNotNull` |
| Join | `join` (INNER), `leftJoin` |
| Group | `groupBy`, `having` |
| Order | `orderBy` |
| Pagination | `limit`, `offset` |

**`where(column, operatorOrValue, value = null)`** — two-argument form defaults operator to `=`; three-argument form validates the operator against the allowed list and throws `InvalidArgumentException` on invalid operators.

**OFFSET without LIMIT** — throws `InvalidArgumentException`; always call `limit()` before `offset()`.

**Execution methods:**

| Method | Returns |
|---|---|
| `get()` | `list<array<string, mixed>>` |
| `first()` | `array<string, mixed>\|null` |
| `count()` | `int` |
| `sum(column)` | `float` |
| `avg(column)` | `float` |
| `min(column)` | `string\|int\|float\|null` |
| `max(column)` | `string\|int\|float\|null` |
| `insert(array)` | `bool` |
| `update(array)` | `int` (rows affected) |
| `delete()` | `int` (rows affected) |
| `paginate(perPage, page)` | `Paginator<array<string, mixed>>` |
| `chunk(size, callback)` | `void` — iterates chunks until exhausted |
| `cache(ttl, CacheInterface)` | `self` — next `get()` will read from/write to cache |

---

### Relations (`src/Relations/`)

All relations extend `EntityRelation` and implement:
- `getResults()` — lazy fetch (list)
- `getResult()` — lazy fetch (single, for HasOne/BelongsTo)
- `getOwnerKey()` / `getForeignKey()` / `getLocalKey()` — key metadata for eager loading
- `eagerLoadFor(list $ids)` — single batch query
- `match(entities, results, relation)` — distribute results back onto owning entities

| Class | Direction | FK location |
|---|---|---|
| `EntityHasMany` | one → many | FK on **related** entity |
| `EntityHasOne` | one → one | FK on **related** entity |
| `EntityBelongsTo` | many → one | FK on **owning** entity |
| `EntityBelongsToMany` | many ↔ many | pivot table; raw SQL join |

---

### Schema / Blueprint (`src/Schema/`)

**Schema** — DDL façade. Detects driver (`sqlite` vs MySQL) from `PDO::ATTR_DRIVER_NAME`.

| Method | Notes |
|---|---|
| `create(table, callback)` | Runs CREATE TABLE + any index statements |
| `table(table, callback)` | Runs ALTER TABLE statements; guards against RENAME COLUMN on SQLite < 3.25 |
| `drop(table)` / `dropIfExists(table)` | DROP TABLE |
| `hasTable(table)` | `sqlite_master` (SQLite) or `INFORMATION_SCHEMA` (MySQL) |
| `hasColumn(table, col)` | `PRAGMA table_info` (SQLite) or `INFORMATION_SCHEMA.COLUMNS` (MySQL) |
| `rename(from, to)` | `ALTER TABLE … RENAME TO` (SQLite) / `RENAME TABLE` (MySQL) |
| `dump(path)` | Writes all CREATE TABLE + CREATE INDEX statements to a SQL file |

**Blueprint** — operates in `'create'` or `'alter'` mode. Column methods route to `$columns` (create) or `$addedColumns` (alter).

**Column type methods** (Blueprint normalises types per driver; SQLite fallbacks in parentheses):

| Method | MySQL | SQLite |
|---|---|---|
| `id()` | `INT UNSIGNED AUTO_INCREMENT` | `INTEGER` (PK) |
| `string(col, n)` | `VARCHAR(n)` | `TEXT` |
| `text(col)` / `longText` / `mediumText` / `tinyText` | `TEXT` / `LONGTEXT` / `MEDIUMTEXT` / `TINYTEXT` | `TEXT` |
| `integer(col)` | `INTEGER` | `INTEGER` |
| `tinyInteger` / `smallInteger` / `mediumInteger` | `TINYINT` / `SMALLINT` / `MEDIUMINT` | `INTEGER` |
| `bigInteger(col)` | `BIGINT` | `INTEGER` |
| `unsignedInteger(col)` / `unsignedBigInteger` / `unsignedTinyInteger` | `INT UNSIGNED` / `BIGINT UNSIGNED` / `TINYINT UNSIGNED` | `INTEGER` |
| `float(col)` | `FLOAT` | `FLOAT` |
| `decimal(col, p, s)` | `DECIMAL(p,s)` | `NUMERIC(p,s)` |
| `boolean(col)` | `TINYINT(1)` | `INTEGER` |
| `uuid(col)` / `ulid(col)` | `CHAR(36)` / `CHAR(26)` | `TEXT` |
| `date(col)` / `time(col)` / `dateTime(col)` / `year(col)` | `DATE` / `TIME` / `DATETIME` / `YEAR` | `TEXT` |
| `timestamp(col)` | `TIMESTAMP` | `TEXT` |
| `json(col)` | `JSON` | `TEXT` |
| `binary(col)` | `BLOB` | `BLOB` |
| `ipAddress(col)` / `macAddress(col)` | `VARCHAR(45)` / `VARCHAR(17)` | `TEXT` |
| `enum(col, values)` | `ENUM(...)` | `TEXT` + `CHECK` constraint |

**Convenience column methods:**

| Method | Adds |
|---|---|
| `timestamps()` | `created_at` + `updated_at` TIMESTAMP NULL |
| `softDeletes()` | `deleted_at` TIMESTAMP NULL |
| `rememberToken()` | `remember_token` VARCHAR(100) NULL |
| `morphs(name)` | `{name}_type` VARCHAR(255) + `{name}_id` BIGINT UNSIGNED + composite index |
| `nullableMorphs(name)` | same as morphs but both columns nullable |
| `foreignId(col)` | BIGINT UNSIGNED column + registers FK; returns `ForeignKeyDefinition` for chaining |

**Foreign key fluent API:**

```php
$table->foreign('user_id')->references('id')->on('users')->onDelete('cascade')->onUpdate('restrict');
$table->foreignId('user_id')->constrained();                    // infers references('id')->on('users')
$table->foreignId('post_id')->constrained('articles');          // explicit table override
```

Cascade actions: `cascade`, `restrict`, `set null`, `no action`. FK constraints are named `fk_{table}_{col}` and emit `CONSTRAINT … FOREIGN KEY … ON DELETE … ON UPDATE …`.

**Expression-based defaults:**

```php
$table->timestamp('created_at')->default(Expression::raw('CURRENT_TIMESTAMP'));
$table->timestamp('updated_at')->useCurrent()->useCurrentOnUpdate();
```

`Expression::raw(string)` emits the value verbatim in `DEFAULT` clauses instead of quoting it as a string literal.

**Column modifiers:** `->nullable()`, `->default(value)`, `->unique()`, `->after(col)`, `->first()`, `->change()` (marks for MODIFY COLUMN in MySQL ALTER), `->useCurrent()`, `->useCurrentOnUpdate()`.

**ALTER TABLE operations:**

| Method | MySQL | SQLite |
|---|---|---|
| `addColumn` / `addedColumns` | `ADD COLUMN` | `ADD COLUMN` |
| `dropColumn(col)` / `dropColumns([…])` | `DROP COLUMN` | `DROP COLUMN` |
| `renameColumn(from, to)` | `RENAME COLUMN` | `RENAME COLUMN` (requires 3.25+) |
| `change()` on column | `MODIFY COLUMN` | skipped silently |
| `dropIndex(name)` / `dropUnique(name)` | `DROP INDEX` | standalone `DROP INDEX` |
| `dropForeign(name)` | `DROP FOREIGN KEY` | skipped |
| `dropPrimary()` | `DROP PRIMARY KEY` | skipped |
| `dropTimestamps()` | drops `created_at` + `updated_at` | drops `created_at` + `updated_at` |
| `dropSoftDeletes()` | drops `deleted_at` | drops `deleted_at` |
| `dropMorphs(name)` | drops both columns + index | drops both columns + `DROP INDEX` |
| `foreign(col)` / `foreignId(col)` | `ADD CONSTRAINT … FOREIGN KEY` | skipped |

**Indexes** — generated as separate `CREATE INDEX` statements (not inline in `CREATE TABLE`).

---

## Design Decisions and Constraints

- **Data Mapper instead of Active Record** — Entities are plain PHP objects with no static query methods and no `save()`. All persistence is in the repository. This separates the domain model from the storage mechanism, makes entities unit-testable without a database, and avoids the global state problems of Active Record base classes.
- **Repository-side dirty tracking via `SplObjectStorage`** — Storing `$original` inside the entity would couple the entity to its own persistence history, violating the Data Mapper principle. `SplObjectStorage` keyed by entity object identity keeps the snapshot outside the entity and is garbage-collected when the entity goes out of scope.
- **Per-class database registry** — A plain `protected static $db` would be shared across the entire class hierarchy via PHP's static property inheritance rules. The `array<class-string, Database>` registry keyed by `static::class` (via late-static-binding write) allows different entity types to use different connections without interference.
- **`Entity::setDatabase(Entity::class)` as the shared default** — `EntityServiceProvider::boot()` registers the default. `database()` falls back to this after checking the specific subclass key.
- **`Entity::resetDatabase()` for tests** — Tests must call this (or use `RepositoryTestCase`) in `tearDown`. Omitting it leaks the database connection across test classes.
- **Dirty tracking compares cast fields normalised** — When a column has an `array`/`json` or `CastableInterface` cast, `normalizeForComparison()` reduces values to a comparable form (JSON string for arrays, `castTo()` for custom types) to avoid false dirty positives after a round-trip.
- **`performUpdate()` sends only dirty columns** — An `UPDATE` with an empty diff is a no-op. This prevents unnecessary DB round-trips and timestamp updates when nothing changed.
- **Composite PKs supported** — `$primaryKey` can be a `list<string>`. `save()` dispatches to `performInsertComposite`/`performUpdateComposite` accordingly.
- **`CastableInterface` for domain value objects** — Custom value objects (e.g. `Money`, `EmailAddress`) implement `castFrom`/`castTo` and are registered in `$casts`. This keeps domain types in the entity without framework coupling.
- **`QueryBuilder::cache()` integration** — Pass a `CacheInterface` instance and TTL; the next `get()` will read from cache on hit or execute the query and store the result on miss. Cache is keyed by the compiled SQL + bindings.
- **Blueprint is driver-aware** — The same migration code runs on both SQLite (tests) and MySQL (production). Type normalisation is handled centrally in `Blueprint`.
- **`Expression` for raw SQL defaults** — `ColumnDefinition::default()` accepts any value; when the value is an `Expression` instance, `Blueprint::compileDefault()` emits the raw string verbatim instead of quoting it. This enables `DEFAULT CURRENT_TIMESTAMP`, `DEFAULT (UUID())`, etc. without breaking the type-safe default path for literals.
- **SQLite 3.25+ required for `RENAME COLUMN`** — `Schema::table()` inspects pending ALTER statements and throws a descriptive `RuntimeException` if the running SQLite version is below 3.25.0. The table-recreation fallback is not implemented because PHP 8.5 targets platforms where 3.25+ is always available.
- **`Schema::dump()` is SQLite/MySQL only** — The dump logic uses `sqlite_master` or `SHOW CREATE TABLE`. Other drivers are not supported.
- **`QueryBuilder` uses clone-based withers** — Every clause method clones the builder so intermediate states can be reused without side effects.

---

## Testing Approach

- **`RepositoryTestCase`** — Provides a fresh in-memory SQLite database (`sqlite::memory:`) wired via `Entity::setDatabase()` for every test method. Override `setUpDatabase()` to create tables and seed fixtures. `tearDown` calls `Entity::resetDatabase()`.
- **No MySQL required for most tests** — SQLite covers the full ORM surface. MySQL-specific behaviour (e.g. `INFORMATION_SCHEMA` in `hasTable`) should be noted in test comments.
- **`QueryBuilder` and `Schema` tests** — Also use SQLite in-memory. `QueryBuilderTest` constructs a `Database` directly; `RepositoryTestCase` is not needed.
- **Relation eager-load tests** — Set up two related tables in `setUpDatabase()`, seed rows, call `with('relation')` on the repository, and assert the relation is populated on each returned entity.
- **`CastableInterface` tests** — Use inline anonymous classes. Assert `getAttribute()` returns the cast object and that `save()` stores the correct scalar value.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Raw PDO / transaction management | `ez-php/framework` (`Database`) |
| Migration tracking (`migrations` table) | `ez-php/framework` (`Migrator`) |
| Validation of entity attributes | `ez-php/validation` |
| Caching of query results (application-level) | `ez-php/cache` + application layer |
| Full-text search | Infrastructure layer / dedicated search service |
| Database seeding commands | Application layer |
| Complex pivot data / pivot entities | Application layer (extend `EntityBelongsToMany` or use raw `QueryBuilder`) |
