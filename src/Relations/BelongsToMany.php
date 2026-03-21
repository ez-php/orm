<?php

declare(strict_types=1);

namespace EzPhp\Orm\Relations;

use EzPhp\Contracts\DatabaseInterface;
use EzPhp\Orm\Model;
use EzPhp\Orm\QueryBuilder;

/**
 * Class BelongsToMany
 *
 * @package EzPhp\Orm\Relations
 */
final class BelongsToMany extends Relation
{
    /**
     * Optional custom pivot model class.
     *
     * @var class-string<PivotModel>|null
     */
    private ?string $pivotClass = null;

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
     * Specify a custom pivot model class.
     *
     * @param class-string<PivotModel> $pivotClass
     *
     * @return self
     */
    public function using(string $pivotClass): self
    {
        $clone = clone $this;
        $clone->pivotClass = $pivotClass;

        return $clone;
    }

    /**
     * @return array<Model>
     */
    public function getResults(): array
    {
        if ($this->localValue === null) {
            return [];
        }

        if ($this->pivotClass !== null) {
            return $this->getResultsWithPivot();
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
     * For BelongsToMany, getForeignKey returns the FK in the pivot pointing to the owner.
     *
     * @return string
     */
    public function getForeignKey(): string
    {
        return $this->foreignKey;
    }

    /**
     * Return the local key (PK) on the owning model.
     *
     * @return string
     */
    public function getLocalKey(): string
    {
        return $this->localKey;
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

        if ($this->pivotClass !== null) {
            return $this->eagerLoadForWithPivot($ids, $placeholders);
        }

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

    /**
     * Return count of related models per owner-key value (counts pivot rows).
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

    /**
     * Load results including pivot model attributes.
     *
     * @return list<Model>
     */
    private function getResultsWithPivot(): array
    {
        $pivotClass = $this->pivotClass;
        /** @var class-string<PivotModel> $pivotClass */
        $fillable = $pivotClass::getFillable();
        $pivotSelects = array_map(fn (string $col) => "$this->pivotTable.$col AS __pivot_$col", $fillable);
        $selectList = "$this->relatedTable.*, " . implode(', ', $pivotSelects);

        $rows = $this->db->query(
            "SELECT $selectList"
            . " FROM $this->relatedTable"
            . " JOIN $this->pivotTable"
            . " ON $this->relatedTable.$this->relatedLocalKey = $this->pivotTable.$this->relatedKey"
            . " WHERE $this->pivotTable.$this->foreignKey = ?",
            [$this->localValue]
        );

        $class = $this->relatedClass;
        $result = [];

        foreach ($rows as $row) {
            $pivotAttrs = [];
            $relatedAttrs = [];

            foreach ($row as $key => $value) {
                if (str_starts_with($key, '__pivot_')) {
                    $pivotAttrs[substr($key, 8)] = $value;
                } else {
                    $relatedAttrs[$key] = $value;
                }
            }

            $model = $class::fromRaw($relatedAttrs);
            $pivot = $pivotClass::fromRaw($pivotAttrs);
            $model->setRelation('pivot', $pivot);
            $result[] = $model;
        }

        return $result;
    }

    /**
     * Eager-load results including pivot model attributes.
     *
     * @param list<mixed> $ids
     * @param string      $placeholders
     *
     * @return list<Model>
     */
    private function eagerLoadForWithPivot(array $ids, string $placeholders): array
    {
        $pivotClass = $this->pivotClass;
        /** @var class-string<PivotModel> $pivotClass */
        $fillable = $pivotClass::getFillable();
        $pivotSelects = array_map(fn (string $col) => "$this->pivotTable.$col AS __pivot_$col", $fillable);
        $selectList = "$this->relatedTable.*, $this->pivotTable.$this->foreignKey AS __pivot_fk, " . implode(', ', $pivotSelects);

        $rows = $this->db->query(
            "SELECT $selectList"
            . " FROM $this->relatedTable"
            . " JOIN $this->pivotTable"
            . " ON $this->relatedTable.$this->relatedLocalKey = $this->pivotTable.$this->relatedKey"
            . " WHERE $this->pivotTable.$this->foreignKey IN ($placeholders)",
            $ids
        );

        $class = $this->relatedClass;
        $result = [];

        foreach ($rows as $row) {
            $pivotAttrs = [];
            $relatedAttrs = [];

            foreach ($row as $key => $value) {
                if (str_starts_with($key, '__pivot_')) {
                    $pivotAttrs[substr($key, 8)] = $value;
                } else {
                    $relatedAttrs[$key] = $value;
                }
            }

            // Restore __pivot_fk for match()
            $relatedAttrs['__pivot_fk'] = $pivotAttrs['fk'] ?? null;

            $model = $class::fromRaw($relatedAttrs);
            $pivot = $pivotClass::fromRaw($pivotAttrs);
            $model->setRelation('pivot', $pivot);
            $result[] = $model;
        }

        return $result;
    }
}
