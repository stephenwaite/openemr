<?php

/**
 * The record of unattended posting runs: which received 835s are waiting, and what was done with each.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\EraAutoPost;

interface AutoPostLog
{
    /**
     * The 835s marked for unattended posting when they were received that no run has
     * taken, whose partner still has an active user to post as.
     *
     * @return list<PendingRemittance>
     */
    public function pending(): array;

    /**
     * Keep the file for this run for as long as it works on it, from before it takes the
     * file until it has finished it. The ERA worklist reads the lock to tell a run still
     * working on a file from one that stopped without finishing it. The lock goes with the
     * run's database connection, so a run that died does not keep it.
     *
     * @return bool false when another run holds the file
     */
    public function lockFile(int $receivedId): bool;

    public function unlockFile(int $receivedId): void;

    /**
     * Mark the file as taken by this run. False when another run took it first, or when
     * its partner no longer has that user, still active, to post as.
     */
    public function take(PendingRemittance $file): bool;

    public function finish(int $receivedId, AutoPostFileStatus $status): void;

    /**
     * @param int $sequence the check's position in the file, from 1
     */
    public function record(int $receivedId, int $sequence, CheckRecord $check, \DateTimeImmutable $at): void;
}
