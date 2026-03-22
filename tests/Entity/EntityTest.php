<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\CastableInterface;
use EzPhp\Orm\Entity;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

/**
 * Named fixture with @property declarations so PHPStan understands magic __get/__set.
 *
 * @property string $name
 * @property int    $age
 * @property string $email
 */
final class BasicTestEntity extends Entity
{
    protected static array $fillable = ['name', 'age', 'email'];
}

#[CoversClass(Entity::class)]
final class EntityTest extends TestCase
{
    // ─── Fixtures ────────────────────────────────────────────────────────────

    private function makeBasicEntity(): BasicTestEntity
    {
        return new BasicTestEntity(['name' => 'Alice', 'age' => 30]);
    }

    // ─── Fill / attribute access ─────────────────────────────────────────────

    public function testFillSetsAllowedAttributes(): void
    {
        $entity = $this->makeBasicEntity();

        self::assertSame('Alice', $entity->getAttribute('name'));
        self::assertSame(30, $entity->getAttribute('age'));
    }

    public function testFillRespectsFillableGuard(): void
    {
        $entity = new class (['name' => 'Alice', 'secret' => 'x']) extends Entity {
            protected static array $fillable = ['name'];
        };

        self::assertSame('Alice', $entity->getAttribute('name'));
        self::assertNull($entity->getAttribute('secret'));
    }

    public function testFillRespectsGuardedList(): void
    {
        $entity = new class (['name' => 'Alice', 'password' => 'secret']) extends Entity {
            protected static array $guarded = ['password'];
        };

        self::assertSame('Alice', $entity->getAttribute('name'));
        self::assertNull($entity->getAttribute('password'));
    }

    public function testSetAttributeBypassesFillableGuard(): void
    {
        $entity = new class () extends Entity {
            protected static array $fillable = ['name'];
        };

        $entity->setAttribute('secret', 'value');

        self::assertSame('value', $entity->getAttribute('secret'));
    }

    public function testMagicGetSet(): void
    {
        $entity = $this->makeBasicEntity();
        $entity->email = 'alice@example.com';

        self::assertSame('alice@example.com', $entity->email);
    }

    public function testMagicIsset(): void
    {
        $entity = $this->makeBasicEntity();

        self::assertTrue(isset($entity->name));
        self::assertFalse(isset($entity->nonexistent));
    }

    public function testGetAttributesReturnsAllAttributes(): void
    {
        $entity = $this->makeBasicEntity();
        $attrs = $entity->getAttributes();

        self::assertSame(['name' => 'Alice', 'age' => 30], $attrs);
    }

    public function testGetAttributeReturnsNullForMissingKey(): void
    {
        $entity = $this->makeBasicEntity();

        self::assertNull($entity->getAttribute('missing'));
    }

    // ─── Casts ───────────────────────────────────────────────────────────────

    public function testIntCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['count' => 'int'];
        };
        $entity->setAttribute('count', '42');

        self::assertSame(42, $entity->getAttribute('count'));
    }

    public function testFloatCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['price' => 'float'];
        };
        $entity->setAttribute('price', '9.99');

        self::assertSame(9.99, $entity->getAttribute('price'));
    }

    public function testBoolCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['active' => 'bool'];
        };
        $entity->setAttribute('active', '1');

        self::assertTrue($entity->getAttribute('active'));
    }

    public function testStringCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['code' => 'string'];
        };
        $entity->setAttribute('code', 123);

        self::assertSame('123', $entity->getAttribute('code'));
    }

    public function testArrayCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['tags' => 'array'];
        };
        $entity->setAttribute('tags', '["php","orm"]');

        self::assertSame(['php', 'orm'], $entity->getAttribute('tags'));
    }

    public function testJsonCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['meta' => 'json'];
        };
        $entity->setAttribute('meta', '{"key":"value"}');

        self::assertSame(['key' => 'value'], $entity->getAttribute('meta'));
    }

    public function testCastableInterfaceCast(): void
    {
        $castClass = new class ('') implements CastableInterface {
            public function __construct(private readonly string $value)
            {
            }

            public static function castFrom(mixed $value): static
            {
                return new static(is_scalar($value) ? (string) $value : '');
            }

            public function castTo(): mixed
            {
                return $this->value;
            }

            public function getValue(): string
            {
                return $this->value;
            }
        };

        $castClassName = $castClass::class;

        $entity = new class ($castClassName) extends Entity {
            public function __construct(private string $castClass)
            {
                parent::__construct();
                static::$casts = ['status' => $this->castClass];
            }
        };

        $entity->setAttribute('status', 'active');
        $result = $entity->getAttribute('status');

        self::assertInstanceOf($castClassName, $result);
    }

    public function testNullValueSkipsCast(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['count' => 'int'];
        };
        $entity->setAttribute('count', null);

        self::assertNull($entity->getAttribute('count'));
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function testSetRelationAndGetViaAttribute(): void
    {
        $entity = $this->makeBasicEntity();
        $related = $this->makeBasicEntity();

        $entity->setRelation('profile', $related);

        self::assertSame($related, $entity->getAttribute('profile'));
    }

    public function testRelationTakesPrecedenceOverAttribute(): void
    {
        $entity = $this->makeBasicEntity();
        $entity->setAttribute('posts', 'raw');

        $posts = [new class () extends Entity {}];
        $entity->setRelation('posts', $posts);

        self::assertSame($posts, $entity->getAttribute('posts'));
    }

    // ─── Primary key ─────────────────────────────────────────────────────────

    public function testGetKeyReturnsScalarPk(): void
    {
        $entity = new class () extends Entity {};
        $entity->setAttribute('id', 42);

        self::assertSame(42, $entity->getKey());
    }

    public function testGetKeyReturnsNullWhenPkNotSet(): void
    {
        $entity = new class () extends Entity {};

        self::assertNull($entity->getKey());
    }

    public function testGetKeyReturnsArrayForCompositePk(): void
    {
        $entity = new class () extends Entity {
            protected static string|array $primaryKey = ['user_id', 'role_id'];
        };
        $entity->setAttribute('user_id', 1);
        $entity->setAttribute('role_id', 2);

        self::assertSame(['user_id' => 1, 'role_id' => 2], $entity->getKey());
    }

    // ─── Soft deletes ────────────────────────────────────────────────────────

    public function testTrashedReturnsFalseWhenNotDeleted(): void
    {
        $entity = new class () extends Entity {};

        self::assertFalse($entity->trashed());
    }

    public function testTrashedReturnsTrueWhenDeletedAtSet(): void
    {
        $entity = new class () extends Entity {};
        $entity->setAttribute('deleted_at', '2025-01-01 00:00:00');

        self::assertTrue($entity->trashed());
    }

    // ─── Static schema helpers ───────────────────────────────────────────────

    public function testResolveTableDerivesFromClassName(): void
    {
        $entity = new class () extends Entity {};

        // Anonymous class short name is "@anonymous..." so resolveTable falls through
        // to the raw class name — just verify it returns a non-empty string.
        self::assertNotEmpty($entity::resolveTable());
    }

    public function testResolveTableUsesStaticProperty(): void
    {
        $entity = new class () extends Entity {
            protected static string $table = 'custom_table';
        };

        self::assertSame('custom_table', $entity::resolveTable());
    }

    public function testIsPrimaryKeyCompositeReturnsFalse(): void
    {
        self::assertFalse((new class () extends Entity {})->isPrimaryKeyComposite());
    }

    public function testIsPrimaryKeyCompositeReturnsTrue(): void
    {
        $entity = new class () extends Entity {
            protected static string|array $primaryKey = ['user_id', 'role_id'];
        };

        self::assertTrue($entity::isPrimaryKeyComposite());
    }

    public function testHasSoftDeletesReflectsStaticFlag(): void
    {
        $entity = new class () extends Entity {
            protected static bool $softDeletes = true;
        };

        self::assertTrue($entity::hasSoftDeletes());
        self::assertFalse((new class () extends Entity {})->hasSoftDeletes());
    }

    public function testHasTimestampsReflectsStaticFlag(): void
    {
        $entity = new class () extends Entity {
            protected static bool $timestamps = true;
        };

        self::assertTrue($entity::hasTimestamps());
        self::assertFalse((new class () extends Entity {})->hasTimestamps());
    }

    public function testGetCastsReturnsStaticMap(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['age' => 'int'];
        };

        self::assertSame(['age' => 'int'], $entity::getCasts());
    }

    public function testGetFillableReturnsStaticList(): void
    {
        $entity = new class () extends Entity {
            protected static array $fillable = ['name', 'email'];
        };

        self::assertSame(['name', 'email'], $entity::getFillable());
    }
}
