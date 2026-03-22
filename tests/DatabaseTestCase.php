<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\DatabaseTestCase as EzPhpDatabaseTestCase;

/**
 * Base class for tests that bootstrap the Application against a real database.
 *
 * Swaps DB_DATABASE to the testing database before bootstrapping, opens a
 * transaction on the underlying PDO connection, and rolls it back in tearDown().
 * The env-swap ensures DML operations land in the testing database; the
 * transaction rollback provides additional isolation for DML writes.
 *
 * Note: DDL statements (CREATE TABLE, DROP TABLE, etc.) cause an implicit
 * commit in MySQL, bypassing the transaction. Tests that issue DDL must clean
 * up manually (e.g. DROP TABLE IF EXISTS ...).
 *
 * @package Tests
 */
abstract class DatabaseTestCase extends EzPhpDatabaseTestCase
{
    private string $originalDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->originalDatabase = (string) getenv('DB_DATABASE');
        putenv('DB_DATABASE=' . (getenv('DB_TESTING_DATABASE') ?: 'ez-php_testing'));

        parent::setUp();
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        parent::tearDown();

        putenv('DB_DATABASE=' . $this->originalDatabase);
    }

    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        return dirname(__DIR__);
    }
}
