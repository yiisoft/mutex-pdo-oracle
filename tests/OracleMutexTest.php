<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Oracle\Tests;

use InvalidArgumentException;
use PDO;
use Yiisoft\Mutex\Oracle\OracleMutex;

use function implode;
use function microtime;
use function sprintf;

final class OracleMutexTest extends TestCase
{
    public function testMutexAcquire(): void
    {
        $mutex = $this->createMutex('testMutexAcquire');

        $this->assertTrue($mutex->acquire());
        $mutex->release();
    }

    public function testThatMutexLockIsWorking(): void
    {
        $mutexOne = $this->createMutex('testThatMutexLockIsWorking');
        $mutexTwo = $this->createMutex('testThatMutexLockIsWorking');

        $this->assertTrue($mutexOne->acquire());
        $this->assertFalse($mutexTwo->acquire());
        $mutexOne->release();
        $mutexTwo->release();

        $this->assertTrue($mutexTwo->acquire());
        $mutexTwo->release();
    }

    public function testThatMutexLockIsWorkingOnTheSameComponent(): void
    {
        $mutex = $this->createMutex('testThatMutexLockIsWorkingOnTheSameComponent');

        $this->assertTrue($mutex->acquire());
        $this->assertFalse($mutex->acquire());

        $mutex->release();
        $mutex->release();
    }

    public function testTimeout(): void
    {
        $mutexName = __METHOD__;
        $mutexOne = $this->createMutex($mutexName);
        $mutexTwo = $this->createMutex($mutexName);

        $this->assertTrue($mutexOne->acquire());
        $microtime = microtime(true);
        $this->assertFalse($mutexTwo->acquire(1));
        $diff = microtime(true) - $microtime;
        $this->assertTrue($diff >= 1 && $diff < 2);
        $mutexOne->release();
        $mutexTwo->release();
    }

    public function testFreeLock(): void
    {
        $mutexName = 'testFreeLock';
        $mutex = $this->createMutex($mutexName);

        $mutex->acquire();
        $this->assertFalse($this->isFreeLock($mutex, $mutexName));

        $mutex->release();
        $this->assertTrue($this->isFreeLock($mutex, $mutexName));
    }

    public function testDestruct(): void
    {
        $mutexName = 'testDestruct';
        $mutex = $this->createMutex($mutexName);

        $this->assertTrue($mutex->acquire());
        $this->assertFalse($this->isFreeLock($mutex, $mutexName));

        unset($mutex);

        $statement = $this->connection()->prepare(
            'DECLARE
                handle VARCHAR2(128);
            BEGIN
                DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
                :releaseStatus := DBMS_LOCK.RELEASE(handle);
            END;'
        );

        $releaseStatus = 0;
        $statement->bindValue(':name', $mutexName);
        $statement->bindParam(':releaseStatus', $releaseStatus, PDO::PARAM_INT, 1);
        $statement->execute();

        $this->assertTrue($releaseStatus === 4 || $releaseStatus === '4');
    }

    public function testConstructorFailureForIncorrectDriver(): void
    {
        $connection = $this->createConfiguredMock(PDO::class, ['getAttribute' => 'mysql']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Oracle connection instance should be passed. Got "mysql".');

        new OracleMutex('testConstructorFailureForIncorrectDriver', $connection);
    }

    public function testConstructorFailureForIncorrectLockMode(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage(sprintf(
            '"incorrect-mode" is not valid lock mode for "%s". It must be one of the values of: "%s".',
            OracleMutex::class,
            implode('", "', [
                OracleMutex::MODE_X,
                OracleMutex::MODE_NL,
                OracleMutex::MODE_S,
                OracleMutex::MODE_SX,
                OracleMutex::MODE_SS,
                OracleMutex::MODE_SSX,
            ]),
        ));

        new OracleMutex('testConstructorFailureForIncorrectLockMode', $this->connection(), 'incorrect-mode');
    }

    private function createMutex(string $name): OracleMutex
    {
        return new OracleMutex($name, $this->connection());
    }
}
