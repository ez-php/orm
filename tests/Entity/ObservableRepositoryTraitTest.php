<?php

declare(strict_types=1);

namespace Tests\Entity;

use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\EntityObserverInterface;
use EzPhp\Orm\ObservableRepositoryTrait;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\RepositoryTestCase;

// ─── Fixtures ────────────────────────────────────────────────────────────────

final class ObservableUserEntity extends Entity
{
    protected static string $table = 'users';

    protected static array $fillable = ['name'];
}

/** @extends AbstractRepository<ObservableUserEntity> */
final class ObservableUserRepository extends AbstractRepository
{
    /** @use ObservableRepositoryTrait<ObservableUserEntity> */
    use ObservableRepositoryTrait;

    protected function entityClass(): string
    {
        return ObservableUserEntity::class;
    }
}

/**
 * Spy observer that records fired events.
 */
final class SpyObserver implements EntityObserverInterface
{
    /** @var list<string> */
    public array $events = [];

    public function creating(object $entity): void
    {
        $this->events[] = 'creating';
    }

    public function created(object $entity): void
    {
        $this->events[] = 'created';
    }

    public function updating(object $entity): void
    {
        $this->events[] = 'updating';
    }

    public function updated(object $entity): void
    {
        $this->events[] = 'updated';
    }

    public function deleting(object $entity): void
    {
        $this->events[] = 'deleting';
    }

    public function deleted(object $entity): void
    {
        $this->events[] = 'deleted';
    }
}

// ─── Tests ───────────────────────────────────────────────────────────────────

#[UsesClass(AbstractRepository::class)]
#[UsesClass(Entity::class)]
final class ObservableRepositoryTraitTest extends RepositoryTestCase
{
    private ObservableUserRepository $users;

    private SpyObserver $spy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->users = new ObservableUserRepository($this->db, $this->hydrator);
        $this->spy = new SpyObserver();
        $this->users->observe($this->spy);
    }

    protected function setUpDatabase(): void
    {
        $this->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT)');
    }

    // ─── INSERT ──────────────────────────────────────────────────────────────

    public function test_save_new_entity_fires_creating_and_created(): void
    {
        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Alice');

        $this->users->save($entity);

        self::assertSame(['creating', 'created'], $this->spy->events);
    }

    public function test_creating_fires_before_entity_has_id(): void
    {
        $spy = new class () implements EntityObserverInterface {
            public bool $idWasNullAtCreating = false;

            public function creating(object $entity): void
            {
                $this->idWasNullAtCreating = ($entity instanceof ObservableUserEntity)
                    && $entity->getAttribute('id') === null;
            }

            public function created(object $entity): void
            {
            }

            public function updating(object $entity): void
            {
            }

            public function updated(object $entity): void
            {
            }

            public function deleting(object $entity): void
            {
            }

            public function deleted(object $entity): void
            {
            }
        };

        $repo = new ObservableUserRepository($this->db, $this->hydrator);
        $repo->observe($spy);

        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Bob');
        $repo->save($entity);

        // At creating time the entity had no id yet (not yet inserted)
        self::assertTrue($spy->idWasNullAtCreating);
        // After save the id has been assigned
        self::assertNotNull($entity->getAttribute('id'));
    }

    // ─── UPDATE ──────────────────────────────────────────────────────────────

    public function test_save_existing_entity_fires_updating_and_updated(): void
    {
        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Alice');
        $this->users->save($entity);

        // Reset spy after insert
        $this->spy->events = [];

        $entity->setAttribute('name', 'Alicia');
        $this->users->save($entity);

        self::assertSame(['updating', 'updated'], $this->spy->events);
    }

    // ─── DELETE ──────────────────────────────────────────────────────────────

    public function test_delete_fires_deleting_and_deleted(): void
    {
        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Charlie');
        $this->users->save($entity);

        $this->spy->events = [];

        $this->users->delete($entity);

        self::assertSame(['deleting', 'deleted'], $this->spy->events);
    }

    // ─── Multiple observers ──────────────────────────────────────────────────

    public function test_multiple_observers_all_receive_events(): void
    {
        $spy2 = new SpyObserver();
        $this->users->observe($spy2);

        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Dave');
        $this->users->save($entity);

        self::assertSame(['creating', 'created'], $this->spy->events);
        self::assertSame(['creating', 'created'], $spy2->events);
    }

    // ─── No observers ────────────────────────────────────────────────────────

    public function test_save_without_observer_does_not_throw(): void
    {
        $repo = new ObservableUserRepository($this->db, $this->hydrator);

        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Eve');
        $repo->save($entity);

        self::assertNotNull($entity->getAttribute('id'));
    }

    // ─── Full lifecycle ──────────────────────────────────────────────────────

    public function test_full_lifecycle_events_in_order(): void
    {
        $entity = new ObservableUserEntity();
        $entity->setAttribute('name', 'Frank');

        $this->users->save($entity);             // insert
        $entity->setAttribute('name', 'Francis');
        $this->users->save($entity);             // update
        $this->users->delete($entity);           // delete

        self::assertSame(
            ['creating', 'created', 'updating', 'updated', 'deleting', 'deleted'],
            $this->spy->events,
        );
    }
}
