<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Orm\Model;

/**
 * Class BelongsToMany
 *
 * @package EzPhp\Orm\Relations
 */
final class BelongsToMany extends Relation
{
    /**
     * @param DatabaseInterface    $db
     * @param string               $relatedTable
     * @param string               $pivotTable
     * @param string               $foreignKey      FK in pivot pointing to owning model
     * @param string               $relatedKey      FK in pivot pointing to related model
     * @param string               $localKey        PK on owning model
     * @param string               $relatedLocalKey PK on related model
     * @param class-string<Model>  $relatedClass
     * @param mixed                $localValue      Current owning model's PK value (for lazy load)
     */
    public function __construct(
        private readonly DatabaseInterface $db,
        private readonly string $relatedTable,
        private readonly string $pivotTable,
        private readonly string $foreignKey,
        private readonly string $relatedKey,
        private readonly string $localKey,
        private readonly string $relatedLocalKey,
        private readonly string $relatedClass,
        private readonly mixed $localValue,
    ) {
    }

    /**
     * @return array<Model>
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

        $class = $this->relatedClass;

        return array_map(static fn (array $row) => $class::fromRaw($row), $rows);
    }

    /**
     * @return list<Model>
     */
    public function get(): array
    {
        /** @var list<Model> */
        return $this->getResults();
    }

    /**
     * @return string
     */
    public function getOwnerKey(): string
    {
        return $this->localKey;
    }

    /**
     * @return class-string<Model>
     */
    public function getRelatedClass(): string
    {
        return $this->relatedClass;
    }

    /**
     * @param list<mixed> $ids
     *
     * @return list<Model>
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

        $class = $this->relatedClass;

        return array_map(static fn (array $row) => $class::fromRaw($row), $rows);
    }

    /**
     * @param list<Model> $models
     * @param list<Model> $results
     * @param string      $relation
     *
     * @return void
     */
    public function match(array $models, array $results, string $relation): void
    {
        foreach ($models as $model) {
            $key = $model->getAttribute($this->localKey);
            $matched = array_values(
                array_filter($results, static fn (Model $r) => $r->getAttribute('__pivot_fk') == $key)
            );
            $model->setRelation($relation, $matched);
        }
    }
}
