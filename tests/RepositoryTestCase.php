<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Orm\Entity;
use EzPhp\Orm\Hydrator;

/**
 * Base test case for Data Mapper repository tests.
 *
 * Provides a fresh in-memory SQLite database and a Hydrator for every test method.
 * Concrete repository instances are created in subclass setUp or test methods.
 *
 * Usage:
 *   1. Extend RepositoryTestCase instead of TestCase.
 *   2. Override setUpDatabase() to create tables / seed data.
 *   3. Instantiate concrete repositories with $this->db and $this->hydrator.
 *
 * @package Tests
 */
abstract class RepositoryTestCase extends TestCase
{
    protected PdoDatabase $db;

    protected Hydrator $hydrator;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new PdoDatabase('sqlite::memory:');
        $this->hydrator = new Hydrator();

        $this->setUpDatabase();
    }

    /**
     * Override to create tables and seed fixtures before each test.
     *
     * @return void
     */
    protected function setUpDatabase(): void
    {
    }

    /**
     * Helper to execute raw SQL (DDL or DML) against the test database.
     *
     * @param string $sql
     *
     * @return void
     */
    protected function exec(string $sql): void
    {
        $this->db->getPdo()->exec($sql);
    }

    /**
     * Extract an integer attribute from an entity.
     *
     * Asserts the value is int or string before casting so PHPStan can verify
     * the conversion is safe.
     *
     * @param Entity $entity
     * @param string $key
     *
     * @return int
     */
    protected function intAttr(Entity $entity, string $key = 'id'): int
    {
        $value = $entity->getAttribute($key);
        assert(is_int($value) || is_string($value));

        return (int) $value;
    }
}
