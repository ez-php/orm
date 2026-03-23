<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Orm\Console\MakeEntityCommand;
use Tests\TestCase;

/**
 * Class MakeEntityCommandTest
 *
 * @package Tests\Console
 */
final class MakeEntityCommandTest extends TestCase
{
    private string $srcPath;

    protected function setUp(): void
    {
        $this->srcPath = sys_get_temp_dir() . '/ez-php-make-entity-' . uniqid();
        mkdir($this->srcPath);
    }

    protected function tearDown(): void
    {
        $dir = $this->srcPath . '/Entities';
        if (is_dir($dir)) {
            foreach (glob($dir . '/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($dir);
        }
        rmdir($this->srcPath);
    }

    public function testNameAndDescription(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        self::assertSame('make:entity', $command->getName());
        self::assertNotEmpty($command->getDescription());
    }

    public function testCreatesEntityFile(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['User']);
        ob_get_clean();

        self::assertSame(0, $code);
        self::assertFileExists($this->srcPath . '/Entities/User.php');
    }

    public function testCreatesEntitiesDirectoryIfNotExists(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        self::assertDirectoryDoesNotExist($this->srcPath . '/Entities');

        ob_start();
        $command->handle(['Post']);
        ob_get_clean();

        self::assertDirectoryExists($this->srcPath . '/Entities');
    }

    public function testGeneratedFileContainsClassName(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $command->handle(['Order']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Entities/Order.php');
        self::assertIsString($content);
        self::assertStringContainsString('class Order', $content);
        self::assertStringContainsString('extends Entity', $content);
    }

    public function testGeneratedFileContainsEntityNamespace(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $command->handle(['Product']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Entities/Product.php');
        self::assertIsString($content);
        self::assertStringContainsString('namespace App\\Entities', $content);
        self::assertStringContainsString('use EzPhp\\Orm\\Entity', $content);
    }

    public function testPrintsCreatedMessage(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['Invoice']);
        $output = (string) ob_get_clean();

        self::assertSame(0, $code);
        self::assertStringContainsString('Created:', $output);
        self::assertStringContainsString('Invoice.php', $output);
    }

    public function testReturns1WithoutNameArgument(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        self::assertSame(1, $code);
    }

    public function testReturns1ForInvalidClassName(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['123Invalid']);
        ob_get_clean();

        self::assertSame(1, $code);
    }

    public function testReturns1IfEntityAlreadyExists(): void
    {
        $command = new MakeEntityCommand($this->srcPath);

        ob_start();
        $command->handle(['Tag']);
        $code = $command->handle(['Tag']);
        ob_get_clean();

        self::assertSame(1, $code);
    }
}
