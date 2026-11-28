<?php

/**
 * What a biller can do about a claim file in the Claim File Tracker that was
 * not sent: queue the same file to be sent again, or mark it as resolved
 * because it was dealt with another way (sent by hand, generated again).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\BillingProcessor;

use OpenEMR\Common\Database\QueryUtils;
use Psr\Clock\ClockInterface;

final readonly class X12RemoteTrackerActions
{
    /** The statuses a file is left in when it could not be sent. */
    public const FAILED_STATUSES = [
        X12RemoteTracker::STATUS_PARAMETER_ERROR,
        X12RemoteTracker::STATUS_CLAIM_FILE_ERROR,
        X12RemoteTracker::STATUS_LOGIN_ERROR,
        X12RemoteTracker::STATUS_CHDIR_ERROR,
        X12RemoteTracker::STATUS_UPLOAD_ERRROR,
    ];

    /** The longest note kept with a file marked as resolved. */
    public const NOTE_MAX_LENGTH = 255;

    public function __construct(private ClockInterface $clock)
    {
    }

    /**
     * A file that failed can be queued again. One that was sent, is waiting,
     * or is in progress cannot: the receiver has it, or may have it.
     */
    public static function canSendAgain(string $status): bool
    {
        return in_array($status, self::FAILED_STATUSES, true);
    }

    /**
     * A file that failed can be marked as resolved, and so can one left in
     * progress by a send that was interrupted, which nothing else moves on.
     */
    public static function canResolve(string $status): bool
    {
        return self::canSendAgain($status) || $status === X12RemoteTracker::STATUS_IN_PROGRESS;
    }

    /**
     * The messages stored with a tracker row: a JSON list, or nothing.
     *
     * @return list<string>
     */
    public static function decodeMessages(mixed $stored): array
    {
        if (is_string($stored)) {
            $stored = json_decode($stored, true);
        }
        if (!is_array($stored)) {
            return [];
        }
        $messages = [];
        foreach ($stored as $message) {
            if (is_scalar($message)) {
                $messages[] = (string) $message;
            }
        }
        return $messages;
    }

    /**
     * Put a failed file back to waiting, so the SFTP service sends the same
     * file at its next run. The row keeps its earlier messages.
     *
     * @return bool false when the file is not one that failed (nothing changed)
     */
    public function sendAgain(int $id, string $user): bool
    {
        return $this->move(
            $id,
            self::FAILED_STATUSES,
            X12RemoteTracker::STATUS_WAITING,
            sprintf('Queued to be sent again by %s on %s', $user, $this->now())
        );
    }

    /**
     * Mark a file that was not sent as resolved, with who did it and why.
     *
     * @return bool false when the file is not one that can be resolved (nothing changed)
     */
    public function resolve(int $id, string $user, string $note): bool
    {
        $note = mb_substr(trim($note), 0, self::NOTE_MAX_LENGTH);
        return $this->move(
            $id,
            [...self::FAILED_STATUSES, X12RemoteTracker::STATUS_IN_PROGRESS],
            X12RemoteTracker::STATUS_RESOLVED,
            sprintf('Marked as resolved by %s on %s', $user, $this->now()) . ($note !== '' ? ': ' . $note : '')
        );
    }

    /**
     * @param list<string> $from The statuses the row may be in
     */
    private function move(int $id, array $from, string $to, string $message): bool
    {
        return QueryUtils::inTransaction(function () use ($id, $from, $to, $message): bool {
            $rows = QueryUtils::fetchRecords(
                'SELECT `status`, `messages` FROM `x12_remote_tracker` WHERE `id` = ? FOR UPDATE',
                [$id]
            );
            $row = $rows[0] ?? null;
            if (!is_array($row) || !is_string($row['status'] ?? null) || !in_array($row['status'], $from, true)) {
                return false;
            }
            $messages = self::decodeMessages($row['messages'] ?? null);
            $messages[] = $message;
            QueryUtils::sqlStatementThrowException(
                'UPDATE `x12_remote_tracker` SET `status` = ?, `messages` = ?, `updated_at` = ? WHERE `id` = ?',
                [$to, json_encode($messages, JSON_THROW_ON_ERROR), $this->now(), $id]
            );
            return true;
        }) === true;
    }

    private function now(): string
    {
        return $this->clock->now()->format('Y-m-d H:i:s');
    }
}
