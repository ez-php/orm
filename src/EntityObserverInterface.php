<?php

declare(strict_types=1);

namespace EzPhp\Orm;

/**
 * Interface EntityObserverInterface
 *
 * Implement to observe lifecycle events fired by ObservableRepositoryTrait.
 *
 * Each hook receives the entity instance at the moment the event fires.
 * The *ing hooks (creating, updating, deleting) run before the DB operation;
 * the *ed hooks (created, updated, deleted) run after.
 *
 * @package EzPhp\Orm
 */
interface EntityObserverInterface
{
    /**
     * Called before a new entity is inserted.
     *
     * @param object $entity
     *
     * @return void
     */
    public function creating(object $entity): void;

    /**
     * Called after a new entity has been inserted.
     *
     * @param object $entity
     *
     * @return void
     */
    public function created(object $entity): void;

    /**
     * Called before an existing entity is updated.
     *
     * @param object $entity
     *
     * @return void
     */
    public function updating(object $entity): void;

    /**
     * Called after an existing entity has been updated.
     *
     * @param object $entity
     *
     * @return void
     */
    public function updated(object $entity): void;

    /**
     * Called before an entity is deleted (hard or soft).
     *
     * @param object $entity
     *
     * @return void
     */
    public function deleting(object $entity): void;

    /**
     * Called after an entity has been deleted.
     *
     * @param object $entity
     *
     * @return void
     */
    public function deleted(object $entity): void;
}
