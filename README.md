# ez-php/orm

ORM module for the [ez-php framework](https://github.com/ez-php/framework) — Data Mapper pattern with `Entity`, `AbstractRepository`, a fluent `QueryBuilder`, and a `Schema` builder.

[![CI](https://github.com/ez-php/orm/actions/workflows/ci.yml/badge.svg)](https://github.com/ez-php/orm/actions/workflows/ci.yml)

## Requirements

- PHP 8.5+
- ext-pdo
- ez-php/framework 0.*

## Installation

```bash
composer require ez-php/orm
```

## Setup

Register the service providers:

```php
$app->register(\EzPhp\Orm\EntityServiceProvider::class);
$app->register(\EzPhp\Orm\Schema\SchemaServiceProvider::class);
```

## Usage

### Defining an entity

```php
use EzPhp\Orm\Entity;

class User extends Entity
{
    protected static string $table      = 'users';
    protected static bool   $timestamps = true;
    protected static array  $fillable   = ['name', 'email'];
    protected static array  $casts      = ['age' => 'int'];
}
```

### Defining a repository

```php
use EzPhp\Orm\AbstractRepository;

/**
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    protected function entityClass(): string
    {
        return User::class;
    }

    public function findByEmail(string $email): ?User
    {
        return $this->findOneBy('email', $email);
    }

    public function activeUsers(): array
    {
        return $this->query()->where('active', true)->orderBy('name')->get();
    }
}
```

### Persisting

```php
$repo = $app->make(UserRepository::class);

// INSERT
$user = new User(['name' => 'Alice', 'email' => 'alice@example.com']);
$repo->save($user);

// UPDATE (only dirty columns)
$user->name = 'Bob';
$repo->save($user);

// DELETE
$repo->delete($user);
```

### Querying

```php
$user  = $repo->find(1);
$all   = $repo->findAll();
$alice = $repo->findByEmail('alice@example.com');
$page  = $repo->query()->where('active', true)->paginate(perPage: 15, page: 1);
```

### Soft deletes

```php
class Post extends Entity
{
    protected static string $table       = 'posts';
    protected static bool   $softDeletes = true;
}
```

```php
$repo->delete($post);           // sets deleted_at — row stays in the DB
$post->trashed();               // true after soft delete

// Include soft-deleted rows
$all = $repo->query()->withTrashed()->get();
$deleted = $repo->query()->onlyTrashed()->get();
```

### Relations

Define relation helpers on the repository, then call them on an entity:

```php
class PostRepository extends AbstractRepository
{
    protected function entityClass(): string { return Post::class; }

    public function author(Post $post): EntityBelongsTo
    {
        return $this->belongsTo(UserRepository::class, 'user_id', 'id');
    }
}

// Lazy load
$author = $postRepo->author($post)->getResult();

// Eager load (avoids N+1)
$posts = $postRepo->query()->with('author')->get();
```

### Custom casts

```php
use EzPhp\Orm\CastableInterface;

class Money implements CastableInterface
{
    public function __construct(private readonly int $cents) {}

    public static function castFrom(mixed $value): static
    {
        return new self((int) $value);
    }

    public function castTo(): mixed
    {
        return $this->cents;
    }
}

class Product extends Entity
{
    protected static array $casts = ['price' => Money::class];
}
```

### Entity lifecycle observers

Attach observers to a repository to react to create/update/delete events:

```php
use EzPhp\Orm\EntityObserverInterface;
use EzPhp\Orm\ObservableRepositoryTrait;

class AuditObserver implements EntityObserverInterface
{
    public function creating(object $entity): void {}
    public function created(object $entity): void { /* log insert */ }
    public function updating(object $entity): void {}
    public function updated(object $entity): void { /* log update */ }
    public function deleting(object $entity): void {}
    public function deleted(object $entity): void { /* log delete */ }
}

class UserRepository extends AbstractRepository
{
    use ObservableRepositoryTrait;
    // ...
}

$repo->observe(new AuditObserver());
```

The `*ing` hooks fire before the DB operation; `*ed` hooks fire after.

### Schema builder

```php
use EzPhp\Orm\Schema\Schema;

Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});

Schema::table('users', function (Blueprint $table) {
    $table->string('phone')->nullable();
});

Schema::drop('old_table');
```

### Console commands

| Command | Description |
|---|---|
| `make:entity` | Scaffolds an `Entity` subclass in `src/Entities/` |
| `make:repository` | Scaffolds an `AbstractRepository` subclass in `src/Repositories/` |

## Classes

| Class | Description |
|---|---|
| `Entity` | Abstract Data Mapper entity base; attributes, casts, fillable guards, relation storage |
| `AbstractRepository` | Abstract repository base; INSERT/UPDATE/DELETE, dirty tracking, relations, eager-load |
| `EntityObserverInterface` | Lifecycle hook contract: `creating/created/updating/updated/deleting/deleted` |
| `ObservableRepositoryTrait` | Adds observer support to a repository; fires hooks around `save()` and `delete()` |
| `EntityQueryBuilder` | Typed fluent query builder for entities; `with()`, `withCount()`, `paginate()` |
| `EntityServiceProvider` | Calls `Entity::setDatabase($db)` in `boot()` |
| `Hydrator` | Converts raw DB rows → Entity instances and Entity attributes → storage arrays |
| `CastableInterface` | Interface for custom value-object casts: `castFrom()`/`castTo()` |
| `DuplicateKeyException` | Thrown by `save()` on duplicate-key violations |
| `Paginator` | Immutable page-of-results value object |
| `QueryBuilder` | Fluent SQL builder for raw rows; all WHERE/JOIN/ORDER/LIMIT/aggregates/paginate/chunk/cache |
| `EntityHasMany` | One-to-many relation (FK on related entity) |
| `EntityHasOne` | One-to-one relation (FK on related entity) |
| `EntityBelongsTo` | Inverse of HasMany/HasOne (FK on owning entity) |
| `EntityBelongsToMany` | Many-to-many relation via pivot table |
| `Schema` | DDL façade: `create()`, `table()`, `drop()`, `dropIfExists()`, `hasTable()` |
| `Blueprint` | Column and constraint builder for `CREATE TABLE` and `ALTER TABLE` |

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
