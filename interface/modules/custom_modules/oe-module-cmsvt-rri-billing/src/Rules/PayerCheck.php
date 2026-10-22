<?php

/**
 * Lists the charge file's insurances that can't be billed electronically (the
 * COBOL rrmc007, step 1's errfile007): RRMC payer codes that aren't in the
 * hospital file or name no insurance company ('XXX'), and companies without a
 * payer id (paper claims, self pay).
 *
 * Lines keep the 7.0.2 column layout and order (by code, descending) so the
 * biller's printout reads the same.
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
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileInsurance;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\PayerDirectory;

final readonly class PayerCheck
{
    private const POSITIONS = ['PRIMARY    ', 'SECONDARY  ', 'TERTIARY   ', 'QUATERNARY '];
    private const UNKNOWN = 'XXX';

    /** Where the shown code starts in a line: after the position and the RRMC code. */
    private const CODE_COLUMN = 17;

    public function __construct(private PayerDirectory $payers)
    {
    }

    /**
     * @param list<ChargeFileAccount> $accounts
     * @return list<string> errfile007 lines; empty when every insurance is electronic
     */
    public function check(array $accounts): array
    {
        $lines = [];
        foreach ($accounts as $account) {
            foreach ($account->insurances() as $position => $insurance) {
                if ($insurance->isEmpty()) {
                    continue;
                }
                $company = $this->payers->companyForRrmcCode($insurance->payerCode);
                if ($company?->electronic === true) {
                    continue;
                }
                $lines[] = self::line($account, $insurance, self::POSITIONS[$position], $company?->legacyCode() ?? self::UNKNOWN);
            }
        }
        // Descending on the shown code, then on the whole line (the COBOL job's sort).
        usort(
            $lines,
            static fn(string $a, string $b): int => strcmp(substr($b, self::CODE_COLUMN, 3), substr($a, self::CODE_COLUMN, 3))
                ?: strcmp($b, $a)
        );
        return $lines;
    }

    private static function line(ChargeFileAccount $account, ChargeFileInsurance $insurance, string $position, string $shownCode): string
    {
        // The 7.0.2 module built these from the untrimmed fields, so each is
        // padded back to its width; the MRN is its 8 characters without the
        // hyphens, zero-filled on the left.
        $mrn = str_pad(str_replace('-', '', str_pad($account->mrnRaw, 8)), 8, '0', STR_PAD_LEFT);
        return $position . str_pad($insurance->payerCode, 5) . ' ' . $shownCode
            . ' ' . str_pad($insurance->policyNumber, 30)
            . ' ' . str_pad($insurance->payerAddress->city, 20)
            . ' ' . str_pad($insurance->payerAddress->state, 2)
            . ' ' . str_pad($insurance->payerAddress->zip, 10)
            . ' ' . str_pad($insurance->payerPhone, 12)
            . ' MRN ' . $mrn . '        ';
    }
}
