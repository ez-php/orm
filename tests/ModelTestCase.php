<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Database\Database;
use EzPhp\Orm\Model;

/**
 * Base test case for ORM model tests.
 *
 * Provides a fresh in-memory SQLite database for every test method and wires it
 * into Model::setDatabase(), ensuring static state cannot leak between tests even
 * when tests run in parallel or in arbitrary order.
 *
 * Usage:
 *   1. Extend ModelTestCase instead of TestCase.
 *   2. Override setUpDatabase() to create tables / seed data.
 *   3. Use $this->db directly for raw queries; Model::* for ORM operations.
 *
 * @package Tests
 */
abstract class ModelTestCase extends TestCase
{
    protected Database $db;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->db = new Database('sqlite::memory:', '', '');
        Model::setDatabase($this->db);

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
     * @return void
     */
    protected function tearDown(): void
    {
        Model::resetDatabase();

        parent::tearDown();
    }
}
