<?php

/**
 * Turns a charge into its ordfile line (7.0.2
 * RRMC006Processor::generateOrderRecordForChargeRecord, COBOL B1-1): place of
 * service, referring doctor, the codes to bill, the account's next sequence
 * number, and the work comp records for work comp insurances.
 *
 * Messages keep 7.0.2's wording (the ED message's misspelling corrected). As in step 3's other checks the fields are
 * compared trimmed, so "no admit date" and "under observation" now fire;
 * 7.0.2 compared padded fields and never matched them. A work comp secondary
 * whose insurer has no name uses the tertiary, which 7.0.2 meant to do but
 * called a method that doesn't exist (an Error that stopped the run).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyCode;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeLine;

final class OrdfileBuilder
{
    /** RRMC's ED doctors: their referrals are billed as emergency. */
    private const ED_REFKEYS = [
        'A3Z', 'B1T', 'B51', 'B7C', 'D55', 'D3Z', 'F4J', 'F2S', 'GOT', 'G0T', 'G0a', 'G36', 'G4U', 'H1B',
        'H27', 'J06', 'L4Q', 'M8S', 'R1D', 'R2A', 'SAH', 'S70', 'S91', 'SAG', 'S1O', 'V12', 'W2I', 'Z01',
    ];
    private const WORK_COMP_RRMC_CODES = ['00931', '00932'];
    private const WORK_COMP_CODES = ['075', '076', '095'];

    private const OUTPATIENT = '5';
    private const INPATIENT = '3';
    private const EMERGENCY = 'E';

    /** @var array<string, true> ordfile keys used this run */
    private array $keys = [];

    /**
     * @param string $readingDoctorCode the ordfile's docp for every charge
     */
    public function __construct(private readonly string $readingDoctorCode)
    {
    }

    /**
     * The accident date for the account's lines (7.0.2 generateOrdFileDateA).
     */
    public static function accidentDate(ChargeFileAccount $file): string
    {
        return $file->visit->accidentDate?->format('Ymd') ?? '00000000';
    }

    public function charge(
        ChargeFileAccount $file,
        AccountRecord $account,
        ChargeLine $charge,
        string $refkey,
        string $accidentDate,
        \DateTimeImmutable $reportDate,
        \DateTimeImmutable $today,
    ): OrdfileCharge {
        $errors = [];
        $guarantor = $file->guarantor->name->full;
        $admit = trim($file->visit->admitDateYmd());

        $iopat = self::OUTPATIENT;
        if ($charge->placeOfService === 'EMER') {
            $iopat = self::EMERGENCY;
        }
        if ($charge->placeOfService === 'INPT') {
            $iopat = self::INPATIENT;
            if ($admit === '') {
                $errors[] = $guarantor . ' WAS SENT AS INPT WITH NO ADMIT DATE THIS MUST BE FIXED OR CLAIM FILE WILL REJECT.';
            }
        }
        if ($charge->observation === 'OBSERV') {
            $errors[] = $guarantor . ' WAS UNDER OBSERVATION, USING POS OUTP FOR CPT ' . $charge->cpt . ' AND DATE ' . $charge->dateOfServiceRaw;
        }
        if (in_array($refkey, self::ED_REFKEYS, true) && $iopat !== self::EMERGENCY) {
            $iopat = self::EMERGENCY;
            $errors[] = $guarantor . ' WAS REFERRED FROM ED BY ' . $refkey . ' CHANGING POS TO E';
        }

        $cpt = $charge->cpt;
        if ($cpt === '') {
            $cpt = $charge->hcpcs;
            if ($cpt === '') {
                $errors[] = $guarantor . ' HAD NO CPT ' . $charge->cpt . ' AND NO HCPCS TO BILL WITH ' . $charge->hcpcs . '. Call somebody';
                return new OrdfileCharge(null, [], $errors);
            }
        }

        $ord8 = str_pad($account->actno, 8, '0', STR_PAD_LEFT);
        $sequence = 1;
        while (isset($this->keys[$ord8 . sprintf('%03d', $sequence)])) {
            $sequence++;
        }
        $ord3 = sprintf('%03d', $sequence);
        $this->keys[$ord8 . $ord3] = true;

        $line = new OrdfileLine(
            $ord8,
            $ord3,
            substr($charge->cdm, 0, 4),
            $charge->isCancel() ? '-' : ' ',
            $refkey,
            $iopat,
            $accidentDate,
            $charge->dateOfService ?? $today,
            $admit,
            '0001',
            substr($charge->clinical, 0, 38),
            $this->readingDoctorCode,
            substr($file->visit->rrmcDiagnosis, 0, 17),
            $file->visitNumber,
            $charge->modifier1,
            $charge->modifier2,
            $charge->modifier3,
            $reportDate->format('Ymd'),
            substr($cpt, 0, 5),
            $charge->accession,
        );

        $workComp = [];
        if (
            in_array($file->primaryInsurance->payerCode, self::WORK_COMP_RRMC_CODES, true)
            || LegacyCode::oneOf($account->prins, self::WORK_COMP_CODES)
        ) {
            $workComp[] = new WorkCompRecord($ord8, $line->dateT, $line->proc, $file->primaryInsurance);
            if ($file->primaryInsurance->payerName === '') {
                $errors[] = $account->garname . ' BLANK WORK COMP1 ADDRESS ' . $account->prins . ' ' . $account->seins;
            }
        }
        if (LegacyCode::oneOf($account->seins, self::WORK_COMP_CODES)) {
            $insurance = $file->secondaryInsurance->payerName === '' ? $file->tertiaryInsurance : $file->secondaryInsurance;
            $workComp[] = new WorkCompRecord($ord8, $line->dateT, $line->proc, $insurance);
        }

        return new OrdfileCharge($line, $workComp, $errors);
    }
}
