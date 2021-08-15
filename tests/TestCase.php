<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Oracle\Tests;

use PDO;
use ReflectionClass;
use Yiisoft\Mutex\Oracle\OracleMutex;

use function md5;

abstract class TestCase extends \PHPUnit\Framework\TestCase
{
    private ?PDO $connection = null;

    protected function tearDown(): void
    {
        $this->connection = null;

        parent::setUp();
    }

    protected function connection(): PDO
    {
        if ($this->connection === null) {
            $this->connection = new PDO(
                'oci:dbname=localhost:1521/XE',
                'system',
                'oracle',
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
            );
        }

        return $this->connection;
    }

    protected function isFreeLock(OracleMutex $mutex, string $name): bool
    {
        $locks = (new ReflectionClass($mutex))->getParentClass()->getStaticPropertyValue('currentProcessLocks');

        return !isset($locks[md5(OracleMutex::class . $name)]);
    }
}
