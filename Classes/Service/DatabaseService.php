<?php

declare(strict_types=1);

namespace Remind\Backup\Service;

use Doctrine\DBAL\Connection;
use Symfony\Component\Process\Process;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class DatabaseService
{
    private const CONNECTION_NAME = 'Default';

    /**
     * @var mixed[]
     */
    private array $connectionConfig;

    private Connection $connection;

    private ?string $myCnf = null;

    public function __construct()
    {
        $this->connectionConfig = $GLOBALS['TYPO3_CONF_VARS']['DB']['Connections'][self::CONNECTION_NAME];
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $this->connection = $connectionPool->getConnectionByName(self::CONNECTION_NAME);
    }

    public function mysql(mixed $input = null): Process
    {
        $command = ['mysql', ...$this->buildConnectionArguments()];
        $process = new Process($command, null, null, $input);
        $process->setTimeout(null);
        return $process;
    }

    /**
     * @param string[] $args
     */
    public function mysqldump(array $args = []): Process
    {
        $command = ['mysqldump', ...$this->buildConnectionArguments(), ...$args];
        $process = new Process($command);
        $process->setTimeout(null);
        return $process;
    }

    public function getDbName(): string
    {
        return $this->connectionConfig['dbname'];
    }

    /**
     * @return string[]
     */
    public function getTableNames(): array
    {
        return $this->connection->getSchemaManager()->listTableNames();
    }

    /**
     * @return string[]
     */
    private function buildConnectionArguments(): array
    {
        return [
            '--defaults-file=' . $this->createMyCnf(),
            '-h',
            $this->connectionConfig['host'],
            '-P',
            $this->connectionConfig['port'],
            $this->getDbName(),
        ];
    }

    private function createMyCnf(): string
    {
        $user = $this->connectionConfig['user'] ?? null;
        $password = $this->connectionConfig['password'] ?? null;

        if (
            $this->myCnf &&
            file_exists($this->myCnf)
        ) {
            return $this->myCnf;
        }

        $userDefinition = '';
        $passwordDefinition = '';

        if (!empty($user)) {
            $userDefinition = sprintf('user="%s"', addcslashes($user, '"\\'));
        }
        if (!empty($password)) {
            $passwordDefinition = sprintf('password="%s"', addcslashes($password, '"\\'));
        }

        $content = [
            '[mysqldump]',
            $userDefinition,
            $passwordDefinition,
            '[client]',
            $userDefinition,
            $passwordDefinition,
        ];

        $this->myCnf = tempnam(sys_get_temp_dir(), 'typo3_console_my_cnf_');
        file_put_contents($this->myCnf, implode(PHP_EOL, $content));
        register_shutdown_function('unlink', $this->myCnf);

        return $this->myCnf;
    }
}
