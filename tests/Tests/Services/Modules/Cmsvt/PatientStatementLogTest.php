<?php

/**
 * Database tests for flagging invoices whose emailed statements go unpaid.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Tests\Services\Modules\Cmsvt;

use Cmsvt\OpenEMR\Modules\Customizations\Statements\PatientStatementLog;
use Composer\Autoload\ClassLoader;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Events\Billing\StatementPrintSuggestionFilterEvent;
use PHPUnit\Framework\TestCase;

class PatientStatementLogTest extends TestCase
{
    /** Made-up chart ids no patient has; cleanup removes their statements and payments. */
    private const PID = 990001;
    private const OTHER_PID = 990002;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        $loaders = ClassLoader::getRegisteredLoaders();
        $loader = reset($loaders);
        if (!$loader instanceof ClassLoader) {
            self::fail('Composer ClassLoader not available to register module autoload prefix.');
        }
        $loader->addPsr4(
            'Cmsvt\\OpenEMR\\Modules\\Customizations\\',
            dirname(__DIR__, 5) . '/interface/modules/custom_modules/oe-module-cmsvt/src/'
        );
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUp();
    }

    protected function tearDown(): void
    {
        $this->cleanUp();
        parent::tearDown();
    }

    public function testOnlyTheInvoiceEmailedTwiceUnpaidIsFlagged(): void
    {
        $this->statement(self::PID, 1, 60);
        $this->statement(self::PID, 1, 30);
        $this->statement(self::PID, 2, 30);

        $event = $this->suggestions();

        $this->assertTrue($event->shouldPrint(self::PID, 1));
        $this->assertSame(2, $event->getUnpaidEmailCount(self::PID, 1));
        $this->assertFalse($event->shouldPrint(self::PID, 2), 'the patient\'s other invoice was emailed once');
        $this->assertSame(0, $event->getUnpaidEmailCount(self::PID, 2));
    }

    public function testOneEmailRunCoveringTwoInvoicesFlagsNeither(): void
    {
        $this->statement(self::PID, 1, 30);
        $this->statement(self::PID, 2, 30);

        $event = $this->suggestions();

        $this->assertFalse($event->shouldPrint(self::PID, 1));
        $this->assertFalse($event->shouldPrint(self::PID, 2));
    }

    public function testASameDayResendIsOneStatement(): void
    {
        $this->statement(self::PID, 1, 30);
        $this->statement(self::PID, 1, 30);

        $this->assertFalse($this->suggestions()->shouldPrint(self::PID, 1));
    }

    public function testAStatementPaidAfterItWasSentDoesNotCount(): void
    {
        $this->statement(self::PID, 1, 60);
        $this->payment(self::PID, 1, 45);
        $this->statement(self::PID, 1, 30);
        // Paid on a different invoice: the other patient's second email stays unpaid.
        $this->statement(self::OTHER_PID, 1, 60);
        $this->statement(self::OTHER_PID, 1, 30);
        $this->payment(self::OTHER_PID, 2, 10);

        $event = $this->suggestions();

        $this->assertFalse($event->shouldPrint(self::PID, 1));
        $this->assertTrue($event->shouldPrint(self::OTHER_PID, 1));
    }

    public function testRecentAndPrintedStatementsDoNotCount(): void
    {
        $this->statement(self::PID, 1, 30);
        $this->statement(self::PID, 1, 10);
        $this->statement(self::OTHER_PID, 1, 60, 'mail');
        $this->statement(self::OTHER_PID, 1, 30);

        $event = $this->suggestions();

        $this->assertFalse($event->shouldPrint(self::PID, 1), 'sent 10 days ago, not yet unpaid');
        $this->assertFalse($event->shouldPrint(self::OTHER_PID, 1), 'one of the two went by mail');
    }

    private function suggestions(): StatementPrintSuggestionFilterEvent
    {
        $event = new StatementPrintSuggestionFilterEvent();
        (new PatientStatementLog())->suggestPrint($event);
        return $event;
    }

    private function statement(int $pid, int $encounter, int $daysAgo, string $method = 'email'): void
    {
        QueryUtils::sqlInsert(
            "INSERT INTO `patient_statements` (`pid`, `encounter`, `statement_date`, `method`, `amount`, `created_by`) " .
            "VALUES (?, ?, CURDATE() - INTERVAL ? DAY, ?, 25.00, 1)",
            [$pid, $encounter, $daysAgo, $method]
        );
    }

    private function payment(int $pid, int $encounter, int $daysAgo): void
    {
        $session = QueryUtils::sqlInsert(
            "INSERT INTO `ar_session` (`payer_id`, `user_id`, `reference`, `check_date`, `deposit_date`, `pay_total`, " .
            "`modified_time`, `global_amount`, `payment_type`, `adjustment_code`, `post_to_date`, `patient_id`, `payment_method`) " .
            "VALUES (0, 1, 'test', CURDATE() - INTERVAL ? DAY, CURDATE() - INTERVAL ? DAY, 25.00, NOW(), 0, 'patient', " .
            "'patient_payment', CURDATE(), ?, 'check_payment')",
            [$daysAgo, $daysAgo, $pid]
        );
        QueryUtils::sqlInsert(
            "INSERT INTO `ar_activity` (`pid`, `encounter`, `sequence_no`, `code`, `payer_type`, `post_time`, `post_user`, " .
            "`session_id`, `pay_amount`, `modified_time`, `follow_up`, `account_code`) " .
            "VALUES (?, ?, 1, '', 0, NOW(), 1, ?, 25.00, NOW(), '', 'PP')",
            [$pid, $encounter, $session]
        );
    }

    private function cleanUp(): void
    {
        $pids = [self::PID, self::OTHER_PID];
        QueryUtils::sqlStatementThrowException("DELETE FROM `patient_statements` WHERE `pid` IN (?, ?)", $pids);
        QueryUtils::sqlStatementThrowException("DELETE FROM `ar_activity` WHERE `pid` IN (?, ?)", $pids);
        QueryUtils::sqlStatementThrowException(
            "DELETE FROM `ar_session` WHERE `patient_id` IN (?, ?) AND `reference` = 'test'",
            $pids
        );
    }
}
