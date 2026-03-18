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

Run from the new module root (requires `"ez-php/docker": "0.*"` in `require-dev`):

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
| **next free** | **3310** | **6381** |

Only set a port for services the module actually uses. Modules without external services need no port config.

---

# Package: ez-php/orm

Active Record ORM, fluent Query Builder, and Schema Builder.

---

## Source Structure

```
src/
├── Model.php                         — Abstract Active Record base; attributes, dirty tracking, relations, hooks
├── ModelQueryBuilder.php             — Typed query builder returning hydrated Model instances; eager-load, paginate, chunk
├── Paginator.php                     — Immutable value object wrapping a page of results with total/lastPage/hasMorePages/firstItem/lastItem
├── QueryBuilder.php                  — Fluent SQL builder for raw row queries; all WHERE/JOIN/ORDER/LIMIT/aggregates/paginate/chunk
├── ModelServiceProvider.php          — Calls Model::setDatabase($db) in boot(); no bindings registered
├── Console/
│   └── MakeModelCommand.php          — Scaffolds a Model subclass in src/Models/
├── Relations/
│   ├── Relation.php                  — Abstract base; contracts for getResults/eagerLoadFor/match/getOwnerKey
│   ├── HasMany.php                   — One-to-many: FK on related model
│   ├── HasOne.php                    — One-to-one: FK on related model
│   ├── BelongsTo.php                 — Inverse of HasMany/HasOne: FK on owning model
│   └── BelongsToMany.php             — Many-to-many via pivot table; raw SQL join
└── Schema/
    ├── Schema.php                    — DDL façade: create/table/drop/dropIfExists/hasTable
    ├── Blueprint.php                 — Column and constraint definitions; generates CREATE/ALTER/INDEX SQL
    ├── ColumnDefinition.php          — Fluent column spec: nullable/default/unique
    ├── ForeignKeyDefinition.php      — FK constraint: references(table).on(column)
    └── SchemaServiceProvider.php     — Binds Schema lazily to the container

tests/
├── TestCase.php                      — Base PHPUnit test case
├── ModelTestCase.php                 — In-memory SQLite DB wired to Model; fresh DB per test method
├── QueryBuilderTest.php              — Covers all QB clauses and execution methods
├── PaginatorTest.php                 — Unit tests for Paginator value object (all accessors, edge cases)
├── PaginationTest.php                — Integration tests for QB/MQB paginate() and chunk()
├── ORM/ModelTest.php                 — Covers Model CRUD, dirty tracking, casts, soft deletes, relations, hooks
├── ORM/ModelServiceProviderTest.php  — Covers provider boot wiring
├── Schema/BlueprintTest.php          — Covers Blueprint SQL generation for all column types and modes
├── Schema/SchemaTest.php             — Covers Schema DDL methods against real SQLite
├── Schema/SchemaServiceProviderTest.php
└── Console/MakeModelCommandTest.php
```

---

## Key Classes and Responsibilities

### QueryBuilder (`src/QueryBuilder.php`)

Fluent builder for raw SQL. All wither methods return a clone — the original is not mutated.

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

**OFFSET without LIMIT** — emits `LIMIT PHP_INT_MAX OFFSET n` so MySQL/SQLite accept it.

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

---

### Model (`src/Model.php`)

Abstract Active Record base. Subclasses define the table via static properties.

**Static properties (configure per model):**

| Property | Default | Meaning |
|---|---|---|
| `$table` | `''` (auto-derived) | Table name; if empty, derived as `snake_case + s` from class name |
| `$primaryKey` | `'id'` | Primary key column |
| `$fillable` | `[]` | Allowed mass-assignment columns; if set, `$guarded` is ignored |
| `$guarded` | `[]` | Blocked mass-assignment columns; used only when `$fillable` is empty |
| `$casts` | `[]` | Column → type map: `int\|integer\|float\|double\|bool\|boolean\|string\|array\|json` |
| `$timestamps` | `false` | Auto-set `created_at` / `updated_at` on insert/update |
| `$softDeletes` | `false` | `delete()` sets `deleted_at`; `query()` adds `WHERE deleted_at IS NULL` |

**Database registry** — `Model::setDatabase($db)` stores under `static::class` key. Subclass calls register a per-model connection; base `Model::setDatabase()` registers the shared default. `database()` resolves: subclass entry → `Model::class` entry → throws `EzPhpException`.

**Querying:**

| Method | Returns |
|---|---|
| `find(id)` | `static\|null` |
| `all()` | `list<static>` |
| `where(col, val)` | `ModelQueryBuilder<static>` |
| `whereIn(col, values)` | `ModelQueryBuilder<static>` |
| `with(...relations)` | `ModelQueryBuilder<static>` |
| `query()` | `ModelQueryBuilder<static>` (with soft-delete filter) |
| `withTrashed()` | `ModelQueryBuilder<static>` (no soft-delete filter) |
| `onlyTrashed()` | `ModelQueryBuilder<static>` (only deleted rows) |

**Persistence:**

| Method | Behaviour |
|---|---|
| `save()` | INSERT if no PK; UPDATE dirty columns only if PK present |
| `create(array)` | `new static($data); save(); return $model` |
| `delete()` | Soft-delete if `$softDeletes`; hard-delete otherwise |
| `forceDelete()` | Always hard-deletes; ignores `$softDeletes` |
| `restore()` | Sets `deleted_at = null` on a soft-deleted model |

**Dirty tracking** — `$original` is synced on `fromRaw()` and after successful insert/update. `getDirty()` returns only changed columns. `normalizeForComparison()` serialises `array`/`json` cast fields to JSON for comparison, preventing false positives.

**Attribute access** — `__get`/`__set`/`__isset` delegate to `getAttribute`/`setAttribute`. `getAttribute` applies casts on read; `prepareForStorage` applies inverse casts (array → JSON) on write.

**Lifecycle hooks** (override in subclass, no-ops by default):
`beforeSave` · `afterSave` · `beforeCreate` · `afterCreate` · `beforeUpdate` · `afterUpdate` · `beforeDelete` · `afterDelete`

---

### ModelQueryBuilder (`src/ModelQueryBuilder.php`)

Generic typed wrapper over `QueryBuilder`. `@template TModel of Model`.

- `get()` — hydrates each row via `TModel::fromRaw($row)`, then resolves eager-loaded relations
- `first()` — hydrates single row
- `with(...relations)` — queues relation names for eager loading

**Eager loading** — `loadEagerRelations()` calls the first model's relation method to obtain a `Relation` instance, collects owner-key values across all models in a single `whereIn` query, then calls `match()` to set relations on each model. N+1 is avoided; one extra query per eager-loaded relation.

---

### Relations (`src/Relations/`)

All relations extend `Relation` and implement:
- `getResults()` — lazy fetch
- `getOwnerKey()` — which key to collect from parent models for batch loading
- `eagerLoadFor(list $ids)` — single batch query
- `match(models, results, relation)` — distribute results back onto parent models

| Class | Direction | FK location |
|---|---|---|
| `HasMany` | one → many | FK on **related** model |
| `HasOne` | one → one | FK on **related** model |
| `BelongsTo` | many → one | FK on **owning** model |
| `BelongsToMany` | many ↔ many | pivot table; uses raw SQL join with `__pivot_fk` alias |

`BelongsToMany` issues raw SQL directly against the `Database` instance, not via `QueryBuilder`, because the join spans the pivot table.

---

### Schema / Blueprint (`src/Schema/`)

**Schema** — DDL façade. Detects driver (`sqlite` vs MySQL) from `PDO::ATTR_DRIVER_NAME`. `hasTable()` uses `sqlite_master` for SQLite and `INFORMATION_SCHEMA` for MySQL.

**Blueprint** — operates in `'create'` or `'alter'` mode. Column methods route to `$columns` (create) or `$addedColumns` (alter).

**SQLite type mapping** (Blueprint normalises types per driver):

| Blueprint method | MySQL | SQLite |
|---|---|---|
| `id()` | `INT UNSIGNED AUTO_INCREMENT` | `INTEGER` (PK) |
| `string(col, n)` | `VARCHAR(n)` | `TEXT` |
| `boolean(col)` | `TINYINT(1)` | `INTEGER` |
| `timestamp(col)` | `TIMESTAMP` | `TEXT` |
| `date(col)` | `DATE` | `TEXT` |
| `json(col)` | `JSON` | `TEXT` |
| `bigInteger(col)` | `BIGINT` | `INTEGER` |
| `decimal(p, s)` | `DECIMAL(p,s)` | `NUMERIC(p,s)` |

**ALTER TABLE** — SQLite does not support `ADD FOREIGN KEY`, so FK constraints are skipped in alter mode for SQLite.

**Indexes** — generated as separate `CREATE INDEX` statements (not inline in `CREATE TABLE`).

---

## Design Decisions and Constraints

- **Per-class database registry instead of a static property** — A plain `protected static $db` would be shared across the entire class hierarchy via PHP's static property inheritance rules. The `array<class-string, Database>` registry keyed by `static::class` (via late-static-binding write) allows different model classes to use different connections without interference.
- **`Model::setDatabase(Model::class)` as the shared default** — Calling `Model::setDatabase($db)` registers under `Model::class`. `database()` falls back to this after checking the specific subclass key. This means `ModelServiceProvider::boot()` wires all models with one call.
- **`Model::resetDatabase()` for tests** — Tests must call `Model::resetDatabase()` (or use `ModelTestCase`) in `tearDown`. Omitting this leaks the database connection across test classes.
- **Dirty tracking compares cast fields as JSON** — When a column has an `array`/`json` cast, PHP array equality is unreliable after a round-trip through JSON (key order, type coercion). `normalizeForComparison()` serialises arrays to JSON strings before comparing, so only semantically different arrays are treated as dirty.
- **`performUpdate()` sends only dirty columns** — An `UPDATE` with an empty diff is a no-op (returns `true` immediately). This prevents unnecessary DB round-trips and timestamp updates when nothing changed.
- **`BelongsToMany` uses a `__pivot_fk` alias** — The eager-load query SELECTs the pivot FK column with an alias to support `match()`. This is an implementation detail; application code should not access `__pivot_fk` directly.
- **Hooks are empty protected methods, not events** — Lifecycle hooks are designed for direct override in subclasses, keeping the pattern explicit and traceable without requiring an event bus dependency.
- **Blueprint is driver-aware** — The same migration code runs on both SQLite (tests) and MySQL (production). Type normalisation is handled centrally in `Blueprint`, so migrations don't need `if ($driver === 'sqlite')` branches.
- **`QueryBuilder` uses clone-based withers** — Every clause method clones the builder so intermediate states can be reused without side effects. This also makes `ModelQueryBuilder` safe to share across relation eager-loading.

---

## Testing Approach

- **`ModelTestCase`** — Provides a fresh in-memory SQLite database (`sqlite::memory:`) wired via `Model::setDatabase()` for every test method. Override `setUpDatabase()` to create tables and seed fixtures. `tearDown` calls `Model::resetDatabase()`.
- **No MySQL required for most tests** — SQLite covers the full ORM surface. MySQL-specific behaviour (e.g. `INFORMATION_SCHEMA` in `hasTable`) should be noted in test comments.
- **`QueryBuilder` and `Schema` tests** — Also use SQLite in-memory. `QueryBuilderTest` does not use `ModelTestCase` (no model involved); it constructs a `Database` directly.
- **Relation eager-load tests** — Set up two related tables in `setUpDatabase()`, seed rows, call `with('relation')`, and assert the relation is populated on each model.
- **`#[UsesClass]` required** — PHPUnit is configured with `beStrictAboutCoverageMetadata=true`. Declare indirectly used classes with `#[UsesClass]`.

---

## What Does NOT Belong Here

| Concern | Where it belongs |
|---|---|
| Raw PDO / transaction management | `ez-php/framework` (`Database`) |
| Migration tracking (`migrations` table) | `ez-php/framework` (`Migrator`) |
| Validation of model attributes | `ez-php/validation` |
| Caching of query results | `ez-php/cache` + application layer |
| Full-text search | Infrastructure layer / dedicated search service |
| Database seeding commands | Application layer |
| Complex pivot data / pivot models | Application layer (extend `BelongsToMany` or use raw `QueryBuilder`) |

