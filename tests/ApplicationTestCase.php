<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Testing\ApplicationTestCase as EzPhpApplicationTestCase;
use RuntimeException;

/**
 * Base class for ORM module tests that need a bootstrapped Application.
 *
 * Creates a temporary application root with a config/db.php that configures
 * an in-memory SQLite database. This satisfies DatabaseServiceProvider without
 * requiring a live MySQL instance, and keeps all service bindings lazy.
 *
 * Override configureApplication() to register providers or bind services
 * before bootstrap.
 *
 * @package Tests
 */
abstract class ApplicationTestCase extends EzPhpApplicationTestCase
{
    /**
     * @return string
     */
    protected function getBasePath(): string
    {
        $path = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'ez-orm-test-' . uniqid('', true);
        $configDir = $path . DIRECTORY_SEPARATOR . 'config';

        mkdir($configDir, 0o777, true);

        $content = <<<'PHP'
            <?php

            declare(strict_types=1);

            return [
                'driver'   => 'sqlite',
                'database' => ':memory:',
            ];
            PHP;

        $result = file_put_contents($configDir . DIRECTORY_SEPARATOR . 'db.php', $content);

        if ($result === false) {
            throw new RuntimeException('Failed to write db.php for test at ' . $configDir);
        }

        return $path;
    }
}
