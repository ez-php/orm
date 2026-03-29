<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Trait ObservableRepositoryTrait
 *
 * Adds entity lifecycle event support to a concrete AbstractRepository subclass.
 *
 * Usage in a repository:
 *
 *   final class UserRepository extends AbstractRepository
 *   {
 *       use ObservableRepositoryTrait;
 *       ...
 *   }
 *
 *   $repo->observe(new AuditObserver());
 *
 * Observers are notified in the order they were registered.
 * The *ing hooks run before the DB operation; *ed hooks run after.
 *
 * Note: The creating/created hooks fire for INSERT operations;
 *       updating/updated hooks fire for UPDATE operations.
 *       The distinction is made by checking whether the entity's
 *       scalar primary key is null (null → insert, non-null → update).
 *       Composite-PK entities always use the updating/updated hooks.
 *
 * @template T of Entity
 * @phpstan-require-extends AbstractRepository<T>
 *
 * @package EzPhp\Orm
 */
trait ObservableRepositoryTrait
{
    /**
     * Registered observers.
     *
     * @var list<EntityObserverInterface>
     */
    private array $observers = [];

    /**
     * Register an observer for entity lifecycle events.
     *
     * @param EntityObserverInterface $observer
     *
     * @return void
     */
    public function observe(EntityObserverInterface $observer): void
    {
        $this->observers[] = $observer;
    }

    /**
     * Persist an entity, firing creating/created (insert) or updating/updated (update) hooks.
     *
     * @param T $entity
     *
     * @return void
     */
    public function save(object $entity): void
    {
        $entityClass = $this->entityClass();

        if ($entityClass::isPrimaryKeyComposite()) {
            $this->fireObservers('updating', $entity);
            parent::save($entity);
            $this->fireObservers('updated', $entity);

            return;
        }

        $pk = $entityClass::scalarPrimaryKey();
        $isNew = $entity->getAttribute($pk) === null;

        if ($isNew) {
            $this->fireObservers('creating', $entity);
            parent::save($entity);
            $this->fireObservers('created', $entity);
        } else {
            $this->fireObservers('updating', $entity);
            parent::save($entity);
            $this->fireObservers('updated', $entity);
        }
    }

    /**
     * Delete an entity, firing deleting/deleted hooks.
     *
     * @param T $entity
     *
     * @return void
     */
    public function delete(object $entity): void
    {
        $this->fireObservers('deleting', $entity);
        parent::delete($entity);
        $this->fireObservers('deleted', $entity);
    }

    /**
     * Fire the named lifecycle event on all registered observers.
     *
     * @param string $event
     * @param object $entity
     *
     * @return void
     */
    private function fireObservers(string $event, object $entity): void
    {
        foreach ($this->observers as $observer) {
            match ($event) {
                'creating' => $observer->creating($entity),
                'created' => $observer->created($entity),
                'updating' => $observer->updating($entity),
                'updated' => $observer->updated($entity),
                'deleting' => $observer->deleting($entity),
                'deleted' => $observer->deleted($entity),
                default => null,
            };
        }
    }
}
