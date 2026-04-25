<?php

declare(strict_types=1);

namespace EzPhp\Orm;

use LogicException;

/**
 * Class AppendOnlyRepository
 *
 * Base repository for insert-only tables (audit logs, event logs, immutable records).
 * Overrides save() and delete() to throw LogicException, making the append-only
 * constraint enforceable at the type level rather than by convention.
 *
 * Concrete repositories should only expose create() and read methods.
 *
 * @template T of Entity
 * @extends AbstractRepository<T>
 *
 * @package EzPhp\Orm
 */
abstract class AppendOnlyRepository extends AbstractRepository
{
    /**
     * @param T $entity
     *
     * @return never
     */
    public function save(object $entity): never
    {
        throw new LogicException(
            static::class . ' is append-only. Use create() to insert new records.'
        );
    }

    /**
     * @param T $entity
     *
     * @return never
     */
    public function delete(object $entity): never
    {
        throw new LogicException(
            static::class . ' is append-only. Records cannot be deleted.'
        );
    }

    /**
     * Insert a new entity and return it with the auto-generated primary key set.
     *
     * @param array<string, mixed> $attributes
     *
     * @return T
     */
    public function create(array $attributes): object
    {
        $entityClass = $this->entityClass();
        $entity = new $entityClass($attributes);

        $this->insert($entity);

        /** @var T */
        return $entity;
    }

    /**
     * @param T $entity
     *
     * @return void
     */
    private function insert(object $entity): void
    {
        $entityClass = $this->entityClass();
        $table = $entityClass::resolveTable();
        $pk = $entityClass::scalarPrimaryKey();

        $data = $this->hydrator->extract($entity);
        unset($data[$pk]);

        if ($data === []) {
            return;
        }

        $result = (new QueryBuilder($this->db, $table))->insert($data);

        if ($result) {
            $lastId = $this->db->getPdo()->lastInsertId();

            if ($lastId !== false && $lastId !== '') {
                $entity->setAttribute($pk, is_numeric($lastId) ? (int) $lastId : $lastId);
            }
        }
    }
}
