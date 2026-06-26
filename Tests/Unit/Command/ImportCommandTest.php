<?php

declare(strict_types=1);

namespace Remind\Backup\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Backup\Command\ImportCommand;
use Remind\Backup\Service\DatabaseService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ImportCommand::class)]
class ImportCommandTest extends UnitTestCase
{
    #[Test]
    public function configureRegistersAllRelevantOptions(): void
    {
        $command = $this->createImportCommand();
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('dir'));
        self::assertTrue($definition->hasOption('file'));
        self::assertTrue($definition->hasOption('compression'));
    }

    #[Test]
    public function configureUsesDefaultsFromExtensionConfiguration(): void
    {
        $command = $this->createImportCommand();
        $definition = $command->getDefinition();

        self::assertSame('/configured/import-dir/', $definition->getOption('dir')->getDefault());
        self::assertSame('import-file', $definition->getOption('file')->getDefault());
        self::assertFalse((bool) $definition->getOption('compression')->getDefault());
    }

    private function createImportCommand(): ImportCommand
    {
        $databaseService = $this->createMock(DatabaseService::class);

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->with('rmnd_backup')
            ->willReturn([
                'compression' => false,
                'defaultDir' => '/configured/import-dir/',
                'defaultFile' => 'import-file',
            ]);

        return new ImportCommand($databaseService, $extensionConfiguration);
    }
}
