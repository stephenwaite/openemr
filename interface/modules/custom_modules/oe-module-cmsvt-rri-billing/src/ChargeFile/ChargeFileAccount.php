<?php

/**
 * One account (visit) of RRMC's charge file: a ## record, its ++ record and
 * the $$ charges that follow, as RRMC sent them.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final readonly class ChargeFileAccount
{
    /**
     * @param string $visitNumber RRMC's eight-digit visit (FIN) number
     * @param string $mrn the medical record number with its hyphens removed
     * @param string $mrnRaw the eight-character field as sent
     * @param list<ChargeLine> $charges in file order
     */
    public function __construct(
        public string $visitNumber,
        public string $mrn,
        public string $mrnRaw,
        public ChargeFilePatient $patient,
        public ChargeFileGuarantor $guarantor,
        public ChargeFileEmployer $employer,
        public ChargeFileVisit $visit,
        public ChargeFileInsurance $primaryInsurance,
        public ChargeFileInsurance $secondaryInsurance,
        public ChargeFileInsurance $tertiaryInsurance,
        public ChargeFileInsurance $quaternaryInsurance,
        public array $charges,
    ) {
    }

    /**
     * @return list<ChargeFileInsurance> primary, secondary, tertiary, quaternary
     */
    public function insurances(): array
    {
        return [$this->primaryInsurance, $this->secondaryInsurance, $this->tertiaryInsurance, $this->quaternaryInsurance];
    }

    /**
     * @param list<ChargeLine> $charges
     */
    public function withCharges(array $charges): self
    {
        return new self(
            $this->visitNumber,
            $this->mrn,
            $this->mrnRaw,
            $this->patient,
            $this->guarantor,
            $this->employer,
            $this->visit,
            $this->primaryInsurance,
            $this->secondaryInsurance,
            $this->tertiaryInsurance,
            $this->quaternaryInsurance,
            $charges,
        );
    }
}
