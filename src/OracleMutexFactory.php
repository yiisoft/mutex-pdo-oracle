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
    /**
     * @param PDO $connection PDO connection instance to use.
     * @param string $lockMode Lock mode to be used.
     * @param bool $releaseOnCommit Whether to release lock on commit.
     */
    public function __construct(private PDO $connection, private string $lockMode = OracleMutex::MODE_X, private bool $releaseOnCommit = false)
    {
    }

    public function create(string $name): MutexInterface
    {
        return new OracleMutex($name, $this->connection, $this->lockMode, $this->releaseOnCommit);
    }
}
