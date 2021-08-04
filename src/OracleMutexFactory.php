<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\File;

use PDO;
use Yiisoft\Mutex\MutexFactory;
use Yiisoft\Mutex\MutexInterface;
use Yiisoft\Mutex\OracleMutex;

/**
 * Allows creating {@see OracleMutex} mutex objects.
 */
final class OracleMutexFactory extends MutexFactory
{
    private PDO $connection;
    private bool $autoRelease;
    private string $lockMode;
    private bool $releaseOnCommit;

    /**
     * @param PDO $connection PDO connection instance to use.
     * @param bool $autoRelease Whether to automatically release lock when PHP script ends.
     */
    public function __construct(PDO $connection, string $lockMode = OracleMutex::MODE_X, bool $releaseOnCommit = false, bool $autoRelease = true)
    {
        $this->connection = $connection;
        $this->lockMode = $lockMode;
        $this->releaseOnCommit = $releaseOnCommit;
        $this->autoRelease = $autoRelease;
    }

    public function create(string $name): MutexInterface
    {
        return new OracleMutex($name, $this->connection, $this->lockMode, $this->releaseOnCommit, $this->autoRelease);
    }
}
