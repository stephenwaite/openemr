<?php

/**
 * A second connection to the test database, to hold a posting lock as another posting would.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Billing\EraPosting;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DriverManager;
use OpenEMR\BC\DatabaseConnectionOptions;
use OpenEMR\Core\OEGlobalsBag;

final readonly class OtherConnection
{
    private Connection $connection;

    public function __construct()
    {
        // A connection of its own, never the one the code under test uses: a lock is held by
        // a connection, and the same connection can always take its own lock again.
        $this->connection = DriverManager::getConnection(
            DatabaseConnectionOptions::forSite(OEGlobalsBag::getInstance()->getString('OE_SITE_DIR'))->toDbalParams()
        );
    }

    /**
     * @return bool false when another connection holds the lock
     */
    public function lock(string $name): bool
    {
        $locked = $this->connection->fetchOne('SELECT GET_LOCK(?, 0)', [$name]);
        return is_numeric($locked) && (int) $locked === 1;
    }

    public function unlock(string $name): void
    {
        $this->connection->fetchOne('SELECT RELEASE_LOCK(?)', [$name]);
    }

    /**
     * End the connection, as when the process that held it is gone.
     */
    public function close(): void
    {
        $this->connection->close();
    }
}
