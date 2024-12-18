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

final class ExportCommand extends Command
{
    private const INPUT_DIR = 'dir';
    private const INPUT_FILE = 'file';
    private const INPUT_NO_DATA = 'no-data';
    private const INPUT_INCLUDE_CACHE_DATA = 'include-cache-data';
    private const INPUT_INCLUDE_DEFAULT_NO_DATA = 'include-default-no-data';
    private const INPUT_OMIT_TIMESTAMP = 'omit-timestamp';
    private const INPUT_NO_COMPRESSION = 'no-compression';
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
                [],
            )
            ->addOption(
                self::INPUT_INCLUDE_CACHE_DATA,
                null,
                InputOption::VALUE_NONE,
                'Include cache tables data'
            )
            ->addOption(
                self::INPUT_INCLUDE_DEFAULT_NO_DATA,
                null,
                InputOption::VALUE_NONE,
                sprintf('Include data for tables %s', implode(', ', self::DEFAULT_NO_DATA)),
            )
            ->addOption(
                self::INPUT_OMIT_TIMESTAMP,
                null,
                InputOption::VALUE_NONE,
                'Omit timestamp in filename'
            )
            ->addOption(
                self::INPUT_NO_COMPRESSION,
                null,
                InputOption::VALUE_NONE,
                'Do not compress the output file',
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (!(bool) $this->extensionConfiguration['export']['enable']) {
            $output->writeln(
                'Database export has to be enabled with [\'EXTENSIONS\'][\'rmnd_backup\'][\'export\'][\'enable\'] = 1'
            );
            return Command::SUCCESS;
        }

        $dir = $input->getOption(self::INPUT_DIR);

        $noCompression = $input->getOption(self::INPUT_NO_COMPRESSION);

        $path = FileNamingUtility::buildPath(
            $dir,
            $input->getOption(self::INPUT_FILE),
            !$input->getOption(self::INPUT_OMIT_TIMESTAMP),
            true,
            !$noCompression,
        );

        if (file_exists($path)) {
            $output->writeln(sprintf('File \'%s\' already exists', $path));
            return Command::FAILURE;
        }

        if (!file_exists($dir)) {
            mkdir($dir, 0755, true);
        }

        $tables = $this->databaseService->getTableNames();

        $cacheTables = array_filter($tables, function (string $table) {
            return str_starts_with($table, 'cache_');
        });

        $ignoreTables = $input->getOption(self::INPUT_NO_DATA);

        if (!$input->getOption(self::INPUT_INCLUDE_CACHE_DATA)) {
            array_push($ignoreTables, ...$cacheTables);
        }

        if (!$input->getOption(self::INPUT_INCLUDE_DEFAULT_NO_DATA)) {
            array_push($ignoreTables, ...self::DEFAULT_NO_DATA);
        }

        $ignoreTableArgs = array_map(function (string $table) {
            return sprintf('--ignore-table=%s.%s', $this->databaseService->getDbName(), $table);
        }, $ignoreTables);

        $processes = [
            $this->databaseService->mysqldump(['--single-transaction', '--no-data']),
            $this->databaseService->mysqldump(['--single-transaction', '--no-create-info', ...$ignoreTableArgs]),
        ];

        $file = $noCompression ? fopen($path, 'a') : gzopen($path, 'a');

        if ($file) {
            foreach ($processes as $process) {
                $exitCode = $process->run(function ($type, $buffer) use ($output, $file, $noCompression): void {
                    if ($type === Process::ERR) {
                        $output->writeln($buffer);
                    } else {
                        $noCompression ? fwrite($file, $buffer) : gzwrite($file, $buffer);
                    }
                });

                if ($exitCode !== 0) {
                    return Command::FAILURE;
                }
            }
            $noCompression ? fclose($file) : gzclose($file);
        }

        return Command::SUCCESS;
    }
}
