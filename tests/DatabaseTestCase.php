<?php

declare(strict_types=1);

namespace Tests;

/**
 * Base class for tests that bootstrap the Application against a real database.
 * Swaps DB_DATABASE to the testing database for the duration of each test.
 *
 * @package Tests
 */
abstract class DatabaseTestCase extends TestCase
{
    private string $originalDatabase;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->originalDatabase = (string) getenv('DB_DATABASE');
        putenv('DB_DATABASE=' . (getenv('DB_TESTING_DATABASE') ?: 'ez-php_testing'));
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        putenv('DB_DATABASE=' . $this->originalDatabase);

        parent::tearDown();
    }
}
