<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Oracle\Tests;

use Yiisoft\Mutex\MutexInterface;
use Yiisoft\Mutex\Oracle\OracleMutex;
use Yiisoft\Mutex\Oracle\OracleMutexFactory;

final class OracleMutexFactoryTest extends TestCase
{
    public function testCreateAndAcquire(): void
    {
        $mutexName = 'testCreateAndAcquire';
        $factory = new OracleMutexFactory($this->connection());
        $mutex = $factory->createAndAcquire($mutexName);

        $this->assertInstanceOf(MutexInterface::class, $mutex);
        $this->assertInstanceOf(OracleMutex::class, $mutex);

        $this->assertFalse($this->isFreeLock($mutex, $mutexName));
        $this->assertFalse($mutex->acquire());
        $mutex->release();

        $this->assertTrue($this->isFreeLock($mutex, $mutexName));
        $this->assertTrue($mutex->acquire());
        $this->assertFalse($this->isFreeLock($mutex, $mutexName));

        $mutex->release();
        $this->assertTrue($this->isFreeLock($mutex, $mutexName));
    }
}
