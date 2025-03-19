<?php

declare(strict_types=1);

namespace Remind\Backup\Command;

use Remind\Backup\Service\DatabaseService;
use Remind\Backup\Utility\FileNamingUtility;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Utility\GeneralUtility;

final class ExportCommand extends Command
{
    private const INPUT_DIR = 'dir';
    private const INPUT_FILE = 'file';
    private const INPUT_NO_DATA = 'no-data';
    private const INPUT_INCLUDE_CACHE_DATA = 'include-cache-data';
    private const INPUT_INCLUDE_DEFAULT_NO_DATA = 'include-default-no-data';
    private const INPUT_TIMESTAMP = 'timestamp';
    private const INPUT_COMPRESSION = 'compression';
    private const DEFAULT_NO_DATA = [
        'be_sessions',
        'fe_sessions',
        'fe_users',
        'sys_file_processedfile',
        'sys_history',
        'sys_http_report',
        'sys_lockedrecords',
        'sys_log',
    ];

    /**
     * @var mixed[]
     */
    private array $extensionConfiguration;

    public function __construct(
        private readonly DatabaseService $databaseService,
        ExtensionConfiguration $extensionConfiguration,
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
                self::INPUT_NO_DATA,
                null,
                InputOption::VALUE_OPTIONAL | InputOption::VALUE_IS_ARRAY,
                'Table with data excluded',
                GeneralUtility::trimExplode(',', $this->extensionConfiguration['export']['noData']),
            )
            ->addOption(
                self::INPUT_INCLUDE_CACHE_DATA,
                null,
                InputOption::VALUE_OPTIONAL,
                'Include cache tables data',
                $this->extensionConfiguration['export']['includeCacheData']
            )
            ->addOption(
                self::INPUT_INCLUDE_DEFAULT_NO_DATA,
                null,
                InputOption::VALUE_OPTIONAL,
                sprintf('Include data for tables %s', implode(', ', self::DEFAULT_NO_DATA)),
                $this->extensionConfiguration['export']['includeDefaultNoData'],
            )
            ->addOption(
                self::INPUT_TIMESTAMP,
                null,
                InputOption::VALUE_OPTIONAL,
                'Include timestamp in filename',
                $this->extensionConfiguration['export']['timestamp'],
            )
            ->addOption(
                self::INPUT_COMPRESSION,
                null,
                InputOption::VALUE_OPTIONAL,
                'Compress the output file',
                $this->extensionConfiguration['compression'],
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dir = $input->getOption(self::INPUT_DIR);

        $compression = (bool) $input->getOption(self::INPUT_COMPRESSION);
        $ignoreTables = $input->getOption(self::INPUT_NO_DATA);
        $includeCacheData = (bool) $input->getOption(self::INPUT_INCLUDE_CACHE_DATA);
        $includeDefaultNoData = (bool) $input->getOption(self::INPUT_INCLUDE_DEFAULT_NO_DATA);
        $timestamp = (bool) $input->getOption(self::INPUT_TIMESTAMP);

        $path = FileNamingUtility::buildPath(
            $dir,
            $input->getOption(self::INPUT_FILE),
            $timestamp,
            true,
            $compression,
        );

        if (file_exists($path)) {
            $output->writeln(sprintf('File \'%s\' already exists', $path));
            return Command::FAILURE;
        }

        $output->writeln(sprintf('Export backup to \'%s\'', $path));

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $tables = $this->databaseService->getTableNames();

        $cacheTables = array_filter($tables, function (string $table) {
            return str_starts_with($table, 'cache_');
        });

        if (!$includeCacheData) {
            array_push($ignoreTables, ...$cacheTables);
        }

        if (!$includeDefaultNoData) {
            array_push($ignoreTables, ...self::DEFAULT_NO_DATA);
        }

        $ignoreTableArgs = array_map(function (string $table) {
            return sprintf('--ignore-table=%s.%s', $this->databaseService->getDbName(), $table);
        }, $ignoreTables);

        $processes = [
            $this->databaseService->mysqldump(['--single-transaction', '--no-data']),
            $this->databaseService->mysqldump(['--single-transaction', '--no-create-info', ...$ignoreTableArgs]),
        ];

        $file = $compression ? gzopen($path, 'a') : fopen($path, 'a');

        $tmpPath = tempnam(sys_get_temp_dir(), '');

        $file = $compression ? gzopen($tmpPath, 'a') : fopen($tmpPath, 'a');

        if ($file) {
            foreach ($processes as $process) {
                $exitCode = $process->run(function ($type, $buffer) use ($output, $file, $compression): void {
                    if ($type === Process::ERR) {
                        $output->writeln($buffer);
                    } else {
                        $compression ? gzwrite($file, $buffer) : fwrite($file, $buffer);
                    }
                });

                if ($exitCode !== 0) {
                    return Command::FAILURE;
                }
            }
            $compression ? gzclose($file) : fclose($file);
        }

        if (!rename($tmpPath, $path)) {
            $output->writeln(sprintf('Failed to move file from \'%s\' to \'%s\'', $tmpPath, $path));
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
