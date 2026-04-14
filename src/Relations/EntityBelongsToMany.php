<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Orm\AbstractRepository;
use EzPhp\Orm\Entity;
use EzPhp\Orm\QueryBuilder;

/**
 * Class EntityBelongsToMany
 *
 * Many-to-many relation via pivot table for the Data Mapper pattern.
 * Issues raw SQL directly against the DatabaseInterface because the join spans
 * the pivot table and cannot be expressed via a single-table QueryBuilder.
 *
 * The '__pivot_fk' alias is an implementation detail used by match() to group
 * eager-loaded results back onto their owning entities.
 *
 * @template TRelated of Entity
 *
 * @package EzPhp\Orm\Relations
 */
final class EntityBelongsToMany extends EntityRelation
{
    /**
     * @param DatabaseInterface            $db
     * @param AbstractRepository<TRelated> $relatedRepo      Repository managing the related entity
     * @param string             $relatedTable     Table of the related entity
     * @param string             $pivotTable       Pivot table name
     * @param string             $foreignKey       FK in pivot pointing to owning entity
     * @param string             $relatedKey       FK in pivot pointing to related entity
     * @param string             $localKey         PK on owning entity
     * @param string             $relatedLocalKey  PK on related entity
     * @param mixed              $localValue       Owning entity's PK value (for lazy load)
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly AbstractRepository $relatedRepo,
        private readonly string $relatedTable,
        private readonly string $pivotTable,
        private readonly string $foreignKey,
        private readonly string $relatedKey,
        private readonly string $localKey,
        private readonly string $relatedLocalKey,
        private readonly mixed $localValue,
    ) {
    }

    /**
     * Lazy-load all related entities for the owning entity.
     *
     * @return list<Entity>
     */
    public function getResults(): array
    {
        if ($this->localValue === null) {
            return [];
        }

        $rows = $this->db->query(
            "SELECT $this->relatedTable.*"
            . " FROM $this->relatedTable"
            . " JOIN $this->pivotTable"
            . " ON $this->relatedTable.$this->relatedLocalKey = $this->pivotTable.$this->relatedKey"
            . " WHERE $this->pivotTable.$this->foreignKey = ?",
            [$this->localValue]
        );

        return array_map(fn (array $row) => $this->relatedRepo->hydrateOne($row), $rows);
    }

    /**
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->localKey;
    }

    /**
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<Entity>
     */
    public function eagerLoadFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $placeholders = implode(', ', array_fill(0, count($ids), '?'));

        $rows = $this->db->query(
            "SELECT $this->relatedTable.*, $this->pivotTable.$this->foreignKey AS __pivot_fk"
            . " FROM $this->relatedTable"
            . " JOIN $this->pivotTable"
            . " ON $this->relatedTable.$this->relatedLocalKey = $this->pivotTable.$this->relatedKey"
            . " WHERE $this->pivotTable.$this->foreignKey IN ($placeholders)",
            $ids
        );

        return array_map(fn (array $row) => $this->relatedRepo->hydrateOne($row), $rows);
    }

    /**
     * @param list<Entity> $entities
     * @param list<Entity> $results
     * @param string       $relation
     *
     * @return void
     */
    public function match(array $entities, array $results, string $relation): void
    {
        foreach ($entities as $entity) {
            $key = $entity->getAttribute($this->localKey);
            $matched = array_values(
                array_filter($results, static fn (Entity $r) => $r->getAttribute('__pivot_fk') == $key)
            );
            $entity->setRelation($relation, $matched);
        }
    }

    /**
     * Return count of related entities per owner-key value (counts pivot rows).
     *
     * @param list<mixed> $ownerIds
     *
     * @return array<mixed, int>
     */
    public function countFor(array $ownerIds): array
    {
        if ($ownerIds === []) {
            return [];
        }

        $rows = (new QueryBuilder($this->db, $this->pivotTable))
            ->select($this->foreignKey, 'COUNT(*) as count')
            ->whereIn($this->foreignKey, $ownerIds)
            ->groupBy($this->foreignKey)
            ->get();

        $result = [];
        foreach ($rows as $row) {
            $keyVal = $row[$this->foreignKey];
            if (!is_int($keyVal) && !is_string($keyVal)) {
                continue;
            }

            $countVal = $row['count'];
            $result[$keyVal] = is_numeric($countVal) ? (int) $countVal : 0;
        }

        return $result;
    }
}
