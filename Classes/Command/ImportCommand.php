<?php

declare(strict_types=1);

namespace Remind\Backup\Command;

use Remind\Backup\Service\DatabaseService;
use Remind\Backup\Utility\FileNameUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;

final class ImportCommand extends Command
{
    private const INPUT_DIR = 'dir';
    private const INPUT_FILE = 'file';

    private array $extensionConfiguration;

    public function __construct(
        private readonly DatabaseService $databaseService,
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
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(bool) $this->extensionConfiguration['import']['enable']) {
            $output->writeln(
                'Database import has to be enabled with [\'EXTENSIONS\'][\'rmnd_backup\'][\'import\'][\'enable\'] = 1'
            );
            return Command::SUCCESS;
        }

        $dir = $input->getOption(self::INPUT_DIR);
        $file = $input->getOption(self::INPUT_FILE);

        $path = FileNameUtility::buildPath($dir, $file, false, true);

        if (!file_exists($path)) {
            if (!is_dir($dir)) {
                $output->writeln(sprintf('Directory \'%s\' does not exist.', $dir));
                return Command::FAILURE;
            }

            $files = array_values(array_diff(scandir($dir, SCANDIR_SORT_ASCENDING), ['.', '..']));
            $matches = array_filter($files, function (string $fileToCheck) use ($file) {
                return preg_match(FileNameUtility::getRegexPattern($file), $fileToCheck);
            });
            if (!empty($matches)) {
                $path = FileNameUtility::buildPath($dir, array_pop($matches));
                $output->writeln(sprintf('Use \'%s\' for import.', $path));
            } else {
                $output->writeln(sprintf('File \'%s\' does not exist.', $path));
                return Command::FAILURE;
            }
        }

        $stream = fopen($path, 'r');

        $process = $this->databaseService->mysql($stream);

        $exitCode = $process->run();

        if ($exitCode > 0) {
            $output->writeln($process->getErrorOutput());
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
