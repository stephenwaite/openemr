<?php

/**
 * Step 6 (7.0.2's rri-250: rri249, rri242, rri240): Medigap secondaries for
 * each account's first charge, then an encounter and billing line for each
 * charge in the ordfile, ready for the coding page.
 *
 * Changes from 7.0.2: a charge step 4 pairs with its cancel is not billed,
 * nor is any cancel (the COBOL deleted the pairs from the ordfile before
 * rri240; 7.0.2 billed every line, the cancel as a positive charge of its
 * own); a cancel left without its charge goes to errfile240. A line a
 * previous run billed is skipped, so running the step again on the same
 * ordfile adds nothing (7.0.2 made new encounters and ICD10 lines each time,
 * and asked whether to update a billing line found by accession and code,
 * which could be another patient's). Unexpected errors stop the run; run it
 * again once fixed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingCharge;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\BillingException;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\ChargeBilling;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\Medigap;
use Cmsvt\OpenEMR\Modules\RriBilling\Billing\PlaceOfService;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\AccountFile;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;
use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\StoredOrdfile;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\DanglingCredits;
use Cmsvt\OpenEMR\Modules\RriBilling\Review\OrdfileReview;

final readonly class StepSix
{
    /**
     * @param string $readingDoctorCode the ordfile doctor code (docp) billed under $readingDoctorUser
     * @param int $readingDoctorUser the users id of the reading doctor
     * @param \Closure(PlaceOfService): int $facility the facility id for a place of service
     */
    public function __construct(
        private AccountFile $accounts,
        private ChargeBilling $billing,
        private string $readingDoctorCode,
        private int $readingDoctorUser,
        private \Closure $facility,
    ) {
    }

    /**
     * @param \Closure(OrdfileLine): PlaceOfService $askPlace for an iopat other than E, 5 or 3
     * @param ?\Closure(): void $advance called after each line
     */
    public function run(StoredOrdfile $ordfile, \Closure $askPlace, ?\Closure $advance = null): StepSixResult
    {
        $remaining = (new DanglingCredits($this->accounts))->check($ordfile)->remaining;
        $errors = [];

        $file242 = [];
        foreach (OrdfileReview::firstChargePerAccount($remaining) as $line) {
            $account = $this->accounts->find($line->ord8);
            if ($account === null) {
                continue;
            }
            $id = Medigap::idFor($account);
            if ($id !== null) {
                Medigap::apply($account, $id);
                $this->accounts->save($account);
                $file242[] = Medigap::reportLine($account);
            }
        }

        $billed = 0;
        $billedBefore = 0;
        foreach ($remaining as $recordId => $line) {
            if ($line->ind === '-') {
                $errors[] = self::errorLine($line, 'CANCEL WITHOUT ITS CHARGE, NOT BILLED');
            } elseif ($this->billing->isBilled($recordId)) {
                $billedBefore++;
            } else {
                $problem = $this->billLine($recordId, $line, $askPlace);
                if ($problem === null) {
                    $billed++;
                } else {
                    $errors[] = self::errorLine($line, $problem);
                }
            }
            if ($advance !== null) {
                $advance();
            }
        }

        return new StepSixResult($file242, $errors, $billed, $billedBefore, count($ordfile->lines) - count($remaining));
    }

    /**
     * @param \Closure(OrdfileLine): PlaceOfService $askPlace
     * @return ?string null when billed, else why not
     */
    private function billLine(int $recordId, OrdfileLine $line, \Closure $askPlace): ?string
    {
        if ($this->accounts->find($line->ord8) === null) {
            return 'NOT IN THE ACCOUNT FILE';
        }
        $patient = $this->billing->findPatient($line->ord8);
        if ($patient === null) {
            return 'PATIENT NOT FOUND';
        }
        $place = PlaceOfService::fromIopat($line->iopat) ?? $askPlace($line);
        $charge = new BillingCharge(
            $recordId,
            $line,
            $patient,
            $place,
            ($this->facility)($place),
            trim($line->docp) === $this->readingDoctorCode ? $this->readingDoctorUser : null,
        );
        try {
            $this->billing->bill($charge);
        } catch (BillingException $e) {
            return $e->getMessage();
        }
        return null;
    }

    private static function errorLine(OrdfileLine $line, string $problem): string
    {
        return $line->ord8 . $line->ord3 . ' ' . str_pad($line->proc, 4) . ' ' . str_pad($line->cpt, 5) . ' '
            . $line->dateT->format('Ymd') . ' ' . $problem;
    }
}
