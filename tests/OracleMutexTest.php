<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Tests;

use PDO;
use Yiisoft\Mutex\MutexInterface;
use Yiisoft\Mutex\MysqlMutex;

final class OracleMutexTest
{
    use MutexTestTrait;

    protected function createMutex(): MutexInterface
    {
        return new MysqlMutex($this->getConnection());
    }

    private function getConnection(): PDO
    {
        // TODO: create MySQL connection here
    }
}
