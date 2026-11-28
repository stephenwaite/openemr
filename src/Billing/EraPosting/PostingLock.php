<?php

/**
 * The named database locks that keep two postings of the same remittance apart.
 *
 * A lock is held on the database connection that took it and goes when that
 * connection does, so a posting that died does not keep one.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\EraPosting;

use OpenEMR\Common\Database\QueryUtils;

final class PostingLock
{
    /** A received file's lock is this followed by its x12_remote_received.id. */
    public const FILE_PREFIX = 'era_file_';

    /**
     * The lock for one check, held for the whole posting of it: finding or creating its
     * payment session, and posting its claim payments. The insurance company is not part of
     * it, so a posting for one company and a posting for another still take turns.
     *
     * Letter case and trailing spaces are not part of it either: the payment session is
     * looked up by ar_session.reference, whose collation ignores both, and two numbers that
     * find the same session have to share a lock.
     *
     * @param string $checkDate yyyymmdd (BPR16), or '' when the check carries none
     */
    public static function forCheck(string $checkNumber, string $checkDate): string
    {
        return 'era_check_' . sha1(mb_strtoupper(rtrim($checkNumber)) . "\0" . $checkDate);
    }

    /**
     * The lock an unattended posting run holds on a received file while it works on it.
     */
    public static function forFile(int $receivedId): string
    {
        return self::FILE_PREFIX . $receivedId;
    }

    /**
     * @param int $waitSeconds how long to wait for another holder to let go; 0 not to wait
     * @return bool false when another connection still holds the lock
     */
    public static function acquire(string $name, int $waitSeconds): bool
    {
        $locked = QueryUtils::fetchSingleValue('SELECT GET_LOCK(?, ?) AS locked', 'locked', [$name, $waitSeconds]);
        return is_numeric($locked) && (int) $locked === 1;
    }

    public static function release(string $name): void
    {
        QueryUtils::fetchSingleValue('SELECT RELEASE_LOCK(?) AS released', 'released', [$name]);
    }

    /**
     * Whether any connection, this one included, holds the lock.
     */
    public static function isHeld(string $name): bool
    {
        $holder = QueryUtils::fetchSingleValue('SELECT IS_USED_LOCK(?) AS holder', 'holder', [$name]);
        return $holder !== null && $holder !== false;
    }
}
