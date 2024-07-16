<?php

declare(strict_types=1);

namespace Remind\Backup\Command;

use Remind\Backup\Utility\FileNameUtility;
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
                '',
                $this->extensionConfiguration['defaultDir'],
            )
            ->addOption(
                self::INPUT_FILE,
                'f',
                InputOption::VALUE_OPTIONAL,
                '',
                $this->extensionConfiguration['defaultFile'],
            )
            ->addOption(
                self::INPUT_KEEP_COUNT,
                null,
                InputOption::VALUE_OPTIONAL,
                'Number of database backups to keep',
                $this->extensionConfiguration['delete']['keepCount']
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(bool) $this->extensionConfiguration['delete']['enable']) {
            $output->writeln(
                'Backup deletion has to be enabled with [\'EXTENSIONS\'][\'rmnd_backup\'][\'delete\'][\'enable\'] = 1'
            );
            return Command::SUCCESS;
        }

        $dir = $input->getOption(self::INPUT_DIR);
        $file = $input->getOption(self::INPUT_FILE);

        if (!is_dir($dir)) {
            $output->writeln(sprintf('Directory \'%s\' does not exist.', $dir));
            return Command::FAILURE;
        }

        $files = array_values(array_diff(scandir($dir, SCANDIR_SORT_ASCENDING), ['.', '..']));
        $matches = array_filter($files, function (string $fileToCheck) use ($file) {
            return preg_match(FileNameUtility::getRegexPattern($file), $fileToCheck);
        });

        $filesToBeDeleted = array_slice($matches, 0, -$input->getOption(self::INPUT_KEEP_COUNT));

        foreach ($filesToBeDeleted as $file) {
            unlink(FileNameUtility::buildPath($dir, $file));
        }

        return Command::SUCCESS;
    }
}
