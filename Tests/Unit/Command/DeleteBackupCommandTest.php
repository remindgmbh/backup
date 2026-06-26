<?php

declare(strict_types=1);

namespace Remind\Backup\Tests\Unit\Command;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Remind\Backup\Command\DeleteBackupCommand;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

#[CoversClass(DeleteBackupCommand::class)]
class DeleteBackupCommandTest extends UnitTestCase
{
    #[Test]
    public function configureRegistersAllRelevantOptions(): void
    {
        $command = $this->createDeleteCommand();
        $definition = $command->getDefinition();

        self::assertTrue($definition->hasOption('dir'));
        self::assertTrue($definition->hasOption('file'));
        self::assertTrue($definition->hasOption('keep-count'));
        self::assertTrue($definition->hasOption('compression'));
    }

    #[Test]
    public function configureUsesDefaultsFromExtensionConfiguration(): void
    {
        $command = $this->createDeleteCommand();
        $definition = $command->getDefinition();

        self::assertSame('/configured/delete-dir/', $definition->getOption('dir')->getDefault());
        self::assertSame('delete-file', $definition->getOption('file')->getDefault());
        self::assertSame(7, $definition->getOption('keep-count')->getDefault());
        self::assertTrue((bool) $definition->getOption('compression')->getDefault());
    }

    private function createDeleteCommand(): DeleteBackupCommand
    {
        $extensionConfiguration = $this->createMock(ExtensionConfiguration::class);
        $extensionConfiguration
            ->method('get')
            ->with('rmnd_backup')
            ->willReturn([
                'compression' => true,
                'defaultDir' => '/configured/delete-dir/',
                'defaultFile' => 'delete-file',
                'delete' => [
                    'keepCount' => 7,
                ],
            ]);

        return new DeleteBackupCommand($extensionConfiguration);
    }
}
