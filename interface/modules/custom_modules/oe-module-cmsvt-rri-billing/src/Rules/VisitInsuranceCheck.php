<?php

/**
 * Warns when one patient (MRN) has visits in the same charge file with
 * different primary payers: RRMC gave different insurances for the same person.
 *
 * The 7.0.2 module meant to run this in step 1 (visitno-load) but its warnings
 * were written where nothing read them, and it read the file at 7-digit visit
 * number offsets; this runs it on the parsed accounts.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Rules;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;

final class VisitInsuranceCheck
{
    /**
     * @param list<ChargeFileAccount> $accounts in file order
     * @return list<string> errfile008 lines
     */
    public function check(array $accounts): array
    {
        $messages = [];
        /** @var array<string, array{string, string}> $seen MRN => [visit number, primary payer code] */
        $seen = [];
        foreach ($accounts as $account) {
            $code = $account->primaryInsurance->payerCode;
            $previous = $seen[$account->mrn] ?? null;
            if ($previous !== null && $previous[0] !== $account->visitNumber && $previous[1] !== $code) {
                $messages[] = '*** uh oh, rrmc ins code ' . $code . ' is different from prior record ins code '
                    . $previous[1] . ' in this file for mrn ' . $account->mrn;
                $messages[] = '*** which means we were given different insurances for distinct charges for the same person and should be remedied.';
            }
            $seen[$account->mrn] = [$account->visitNumber, $code];
        }
        return $messages;
    }
}
