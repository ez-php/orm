<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\Entity;
use EzPhp\Orm\TypedAttributes;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

/**
 * Concrete entity fixture using the TypedAttributes trait.
 */
final class TypedEntity extends Entity
{
    use TypedAttributes;

    protected static array $fillable = [
        'count',
        'label',
        'active',
        'score',
        'title',
        'created_at',
    ];
}

/**
 * Class TypedAttributesTest
 *
 * @package Tests\Entity
 */
#[UsesClass(Entity::class)]
final class TypedAttributesTest extends TestCase
{
    // ─── getInt ──────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_int_returns_integer(): void
    {
        $e = new TypedEntity(['count' => '42']);
        $this->assertSame(42, $e->getInt('count'));
    }

    /**
     * @return void
     */
    public function test_get_int_returns_default_for_null(): void
    {
        $e = new TypedEntity([]);
        $this->assertSame(0, $e->getInt('count'));
        $this->assertSame(99, $e->getInt('count', 99));
    }

    /**
     * @return void
     */
    public function test_get_int_returns_default_for_non_numeric(): void
    {
        $e = new TypedEntity(['count' => 'abc']);
        $this->assertSame(0, $e->getInt('count'));
    }

    // ─── getString ───────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_string_returns_string(): void
    {
        $e = new TypedEntity(['label' => 'hello']);
        $this->assertSame('hello', $e->getString('label'));
    }

    /**
     * @return void
     */
    public function test_get_string_returns_default_for_null(): void
    {
        $e = new TypedEntity([]);
        $this->assertSame('', $e->getString('label'));
        $this->assertSame('n/a', $e->getString('label', 'n/a'));
    }

    /**
     * @return void
     */
    public function test_get_string_casts_int_to_string(): void
    {
        $e = new TypedEntity(['label' => 7]);
        $this->assertSame('7', $e->getString('label'));
    }

    // ─── getBool ─────────────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_bool_returns_true_for_truthy(): void
    {
        $e = new TypedEntity(['active' => 1]);
        $this->assertTrue($e->getBool('active'));
    }

    /**
     * @return void
     */
    public function test_get_bool_returns_false_for_falsy(): void
    {
        $e = new TypedEntity(['active' => 0]);
        $this->assertFalse($e->getBool('active'));
    }

    /**
     * @return void
     */
    public function test_get_bool_returns_false_for_missing(): void
    {
        $e = new TypedEntity([]);
        $this->assertFalse($e->getBool('active'));
    }

    // ─── getNullableInt ───────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_nullable_int_returns_int(): void
    {
        $e = new TypedEntity(['score' => '10']);
        $this->assertSame(10, $e->getNullableInt('score'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_int_returns_null_for_missing(): void
    {
        $e = new TypedEntity([]);
        $this->assertNull($e->getNullableInt('score'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_int_returns_null_for_non_numeric(): void
    {
        $e = new TypedEntity(['score' => 'abc']);
        $this->assertNull($e->getNullableInt('score'));
    }

    // ─── getNullableString ────────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_nullable_string_returns_string(): void
    {
        $e = new TypedEntity(['title' => 'foo']);
        $this->assertSame('foo', $e->getNullableString('title'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_string_returns_null_for_missing(): void
    {
        $e = new TypedEntity([]);
        $this->assertNull($e->getNullableString('title'));
    }

    // ─── getNullableDatetime ─────────────────────────────────────────────────

    /**
     * @return void
     */
    public function test_get_nullable_datetime_returns_datetime(): void
    {
        $e = new TypedEntity(['created_at' => '2024-01-15 10:30:00']);
        $dt = $e->getNullableDatetime('created_at');
        $this->assertNotNull($dt);
        $this->assertSame('2024-01-15 10:30:00', $dt->format('Y-m-d H:i:s'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_datetime_returns_null_for_missing(): void
    {
        $e = new TypedEntity([]);
        $this->assertNull($e->getNullableDatetime('created_at'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_datetime_returns_null_for_empty_string(): void
    {
        $e = new TypedEntity(['created_at' => '']);
        $this->assertNull($e->getNullableDatetime('created_at'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_datetime_returns_null_for_invalid_format(): void
    {
        $e = new TypedEntity(['created_at' => 'not-a-date']);
        $this->assertNull($e->getNullableDatetime('created_at'));
    }

    /**
     * @return void
     */
    public function test_get_nullable_datetime_returns_null_for_non_scalar(): void
    {
        $e = new TypedEntity([]);
        $e->setAttribute('created_at', ['2024-01-01']);
        $this->assertNull($e->getNullableDatetime('created_at'));
    }
}
