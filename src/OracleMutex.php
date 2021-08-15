<?php

declare(strict_types=1);

namespace Yiisoft\Mutex\Oracle;

use InvalidArgumentException;
use PDO;
use Yiisoft\Mutex\Mutex;

use function implode;
use function in_array;
use function sprintf;

/**
 * OracleMutex implements mutex "lock" mechanism via Oracle locks.
 */
final class OracleMutex extends Mutex
{
    /** available lock modes */
    public const MODE_X = 'X_MODE';
    public const MODE_NL = 'NL_MODE';
    public const MODE_S = 'S_MODE';
    public const MODE_SX = 'SX_MODE';
    public const MODE_SS = 'SS_MODE';
    public const MODE_SSX = 'SSX_MODE';

    private const MODES = [
        self::MODE_X,
        self::MODE_NL,
        self::MODE_S,
        self::MODE_SX,
        self::MODE_SS,
        self::MODE_SSX,
    ];

    private string $lockName;
    private PDO $connection;
    private string $lockMode;
    private bool $releaseOnCommit;

    /**
     * @param string $name Mutex name.
     * @param PDO $connection PDO connection instance to use.
     * @param string $lockMode Lock mode to be used {@see https://docs.oracle.com/en/database/oracle/oracle-database/21/arpls/DBMS_LOCK.html#GUID-8F868C41-CEA3-48E2-8701-3C0F8D2B308C}.
     * @param bool $releaseOnCommit Whether to release lock on commit.
     */
    public function __construct(
        string $name,
        PDO $connection,
        string $lockMode = self::MODE_X,
        bool $releaseOnCommit = false
    ) {
        if (!in_array($lockMode, self::MODES, true)) {
            throw new InvalidArgumentException(sprintf(
                '"%s" is not valid lock mode for "%s". It must be one of the values of: "%s".',
                $lockMode,
                self::class,
                implode('", "', self::MODES),
            ));
        }

        $this->lockName = $name;
        $this->connection = $connection;
        $this->lockMode = $lockMode;
        $this->releaseOnCommit = $releaseOnCommit;

        /** @var string $driverName */
        $driverName = $connection->getAttribute(PDO::ATTR_DRIVER_NAME);

        if (!in_array($driverName, ['oci', 'obdb'], true)) {
            throw new InvalidArgumentException("Oracle connection instance should be passed. Got \"$driverName\".");
        }

        parent::__construct(self::class, $name);
    }

    /**
     * {@inheritdoc}
     *
     * @see https://docs.oracle.com/en/database/oracle/oracle-database/21/arpls/DBMS_LOCK.html
     */
    protected function acquireLock(int $timeout = 0): bool
    {
        $lockStatus = 4;

        // clean vars before using
        $releaseOnCommit = $this->releaseOnCommit ? 'TRUE' : 'FALSE';

        // inside pl/sql scopes pdo binding not working correctly :(

        $statement = $this->connection->prepare(
            "DECLARE
                handle VARCHAR2(128);
            BEGIN
                DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
                :lockStatus := DBMS_LOCK.REQUEST(
                    handle,
                    DBMS_LOCK.$this->lockMode,
                    $timeout,
                    $releaseOnCommit
                );
            END;"
        );

        $statement->bindValue(':name', $this->lockName);
        $statement->bindParam(':lockStatus', $lockStatus, PDO::PARAM_INT, 1);
        $statement->execute();

        return $lockStatus === 0 || $lockStatus === '0';
    }

    /**
     * {@inheritdoc}
     *
     * @see https://docs.oracle.com/en/database/oracle/oracle-database/21/arpls/DBMS_LOCK.html
     */
    protected function releaseLock(): bool
    {
        $releaseStatus = 4;

        $statement = $this->connection->prepare(
            'DECLARE
                handle VARCHAR2(128);
            BEGIN
                DBMS_LOCK.ALLOCATE_UNIQUE(:name, handle);
                :releaseStatus := DBMS_LOCK.RELEASE(handle);
            END;'
        );

        $statement->bindValue(':name', $this->lockName);
        $statement->bindParam(':releaseStatus', $releaseStatus, PDO::PARAM_INT, 1);
        $statement->execute();

        return $releaseStatus === 0 || $releaseStatus === '0';
    }
}
