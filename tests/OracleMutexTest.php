<?php
namespace Yiisoft\Mutex\Tests;

use Yiisoft\Mutex\MysqlMutex;

/**
 * Class MysqlMutexTest.
 *
 * @group mutex
 * @group db
 * @group mysql
 */
class OracleMutexTest
{
    use MutexTestTrait;

    /**
     * @return MysqlMutex
     */
    protected function createMutex()
    {
        return new MysqlMutex($this->getConnection());
    }

    private function getConnection()
    {
        // TODO: create MySQL connection here
    }
}
