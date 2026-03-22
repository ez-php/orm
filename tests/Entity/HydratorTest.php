<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\CastableInterface;
use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;
use PHPUnit\Framework\Attributes\CoversClass;
use Tests\TestCase;

#[CoversClass(Hydrator::class)]
final class HydratorTest extends TestCase
{
    private Hydrator $hydrator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->hydrator = new Hydrator();
    }

    // ─── Fixtures ────────────────────────────────────────────────────────────

    /**
     * @return class-string<Entity>
     */
    private function basicEntityClass(): string
    {
        return new class () extends Entity {
            protected static array $fillable = ['name'];
        }::class;
    }

    // ─── hydrate() ───────────────────────────────────────────────────────────

    public function testHydrateSetsAllRowColumns(): void
    {
        $entityClass = $this->basicEntityClass();

        /** @var Entity $entity */
        $entity = $this->hydrator->hydrate($entityClass, ['id' => 1, 'name' => 'Alice', 'secret' => 'x']);

        // All columns are set even if not in $fillable
        self::assertSame(1, $entity->getAttribute('id'));
        self::assertSame('Alice', $entity->getAttribute('name'));
        self::assertSame('x', $entity->getAttribute('secret'));
    }

    public function testHydrateReturnsCorrectEntityClass(): void
    {
        $entityClass = $this->basicEntityClass();

        $entity = $this->hydrator->hydrate($entityClass, ['id' => 1]);

        self::assertInstanceOf($entityClass, $entity);
    }

    public function testHydrateHandlesEmptyRow(): void
    {
        $entityClass = $this->basicEntityClass();

        $entity = $this->hydrator->hydrate($entityClass, []);

        self::assertSame([], $entity->getAttributes());
    }

    // ─── extract() ───────────────────────────────────────────────────────────

    public function testExtractReturnsRawAttributesWithoutCast(): void
    {
        $entity = new class () extends Entity {};
        $entity->setAttribute('name', 'Alice');
        $entity->setAttribute('age', 30);

        $result = $this->hydrator->extract($entity);

        self::assertSame(['name' => 'Alice', 'age' => 30], $result);
    }

    public function testExtractEncodesArrayCastToJson(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['tags' => 'array'];
        };
        $entity->setAttribute('tags', ['php', 'orm']);

        $result = $this->hydrator->extract($entity);

        self::assertSame('["php","orm"]', $result['tags']);
    }

    public function testExtractEncodesJsonCastToJson(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['meta' => 'json'];
        };
        $entity->setAttribute('meta', ['key' => 'value']);

        $result = $this->hydrator->extract($entity);

        self::assertSame('{"key":"value"}', $result['meta']);
    }

    public function testExtractAppliesCastableInterfaceCastTo(): void
    {
        $castClass = new class ('raw') implements CastableInterface {
            public function __construct(private readonly string $value)
            {
            }

            public static function castFrom(mixed $value): static
            {
                return new static(is_scalar($value) ? (string) $value : '');
            }

            public function castTo(): mixed
            {
                return 'serialised:' . $this->value;
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

        $castInstance = $castClass::castFrom('active');
        $entity->setAttribute('status', $castInstance);

        $result = $this->hydrator->extract($entity);

        self::assertSame('serialised:active', $result['status']);
    }

    public function testExtractLeavesNullValuesAsNull(): void
    {
        $entity = new class () extends Entity {
            protected static array $casts = ['tags' => 'array'];
        };
        $entity->setAttribute('tags', null);

        $result = $this->hydrator->extract($entity);

        self::assertNull($result['tags']);
    }

    public function testExtractRoundTripWithArrayCast(): void
    {
        /** @var class-string<Entity> $entityClass */
        $entityClass = new class () extends Entity {
            protected static array $casts = ['tags' => 'array'];
        }::class;

        // Simulate: DB row → hydrate → modify → extract back to DB
        $entity = $this->hydrator->hydrate($entityClass, ['id' => 1, 'tags' => '["a","b"]']);
        $extracted = $this->hydrator->extract($entity);

        // tags should be stored back as JSON string
        self::assertSame('["a","b"]', $extracted['tags']);
    }
}
