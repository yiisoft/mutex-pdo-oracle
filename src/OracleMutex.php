<?php

namespace Yiisoft\Mutex;

/**
 * OracleMutex implements mutex "lock" mechanism via Oracle locks.
 *
 * Application configuration example:
 *
 * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
 * @see Mutex
 */
class OracleMutex extends Mutex
{
    /** available lock modes */
    const MODE_X = 'X_MODE';
    const MODE_NL = 'NL_MODE';
    const MODE_S = 'S_MODE';
    const MODE_SX = 'SX_MODE';
    const MODE_SS = 'SS_MODE';
    const MODE_SSX = 'SSX_MODE';

    /**
     * @var \PDO
     */
    protected $connection;

    /**
     * @var string lock mode to be used.
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#CHDBCFDI
     */
    private $lockMode;
    /**
     * @var bool whether to release lock on commit.
     */
    private $releaseOnCommit;

    /**
     * OracleMutex constructor.
     *
     * @param \PDO   $connection
     * @param string $lockMode        lock mode to be used.
     * @param bool   $releaseOnCommit whether to release lock on commit.
     * @param bool   $autoRelease
     */
    public function __construct(
        \PDO $connection,
        string $lockMode = self::MODE_X,
        bool $releaseOnCommit = false,
        bool $autoRelease = true
    ) {
        $this->connection = $connection;
        parent::__construct($autoRelease);

        $driverName = $connection->getAttribute(\PDO::ATTR_DRIVER_NAME);
        if (in_array($driverName, ['oci', 'obdb'])) {
            throw new \InvalidArgumentException(
                'Connection must be configured to use Oracle database. Got '.$driverName.'.'
            );
        }

        $this->lockMode = $lockMode;
        $this->releaseOnCommit = $releaseOnCommit;
    }

    /**
     * Acquires lock by given name.
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
     *
     * @param string $name    of the lock to be acquired.
     * @param int    $timeout time (in seconds) to wait for lock to become released.
     *
     * @return bool acquiring result.
     */
    protected function acquireLock(string $name, int $timeout = 0): bool
    {
        $lockStatus = null;

        // clean vars before using
        $releaseOnCommit = $this->releaseOnCommit ? 'TRUE' : 'FALSE';
        $timeout = (int) abs($timeout);

        // inside pl/sql scopes pdo binding not working correctly :(

        $statement = $this->connection->prepare('DECLARE
            handle VARCHAR2(128);
        BEGIN
            DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
            :lockStatus := DBMS_LOCK.REQUEST(
                handle,
                DBMS_LOCK.'.$this->lockMode.',
                '.$timeout.',
                '.$releaseOnCommit.'
            );
        END;');

        $statement->bindValue(':name', $name);
        $statement->bindParam(':lockStatus', $lockStatus, \PDO::PARAM_INT, 1);
        $statement->execute();

        return $lockStatus === 0 || $lockStatus === '0';
    }

    /**
     * Releases lock by given name.
     *
     * @param string $name of the lock to be released.
     *
     * @return bool release result.
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
     */
    protected function releaseLock(string $name): bool
    {
        $releaseStatus = null;

        $statement = $this->connection->prepare(
            'DECLARE
                handle VARCHAR2(128);
            BEGIN
                DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
                :result := DBMS_LOCK.RELEASE(handle);
            END;'
        );
        $statement->bindValue(':name', $name);
        $statement->bindParam(':result', $releaseStatus, \PDO::PARAM_INT, 1);
        $statement->execute();

        return $releaseStatus === 0 || $releaseStatus === '0';
    }
}
