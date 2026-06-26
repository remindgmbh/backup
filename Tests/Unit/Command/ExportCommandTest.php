<?php

declare(strict_types=1);

namespace Remind\Backup\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Backup\Command\ExportCommand;
use Remind\Backup\Service\DatabaseService;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(ExportCommand::class)]
class ExportCommandTest extends UnitTestCase
{
    #[Test]
    public function configureRegistersAllRelevantOptions(): void
    {
        $command = $this->createExportCommand();
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('dir'));
        self::assertTrue($definition->hasOption('file'));
        self::assertTrue($definition->hasOption('no-data'));
        self::assertTrue($definition->hasOption('include-cache-data'));
        self::assertTrue($definition->hasOption('include-default-no-data'));
        self::assertTrue($definition->hasOption('timestamp'));
        self::assertTrue($definition->hasOption('compression'));
    }

    #[Test]
    public function configureUsesDefaultsFromExtensionConfiguration(): void
    {
        $command = $this->createExportCommand();
        $definition = $command->getDefinition();

        self::assertSame('/configured/backup-dir/', $definition->getOption('dir')->getDefault());
        self::assertSame('configured-file', $definition->getOption('file')->getDefault());
        self::assertSame(['foo_table', 'bar_table'], $definition->getOption('no-data')->getDefault());
        self::assertTrue((bool) $definition->getOption('include-cache-data')->getDefault());
        self::assertFalse((bool) $definition->getOption('include-default-no-data')->getDefault());
        self::assertTrue((bool) $definition->getOption('timestamp')->getDefault());
        self::assertTrue((bool) $definition->getOption('compression')->getDefault());
    }

    private function createExportCommand(): ExportCommand
    {
        $databaseService = $this->createMock(DatabaseService::class);

        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->with('rmnd_backup')
            ->willReturn([
                'compression' => true,
                'defaultDir' => '/configured/backup-dir/',
                'defaultFile' => 'configured-file',
                'export' => [
                    'includeCacheData' => true,
                    'includeDefaultNoData' => false,
                    'noData' => 'foo_table, bar_table',
                    'timestamp' => true,
                ],
            ]);

        return new ExportCommand($databaseService, $extensionConfiguration);
    }
}
