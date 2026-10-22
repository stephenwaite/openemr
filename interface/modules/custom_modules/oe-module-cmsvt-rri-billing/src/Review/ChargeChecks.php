<?php

/**
 * Step 4's checks of each account's charges (COBOL rri245, file245): the
 * referring doctor's key and NPI, studies without a reading doctor, CDMs the
 * charge master doesn't have, and the same charge twice ("these can be the
 * charges that need 76 or 77 mod"). Lines keep 7.0.2's columns and order.
 *
 * Changes from 7.0.2: "BAD MED REC NUMBER" is reported (7.0.2 logged it where
 * nothing read it), and an "UNDEFINED PROCEDURE" line no longer overwrites the
 * account's columns used by the account's later lines.
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

use Cmsvt\OpenEMR\Modules\RriBilling\Account\StepThreePrompts;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ChargeMaster;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctors;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final readonly class ChargeChecks
{
    private const NOT_READ = '00';
    private const AUC_CODE = 'G1004';

    public function __construct(
        private AccountFile $accounts,
        private ReferringDoctors $doctors,
        private ChargeMaster $chargeMaster,
        private StepThreePrompts $prompts,
    ) {
    }

    /**
     * @param array<int, OrdfileLine> $lines the ordfile after the dangling credit pairs are taken out
     * @param array<int, OrdfileLine> $firstCharges each account's first charge
     * @return list<string> file245, sorted
     */
    public function check(array $lines, array $firstCharges): array
    {
        $rows = [];
        foreach ($firstCharges as $first) {
            $account = $this->accounts->find(str_pad($first->ord8, 8, '0', STR_PAD_LEFT));
            if ($account === null) {
                if (!$this->prompts->confirm("BAD MRN, THIS CAN'T BE! LOOK IN ERRORFILE AFTER", true)) {
                    break;
                }
                $rows[] = FileRow::fromText(substr($first->toText(), 0, 28) . $first->dateT->format('Ymd'), ' ');
                continue;
            }
            $actno = str_pad($account->actno, 8, '0', STR_PAD_LEFT);
            $row = new FileRow($first->dateT->format('Ymd'), $actno, substr($account->garname, 0, 24), '', '', ' ');
            if (!ctype_digit($account->actno) || (int) $account->actno === 0) {
                $rows[] = $row->with('BAD MED REC NUMBER', $actno);
                continue;
            }

            $candidates = [];
            foreach ($lines as $line) {
                if ($line->ord8 !== $actno) {
                    continue;
                }
                $date = $line->dateT->format('Ymd');
                $npi = $this->doctors->npiForRefkey($line->ref);
                if ($npi === null) {
                    $row = $row->withDate($date);
                    $rows[] = $row->with($line->ref, 'BAD REF');
                } elseif ($npi === '') {
                    $row = $row->withDate($date);
                    $rows[] = $row->with($line->ref, 'NO NPI');
                } elseif ($line->docp === self::NOT_READ && $line->cpt !== self::AUC_CODE) {
                    $row = $row->withDate($date);
                    $rows[] = $row->with($line->ref, 'NOT READ');
                }
                $entry = $this->chargeMaster->findAnyModifier($line->proc, $line->cpt);
                if ($entry === null) {
                    $rows[] = FileRow::fromText(
                        'UNDEFINED PROCEDURE FOR ' . $line->ord8 . ' ' . $line->proc . ' ' . $line->cpt . ' We should ask RRMC for the cpt/hcpcs.',
                        ''
                    );
                    continue;
                }
                if ($entry->isZeroDollar() && $line->cpt === self::AUC_CODE) {
                    continue;
                }
                $candidates[] = $line;
            }

            $marked = [];
            $count = count($candidates);
            for ($i = 0; $i < $count; $i++) {
                for ($j = $i + 1; $j < $count; $j++) {
                    $a = $candidates[$i];
                    $b = $candidates[$j];
                    if (
                        !isset($marked[$i]) && !isset($marked[$j])
                        && $a->dateT->format('Ymd') === $b->dateT->format('Ymd')
                        && $a->proc === $b->proc && $a->mod2 === $b->mod2
                    ) {
                        $rows[] = $row->with('SAME CHARGES?', $b->proc . '/' . $b->dateT->format('Ymd') . 'ZZ');
                        $marked[$j] = true;
                    }
                }
            }
        }

        usort($rows, static fn(FileRow $a, FileRow $b): int => strcmp(str_pad($a->err3, 22), str_pad($b->err3, 22))
            ?: strcmp(substr(str_pad($a->err0, 8), 0, 7), substr(str_pad($b->err0, 8), 0, 7))
            ?: strcmp(str_pad($a->err2, 24), str_pad($b->err2, 24))
            ?: strcmp($a->toText(), $b->toText()));
        return array_map(static fn(FileRow $row): string => $row->toText(), $rows);
    }
}
