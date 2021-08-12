<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Oracle;

use PDO;
use Yiisoft\Mutex\MutexFactory;
use Yiisoft\Mutex\MutexInterface;

/**
 * Allows creating {@see OracleMutex} mutex objects.
 */
final class OracleMutexFactory extends MutexFactory
{
    private PDO $connection;
    private string $lockMode;
    private bool $releaseOnCommit;

    /**
     * @param PDO $connection PDO connection instance to use.
     * @param string $lockMode Lock mode to be used.
     * @param bool $releaseOnCommit Whether to release lock on commit.
     */
    public function __construct(PDO $connection, string $lockMode = OracleMutex::MODE_X, bool $releaseOnCommit = false)
    {
        $this->connection = $connection;
        $this->lockMode = $lockMode;
        $this->releaseOnCommit = $releaseOnCommit;
    }

    public function create(string $name): MutexInterface
    {
        return new OracleMutex($name, $this->connection, $this->lockMode, $this->releaseOnCommit);
    }
}
