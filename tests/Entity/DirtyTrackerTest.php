<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\DirtyTracker;
use EzPhp\Orm\Entity;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\TestCase;

final class DirtyTrackerEntity extends Entity
{
    protected static array $fillable = ['name', 'tags'];
}

#[CoversClass(DirtyTracker::class)]
#[UsesClass(Entity::class)]
final class DirtyTrackerTest extends TestCase
{
    public function testUntrackedEntityIsEntirelyDirty(): void
    {
        $tracker = new DirtyTracker();
        $entity = new DirtyTrackerEntity(['name' => 'Alice']);

        $this->assertFalse($tracker->isTracked($entity));
        $this->assertSame(['name' => 'Alice'], $tracker->dirty($entity));
    }

    public function testTrackedEntityIsCleanUntilAnAttributeChanges(): void
    {
        $tracker = new DirtyTracker();
        $entity = new DirtyTrackerEntity(['name' => 'Alice']);

        $tracker->track($entity);
        $this->assertTrue($tracker->isTracked($entity));
        $this->assertSame([], $tracker->dirty($entity));

        $entity->setAttribute('name', 'Bob');
        $this->assertSame(['name' => 'Bob'], $tracker->dirty($entity));
    }

    public function testNewAttributeCountsAsDirty(): void
    {
        $tracker = new DirtyTracker();
        $entity = new DirtyTrackerEntity(['name' => 'Alice']);
        $tracker->track($entity);

        $entity->setAttribute('tags', 'x');

        $this->assertSame(['tags' => 'x'], $tracker->dirty($entity));
    }

    public function testArrayAttributesCompareByValue(): void
    {
        $tracker = new DirtyTracker();
        $entity = new DirtyTrackerEntity(['tags' => ['a', 'b']]);
        $tracker->track($entity);

        $entity->setAttribute('tags', ['a', 'b']);
        $this->assertSame([], $tracker->dirty($entity));

        $entity->setAttribute('tags', ['a', 'c']);
        $this->assertSame(['tags' => ['a', 'c']], $tracker->dirty($entity));
    }

    public function testForgetDropsTheBaseline(): void
    {
        $tracker = new DirtyTracker();
        $entity = new DirtyTrackerEntity(['name' => 'Alice']);
        $tracker->track($entity);

        $tracker->forget($entity);

        $this->assertFalse($tracker->isTracked($entity));
    }

    public function testEntitiesAreTrackedIndependently(): void
    {
        $tracker = new DirtyTracker();
        $a = new DirtyTrackerEntity(['name' => 'A']);
        $b = new DirtyTrackerEntity(['name' => 'A']);
        $tracker->track($a);

        $this->assertTrue($tracker->isTracked($a));
        $this->assertFalse($tracker->isTracked($b));
    }
}
