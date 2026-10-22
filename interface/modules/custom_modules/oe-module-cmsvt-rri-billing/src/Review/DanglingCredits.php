<?php

/**
 * Step 4's dangling credits (COBOL rri222 and rri221): for each cancel ('-') in
 * the ordfile, the charge it cancels in the same account (same CDM, a later
 * date of service), listed together and left out of the other checks; a cancel
 * without one is a dangling credit, a cancel of something billed before.
 *
 * Changes from 7.0.2: the lines are grouped by account (7.0.2 looked up only the
 * first account and then checked every later line on its own, under the first
 * account's name), and a charge is a line with a blank indicator (7.0.2 compared
 * it with ' ', which the database gives back as '', so no pair ever matched).
 * The date test is 7.0.2's (cancel date < charge date); see PORT-PLAN question 5.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StoredOrdfile;

final readonly class DanglingCredits
{
    public function __construct(private AccountFile $accounts)
    {
    }

    public function check(StoredOrdfile $ordfile): DanglingCreditsResult
    {
        $file222 = [];
        $warnings = [];
        $paired = [];
        foreach (self::byAccount($ordfile->lines) as $ord8 => $lines) {
            $account = $this->accounts->find(str_pad((string) $ord8, 8, '0', STR_PAD_LEFT));
            if ($account === null) {
                $warnings[] = $ord8 . ' NOT ON FILE???';
                continue;
            }
            foreach ($lines as $cancelId => $cancel) {
                if ($cancel->ind !== '-') {
                    continue;
                }
                $chargeId = null;
                foreach ($lines as $id => $line) {
                    if ($line->proc === $cancel->proc && $line->ind === ' ' && $cancel->dateT < $line->dateT) {
                        $chargeId = $id;
                    }
                }
                if ($chargeId === null) {
                    $file222[] = self::line($account, '000', $cancel, '*** DANGLING CREDIT ***');
                    continue;
                }
                $file222[] = self::line($account, $lines[$chargeId]->ord3, $lines[$chargeId], '');
                $file222[] = self::line($account, $cancel->ord3, $cancel, '');
                $paired[$chargeId] = true;
                $paired[$cancelId] = true;
            }
        }
        return new DanglingCreditsResult($file222, array_diff_key($ordfile->lines, $paired), $warnings);
    }

    /**
     * @param array<int, OrdfileLine> $lines
     * @return array<string, array<int, OrdfileLine>> consecutive lines of each account
     */
    private static function byAccount(array $lines): array
    {
        $groups = [];
        foreach ($lines as $id => $line) {
            $groups[$line->ord8][$id] = $line;
        }
        return $groups;
    }

    private static function line(AccountRecord $account, string $sequence, OrdfileLine $line, string $note): string
    {
        return str_pad($account->actno, 8, '0', STR_PAD_LEFT) . str_pad($sequence, 3) . ' ' . str_pad($line->order, 4) . ' '
            . str_pad($line->proc, 4) . str_pad($line->ind, 1) . ' ' . $line->dateT->format('Ymd') . ' '
            . str_pad($account->garname, 24) . ' ' . str_pad($note, 24);
    }
}
