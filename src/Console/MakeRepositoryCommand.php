<?php

declare(strict_types=1);

namespace EzPhp\Orm\Console;

use EzPhp\Console\CommandInterface;

/**
 * Class MakeRepositoryCommand
 *
 * Scaffolds a new Data Mapper repository class in app/Repositories/.
 *
 * Usage: ez make:repository <EntityName>
 *
 * Given "User", generates UserRepository extends AbstractRepository<User>
 * referencing App\Entities\User as the managed entity class.
 *
 * @package EzPhp\Orm\Console
 */
final readonly class MakeRepositoryCommand implements CommandInterface
{
    /**
     * @param string $srcPath  Absolute path to the application src/ directory
     */
    public function __construct(private string $srcPath)
    {
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return 'make:repository';
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return 'Create a new Data Mapper repository class';
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return 'Usage: ez make:repository <EntityName>';
    }

    /**
     * @param list<string> $args
     *
     * @return int
     */
    public function handle(array $args): int
    {
        $entityName = $args[0] ?? null;

        if ($entityName === null || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/', $entityName)) {
            fwrite(STDERR, "Usage: ez make:repository <EntityName>\n");

            return 1;
        }

        $repoName = $entityName . 'Repository';
        $dir = $this->srcPath . DIRECTORY_SEPARATOR . 'Repositories';
        $filename = "$repoName.php";
        $fullPath = $dir . DIRECTORY_SEPARATOR . $filename;

        if (!is_dir($dir)) {
            mkdir($dir, 0o755, true);
        }

        if (file_exists($fullPath)) {
            fwrite(STDERR, "Repository already exists: $filename\n");

            return 1;
        }

        if (file_put_contents($fullPath, $this->stub($entityName, $repoName)) === false) {
            fwrite(STDERR, "Failed to create repository: $filename\n");

            return 1;
        }

        echo "Created: src/Repositories/$filename\n";

        return 0;
    }

    /**
     * @param string $entityName  e.g. "User"
     * @param string $repoName    e.g. "UserRepository"
     *
     * @return string
     */
    private function stub(string $entityName, string $repoName): string
    {
        return <<<PHP
            <?php

            declare(strict_types=1);

            namespace App\\Repositories;

            use App\\Entities\\$entityName;
            use EzPhp\\Orm\\AbstractRepository;

            /**
             * @extends AbstractRepository<$entityName>
             */
            class $repoName extends AbstractRepository
            {
                protected function entityClass(): string
                {
                    return $entityName::class;
                }
            }
            PHP;
    }
}
