# ez-php/orm

ORM module for the [ez-php framework](https://github.com/ez-php/framework) — Active Record style models with a fluent query builder and schema builder.

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

Register the service provider:

```php
$app->register(\EzPhp\Orm\ModelServiceProvider::class);
$app->register(\EzPhp\Orm\Schema\SchemaServiceProvider::class);
```

## Usage

### Defining a model

```php
use EzPhp\Orm\Model;

class User extends Model
{
    protected static string $table = 'users';
}
```

### Querying

```php
$user  = User::find(1);
$users = User::where('active', true)->orderBy('name')->get();
$count = User::count();
```

### Persisting

```php
$user = new User(['name' => 'Alice', 'email' => 'alice@example.com']);
$user->save();

$user->name = 'Bob';
$user->save();

$user->delete();
```

### Relations

```php
class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

$post->author; // lazy-loaded User
```

### Schema builder

```php
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('name');
    $table->string('email')->unique();
    $table->timestamps();
});
```

## License

MIT — [Andreas Uretschnig](mailto:andreas.uretschnig@gmail.com)
