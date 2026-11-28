<?php

/**
 * What keeps a posting of a check in the posting window apart from another posting of it:
 * the check's lock, and the insurance companies it already has a payment session for.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Billing;

use OpenEMR\Billing\EraCheckPosting;
use OpenEMR\Billing\EraPosting\PostingLock;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Tests\Services\Billing\EraPosting\OtherConnection;
use PHPUnit\Framework\TestCase;

class EraCheckPostingGuardsTest extends TestCase
{
    private const CHECK = 'GUARDCHK1';
    private const OTHER_CHECK = 'GUARDCHK2';
    private const CHECK_DATE = '20261020';
    private const PAYER_A = 99990361;
    private const PAYER_B = 99990362;
    /** A payer id with no insurance company on file. */
    private const PAYER_GONE = 99990363;

    private OtherConnection $other;

    protected function setUp(): void
    {
        $this->cleanUp();
        $this->other = new OtherConnection();
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `insurance_companies` (`id`, `name`) VALUES (?, ?), (?, ?)',
            [self::PAYER_A, 'Guard Alpha Health', self::PAYER_B, 'Guard Beta Plan']
        );
    }

    protected function tearDown(): void
    {
        $this->other->close();
        foreach ([self::CHECK, self::OTHER_CHECK] as $check) {
            PostingLock::release(PostingLock::forCheck($check, self::CHECK_DATE));
        }
        $this->cleanUp();
    }

    public function testACheckIsTakenForTheWholeRunAndLetGoAtItsEnd(): void
    {
        $run = new EraCheckPosting((string) self::PAYER_A, lockWaitSeconds: 0);
        $lock = PostingLock::forCheck(self::CHECK, self::CHECK_DATE);

        $this->assertTrue($run->lockCheck(self::CHECK, self::CHECK_DATE));
        $this->assertTrue($run->lockCheck(self::OTHER_CHECK, self::CHECK_DATE));

        $this->assertFalse($run->isBusy(self::CHECK));
        $this->assertFalse($run->takeBusyNotice(self::CHECK));
        $this->assertFalse($this->other->lock($lock));

        $run->releaseLocks();

        $this->assertTrue($this->other->lock($lock));
        $this->assertTrue($this->other->lock(PostingLock::forCheck(self::OTHER_CHECK, self::CHECK_DATE)));
    }

    public function testACheckAnotherPostingHasIsBusyAndReportedOnce(): void
    {
        // Unattended posting has the check, for whichever insurance company.
        $this->assertTrue($this->other->lock(PostingLock::forCheck(self::CHECK, self::CHECK_DATE)));
        $run = new EraCheckPosting((string) self::PAYER_B, lockWaitSeconds: 0);

        $this->assertFalse($run->lockCheck(self::CHECK, self::CHECK_DATE));
        $this->assertTrue($run->lockCheck(self::OTHER_CHECK, self::CHECK_DATE));

        $this->assertTrue($run->isBusy(self::CHECK));
        $this->assertFalse($run->isBusy(self::OTHER_CHECK));
        $this->assertTrue($run->takeBusyNotice(self::CHECK));
        $this->assertFalse($run->takeBusyNotice(self::CHECK));
        $this->assertTrue($run->isBusy(self::CHECK));

        // Letting go of this run's checks leaves the other posting its own.
        $run->releaseLocks();
        $this->assertTrue(PostingLock::isHeld(PostingLock::forCheck(self::CHECK, self::CHECK_DATE)));
        $this->assertFalse(PostingLock::isHeld(PostingLock::forCheck(self::OTHER_CHECK, self::CHECK_DATE)));
    }

    public function testTheOtherInsuranceCompaniesACheckWasPostedForAreNamed(): void
    {
        $forA = new EraCheckPosting((string) self::PAYER_A);
        $this->assertSame([], $forA->otherPayers(self::CHECK, self::CHECK_DATE));

        // Its own session, by either reference a posting stores.
        $this->session(self::PAYER_A, 'ePay - ' . self::CHECK);
        $this->assertSame([], $forA->otherPayers(self::CHECK, self::CHECK_DATE));

        $this->session(self::PAYER_B, self::CHECK);
        $this->session(self::PAYER_B, 'ePay - ' . self::CHECK);
        $this->assertSame(['Guard Beta Plan'], $forA->otherPayers(self::CHECK, self::CHECK_DATE));
        $this->assertSame(['Guard Alpha Health'], (new EraCheckPosting((string) self::PAYER_B))->otherPayers(self::CHECK, self::CHECK_DATE));

        // Posted with no insurance company, and for one that is no longer on file.
        $this->session(0, self::CHECK);
        $this->session(self::PAYER_GONE, self::CHECK);
        $this->assertSame(['', 'Guard Beta Plan', '#' . self::PAYER_GONE], $forA->otherPayers(self::CHECK, self::CHECK_DATE));

        // A posting window opened for no insurance company sees every company's session.
        $this->assertSame(
            ['Guard Alpha Health', 'Guard Beta Plan', '#' . self::PAYER_GONE],
            (new EraCheckPosting(''))->otherPayers(self::CHECK, self::CHECK_DATE)
        );

        // The same number on another date is another check; with no date the number decides.
        $this->assertSame([], $forA->otherPayers(self::CHECK, '20250101'));
        $this->assertCount(3, $forA->otherPayers(self::CHECK, ''));
        // A patient payment that happens to carry the number is not a posting of the check.
        $this->session(self::PAYER_B, self::OTHER_CHECK, 'patient');
        $this->assertSame([], $forA->otherPayers(self::OTHER_CHECK, self::CHECK_DATE));
    }

    private function session(int $payerId, string $reference, string $paymentType = 'insurance'): void
    {
        QueryUtils::sqlInsert(
            'INSERT INTO ar_session (payer_id, user_id, reference, check_date, deposit_date, pay_total, modified_time,'
            . ' global_amount, payment_type, adjustment_code, post_to_date, patient_id, payment_method)'
            . " VALUES (?, 1, ?, ?, ?, 70, NOW(), 0, ?, 'insurance_payment', ?, 0, 'electronic')",
            [$payerId, $reference, self::CHECK_DATE, self::CHECK_DATE, $paymentType, self::CHECK_DATE]
        );
    }

    private function cleanUp(): void
    {
        QueryUtils::sqlStatementThrowException(
            'DELETE FROM ar_session WHERE reference IN (?, ?, ?, ?)',
            [self::CHECK, 'ePay - ' . self::CHECK, self::OTHER_CHECK, 'ePay - ' . self::OTHER_CHECK]
        );
        QueryUtils::sqlStatementThrowException('DELETE FROM `insurance_companies` WHERE `id` IN (?, ?)', [self::PAYER_A, self::PAYER_B]);
    }
}
