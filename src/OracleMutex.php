<?php

declare(strict_types=1);

namespace Yiisoft\Mutex;

use InvalidArgumentException;
use PDO;

/**
 * OracleMutex implements mutex "lock" mechanism via Oracle locks.
 *
 * Application configuration example:
 *
 * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
 */
class OracleMutex implements MutexInterface
{
    /** available lock modes */
    public const MODE_X = 'X_MODE';
    public const MODE_NL = 'NL_MODE';
    public const MODE_S = 'S_MODE';
    public const MODE_SX = 'SX_MODE';
    public const MODE_SS = 'SS_MODE';
    public const MODE_SSX = 'SSX_MODE';

    protected PDO $connection;

    /**
     * @var string lock mode to be used.
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm#CHDBCFDI
     */
    private string $lockMode;
    /**
     * @var bool whether to release lock on commit.
     */
    private bool $releaseOnCommit;

    private string $name;

    /**
     * OracleMutex constructor.
     *
     * @param string $name Mutex name.
     * @param PDO $connection PDO connection instance to use.
     * @param string $lockMode Lock mode to be used.
     * @param bool $releaseOnCommit Whether to release lock on commit.
     * @param bool $autoRelease Whether all locks acquired in this process (i.e. local locks) must be released
     * automatically before finishing script execution. Defaults to true. Setting this property
     * to true means that all locks acquired in this process must be released (regardless of
     * errors or exceptions).
     */
    public function __construct(
        string $name,
        PDO $connection,
        string $lockMode = self::MODE_X,
        bool $releaseOnCommit = false,
        bool $autoRelease = true
    ) {
        $this->name = $name;
        $this->connection = $connection;

        $driverName = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);
        if (in_array($driverName, ['oci', 'obdb'])) {
            throw new InvalidArgumentException(
                'Connection must be configured to use Oracle database. Got ' . $driverName . '.'
            );
        }

        $this->lockMode = $lockMode;
        $this->releaseOnCommit = $releaseOnCommit;

        if ($autoRelease) {
            register_shutdown_function(function () {
                $this->release();
            });
        }
    }

    /**
     * {@inheritdoc}
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
     */
    public function acquire(int $timeout = 0): bool
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
                DBMS_LOCK.' . $this->lockMode . ',
                ' . $timeout . ',
                ' . $releaseOnCommit . '
            );
        END;');

        $statement->bindValue(':name', $this->name);
        $statement->bindParam(':lockStatus', $lockStatus, PDO::PARAM_INT, 1);
        $statement->execute();

        return $lockStatus === 0 || $lockStatus === '0';
    }

    /**
     * {@inheritdoc}
     *
     * @see http://docs.oracle.com/cd/B19306_01/appdev.102/b14258/d_lock.htm
     */
    public function release(): void
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
        $statement->bindValue(':name', $this->name);
        $statement->bindParam(':result', $releaseStatus, PDO::PARAM_INT, 1);
        $statement->execute();

        if ($releaseStatus !== 0 && $releaseStatus !== '0') {
            throw new RuntimeExceptions("Unable to release lock \"$this->name\".");
        }
    }
}
