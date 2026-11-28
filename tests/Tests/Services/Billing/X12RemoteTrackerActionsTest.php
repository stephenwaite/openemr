<?php

/**
 * Database-backed coverage of the Claim File Tracker's actions on a file that
 * was not sent: sending the same file again and marking it as resolved.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Billing;

use DateTimeImmutable;
use Lcobucci\Clock\FrozenClock;
use OpenEMR\Billing\BillingProcessor\X12RemoteTracker;
use OpenEMR\Billing\BillingProcessor\X12RemoteTrackerActions;
use OpenEMR\Common\Database\QueryUtils;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class X12RemoteTrackerActionsTest extends TestCase
{
    /** A partner with no SFTP settings at all. */
    private const PARTNER = 99990771;
    private const FILE_PREFIX = 'tracker-actions-test-';
    private const QUEUED_AT = '2026-11-01 08:00:00';
    private const NOW = '2026-11-28 15:04:05';

    private X12RemoteTrackerActions $actions;

    protected function setUp(): void
    {
        $this->cleanUp();
        QueryUtils::sqlStatementThrowException(
            "INSERT INTO x12_partners (id, name) VALUES (?, 'Tracker Actions Test Partner')",
            [self::PARTNER]
        );
        $this->actions = new X12RemoteTrackerActions(new FrozenClock(new DateTimeImmutable(self::NOW)));
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
    }

    private function cleanUp(): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM x12_remote_tracker WHERE x12_filename LIKE ?',
            [self::FILE_PREFIX . '%']
        );
        QueryUtils::sqlStatementThrowException('DELETE FROM x12_partners WHERE id = ?', [self::PARTNER]);
    }

    /**
     * @param ?list<string> $messages
     */
    private function file(string $status, ?array $messages = null): int
    {
        return QueryUtils::sqlInsert(
            'INSERT INTO x12_remote_tracker (x12_partner_id, x12_filename, status, claims, messages, created_at, updated_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                self::PARTNER,
                self::FILE_PREFIX . $status . '.txt',
                $status,
                '[]',
                $messages === null ? null : json_encode($messages, JSON_THROW_ON_ERROR),
                self::QUEUED_AT,
                self::QUEUED_AT,
            ]
        );
    }

    /**
     * @return array{status: string, messages: list<string>, updated_at: string}
     */
    private function row(int $id): array
    {
        $row = QueryUtils::querySingleRow('SELECT status, messages, updated_at FROM x12_remote_tracker WHERE id = ?', [$id]);
        $this->assertIsArray($row);
        $this->assertIsString($row['status']);
        $this->assertIsString($row['updated_at']);
        return [
            'status' => $row['status'],
            'messages' => X12RemoteTrackerActions::decodeMessages($row['messages'] ?? null),
            'updated_at' => $row['updated_at'],
        ];
    }

    public function testSendAgainPutsAFailedFileBackToWaitingAndKeepsItsMessages(): void
    {
        $id = $this->file(X12RemoteTracker::STATUS_PARAMETER_ERROR, ['`X12 SFTP Host` is required']);

        $this->assertTrue($this->actions->sendAgain($id, 'biller'));

        $this->assertSame(
            [
                'status' => X12RemoteTracker::STATUS_WAITING,
                'messages' => ['`X12 SFTP Host` is required', 'Queued to be sent again by biller on ' . self::NOW],
                'updated_at' => self::NOW,
            ],
            $this->row($id)
        );
    }

    /**
     * @return array<string, array{string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function notSentAgainProvider(): array
    {
        return [
            'sent' => [X12RemoteTracker::STATUS_SUCCESS],
            'waiting' => [X12RemoteTracker::STATUS_WAITING],
            'in progress' => [X12RemoteTracker::STATUS_IN_PROGRESS],
            'resolved' => [X12RemoteTracker::STATUS_RESOLVED],
        ];
    }

    #[DataProvider('notSentAgainProvider')]
    public function testSendAgainLeavesAFileThatDidNotFailAlone(string $status): void
    {
        $id = $this->file($status);

        $this->assertFalse($this->actions->sendAgain($id, 'biller'));

        $this->assertSame(['status' => $status, 'messages' => [], 'updated_at' => self::QUEUED_AT], $this->row($id));
    }

    public function testSendAgainOfAFileThatDoesNotExistChangesNothing(): void
    {
        $this->assertFalse($this->actions->sendAgain(999999999, 'biller'));
    }

    public function testResolveMarksAFailedFileWithWhoAndWhy(): void
    {
        $id = $this->file(X12RemoteTracker::STATUS_LOGIN_ERROR, ['Invalid Username or Password.']);

        $this->assertTrue($this->actions->resolve($id, 'biller', '  uploaded on the payer portal  '));

        $this->assertSame(
            [
                'status' => X12RemoteTracker::STATUS_RESOLVED,
                'messages' => [
                    'Invalid Username or Password.',
                    'Marked as resolved by biller on ' . self::NOW . ': uploaded on the payer portal',
                ],
                'updated_at' => self::NOW,
            ],
            $this->row($id)
        );
    }

    public function testResolveTakesAFileLeftInProgressAndNeedsNoNote(): void
    {
        $id = $this->file(X12RemoteTracker::STATUS_IN_PROGRESS);

        $this->assertTrue($this->actions->resolve($id, 'biller', ''));

        $row = $this->row($id);
        $this->assertSame(X12RemoteTracker::STATUS_RESOLVED, $row['status']);
        $this->assertSame(['Marked as resolved by biller on ' . self::NOW], $row['messages']);
    }

    public function testResolveKeepsNoMoreThanTheNoteLimit(): void
    {
        $id = $this->file(X12RemoteTracker::STATUS_UPLOAD_ERRROR);

        $this->assertTrue($this->actions->resolve($id, 'biller', str_repeat('n', 300)));

        $this->assertSame(
            ['Marked as resolved by biller on ' . self::NOW . ': ' . str_repeat('n', X12RemoteTrackerActions::NOTE_MAX_LENGTH)],
            $this->row($id)['messages']
        );
    }

    /**
     * @return array<string, array{string}>
     *
     * @codeCoverageIgnore Data providers run before coverage instrumentation starts.
     */
    public static function notResolvedProvider(): array
    {
        return [
            'sent' => [X12RemoteTracker::STATUS_SUCCESS],
            'waiting' => [X12RemoteTracker::STATUS_WAITING],
            'resolved already' => [X12RemoteTracker::STATUS_RESOLVED],
        ];
    }

    #[DataProvider('notResolvedProvider')]
    public function testResolveLeavesAFileThatIsNotStuckAlone(string $status): void
    {
        $id = $this->file($status);

        $this->assertFalse($this->actions->resolve($id, 'biller', 'note'));

        $this->assertSame(['status' => $status, 'messages' => [], 'updated_at' => self::QUEUED_AT], $this->row($id));
    }

    public function testTheSenderTakesAFileQueuedAgainWithItsEarlierMessages(): void
    {
        // The partner has no SFTP settings, so the sender gets as far as checking them: far enough
        // to show it reads the stored messages as a list and adds to them.
        $id = $this->file(X12RemoteTracker::STATUS_CLAIM_FILE_ERROR, ['Could not open local claim file']);
        $this->assertTrue($this->actions->sendAgain($id, 'biller'));

        X12RemoteTracker::sftpSendWaitingFiles();

        $row = $this->row($id);
        $this->assertSame(X12RemoteTracker::STATUS_PARAMETER_ERROR, $row['status']);
        $this->assertSame(
            [
                'Could not open local claim file',
                'Queued to be sent again by biller on ' . self::NOW,
                '`X12 SFTP Host` is required',
            ],
            array_slice($row['messages'], 0, 3)
        );
    }
}
