<?php

/**
 * The named locks that keep two postings of the same remittance apart.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Billing\EraPosting;

use OpenEMR\Billing\EraPosting\PostingLock;
use PHPUnit\Framework\TestCase;

class PostingLockTest extends TestCase
{
    private const CHECK = 'LOCKTEST-CHK1';

    private OtherConnection $other;

    protected function setUp(): void
    {
        $this->other = new OtherConnection();
    }

    protected function tearDown(): void
    {
        PostingLock::release(PostingLock::forCheck(self::CHECK, '20261020'));
        PostingLock::release(PostingLock::forFile(99990991));
        $this->other->close();
    }

    public function testACheckHasOneLockWhateverInsuranceCompanyItIsPostedFor(): void
    {
        $lock = PostingLock::forCheck(self::CHECK, '20261020');

        $this->assertSame($lock, PostingLock::forCheck(self::CHECK, '20261020'));
        // Some payers reuse placeholder check numbers: the date tells the checks apart.
        $this->assertNotSame($lock, PostingLock::forCheck(self::CHECK, '20261021'));
        $this->assertNotSame($lock, PostingLock::forCheck(self::CHECK, ''));
        $this->assertNotSame($lock, PostingLock::forCheck('LOCKTEST-CHK2', '20261020'));
        // The session lookup ignores letter case and trailing spaces, so the lock does too.
        $this->assertSame($lock, PostingLock::forCheck('locktest-chk1', '20261020'));
        $this->assertSame($lock, PostingLock::forCheck('LockTest-Chk1  ', '20261020'));
        // The database takes a lock name of 64 characters at most, however long the check number.
        $this->assertLessThanOrEqual(64, strlen(PostingLock::forCheck(str_repeat('9', 300), '20261020')));
        $this->assertSame('era_file_99990991', PostingLock::forFile(99990991));
    }

    public function testALockIsHeldUntilItIsReleased(): void
    {
        $lock = PostingLock::forCheck(self::CHECK, '20261020');
        $this->assertFalse(PostingLock::isHeld($lock));

        $this->assertTrue(PostingLock::acquire($lock, 0));

        $this->assertTrue(PostingLock::isHeld($lock));
        $this->assertFalse($this->other->lock($lock));

        PostingLock::release($lock);

        $this->assertFalse(PostingLock::isHeld($lock));
        $this->assertTrue($this->other->lock($lock));
    }

    public function testALockAnotherConnectionHoldsIsNotTakenAndGoesWithThatConnection(): void
    {
        $lock = PostingLock::forFile(99990991);
        $this->assertTrue($this->other->lock($lock));

        $this->assertTrue(PostingLock::isHeld($lock));
        $this->assertFalse(PostingLock::acquire($lock, 0));

        // The process that held it is gone without letting go.
        $this->other->close();

        $this->assertFalse(PostingLock::isHeld($lock));
        $this->assertTrue(PostingLock::acquire($lock, 0));
    }
}
