<?php

declare(strict_types=1);

namespace Remind\Backup\Command;

use Remind\Backup\Utility\FileNamingUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class DeleteBackupCommand extends Command
{
    private const INPUT_DIR = 'dir';
    private const INPUT_FILE = 'file';
    private const INPUT_KEEP_COUNT = 'keep-count';
    private const INPUT_COMPRESSION = 'compression';

    /**
     * @var mixed[]
     */
    private array $extensionConfiguration;

    public function __construct(
        ExtensionConfiguration $extensionConfiguration
    ) {
        $this->extensionConfiguration = $extensionConfiguration->get('rmnd_backup');
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                self::INPUT_DIR,
                'd',
                InputOption::VALUE_OPTIONAL,
                'Directory where backups are stored',
                $this->extensionConfiguration['defaultDir'],
            )
            ->addOption(
                self::INPUT_FILE,
                'f',
                InputOption::VALUE_OPTIONAL,
                'Filename of the backup without file extension',
                $this->extensionConfiguration['defaultFile'],
            )
            ->addOption(
                self::INPUT_KEEP_COUNT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of database backups to keep',
                $this->extensionConfiguration['delete']['keepCount']
            )
            ->addOption(
                self::INPUT_COMPRESSION,
                null,
                InputOption::VALUE_OPTIONAL,
                'Only delete compressed or uncompressed files',
                $this->extensionConfiguration['compression'],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $input->getOption(self::INPUT_DIR);
        $file = $input->getOption(self::INPUT_FILE);
        $compression = (bool) $input->getOption(self::INPUT_COMPRESSION);

        if (!is_dir($dir)) {
            $output->writeln(sprintf('Directory \'%s\' does not exist.', $dir));
            return Command::FAILURE;
        }

        $files = array_values(array_diff(scandir($dir, SCANDIR_SORT_ASCENDING) ?: [], ['.', '..']));
        $matches = array_filter($files, function (string $fileToCheck) use ($file, $compression) {
            return (bool) preg_match(FileNamingUtility::getRegexPattern($file, $compression), $fileToCheck);
        });

        $filesToBeDeleted = array_slice($matches, 0, -(int)$input->getOption(self::INPUT_KEEP_COUNT));

        foreach ($filesToBeDeleted as $file) {
            unlink(FileNamingUtility::buildPath($dir, $file));
        }

        return Command::SUCCESS;
    }
}
