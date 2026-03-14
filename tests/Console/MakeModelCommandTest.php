<?php

declare(strict_types=1);

namespace Tests\Console;

use EzPhp\Orm\Console\MakeModelCommand;
use Tests\TestCase;

/**
 * Class MakeModelCommandTest
 *
 * @package Tests\Console\Command
 */

final class MakeModelCommandTest extends TestCase
{
    private string $srcPath;

    /**
     * @return void
     */
    protected function setUp(): void
    {
        $this->srcPath = sys_get_temp_dir() . '/ez-php-make-model-' . uniqid();
        mkdir($this->srcPath);
    }

    /**
     * @return void
     */
    protected function tearDown(): void
    {
        $modelsDir = $this->srcPath . '/Models';
        if (is_dir($modelsDir)) {
            foreach (glob($modelsDir . '/*.php') ?: [] as $file) {
                unlink($file);
            }
            rmdir($modelsDir);
        }
        rmdir($this->srcPath);
    }

    /**
     * @return void
     */
    public function test_name_and_description(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        $this->assertSame('make:model', $command->getName());
        $this->assertNotEmpty($command->getDescription());
    }

    /**
     * @return void
     */
    public function test_creates_model_file(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['User']);
        ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertFileExists($this->srcPath . '/Models/User.php');
    }

    /**
     * @return void
     */
    public function test_creates_models_directory_if_not_exists(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        $this->assertDirectoryDoesNotExist($this->srcPath . '/Models');

        ob_start();
        $command->handle(['Post']);
        ob_get_clean();

        $this->assertDirectoryExists($this->srcPath . '/Models');
    }

    /**
     * @return void
     */
    public function test_generated_file_contains_class_name(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $command->handle(['Order']);
        ob_get_clean();

        $content = file_get_contents($this->srcPath . '/Models/Order.php');
        $this->assertIsString($content);
        $this->assertStringContainsString('class Order', $content);
        $this->assertStringContainsString('extends Model', $content);
    }

    /**
     * @return void
     */
    public function test_prints_created_message(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['Invoice']);
        $output = (string) ob_get_clean();

        $this->assertSame(0, $code);
        $this->assertStringContainsString('Created:', $output);
        $this->assertStringContainsString('Invoice.php', $output);
    }

    /**
     * @return void
     */
    public function test_returns_1_without_name_argument(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $command->handle([]);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_returns_1_for_invalid_class_name(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $code = $command->handle(['123Invalid']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }

    /**
     * @return void
     */
    public function test_returns_1_if_model_already_exists(): void
    {
        $command = new MakeModelCommand($this->srcPath);

        ob_start();
        $command->handle(['Product']);
        $code = $command->handle(['Product']);
        ob_get_clean();

        $this->assertSame(1, $code);
    }
}
