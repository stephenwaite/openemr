<?php

/**
 * One charge of the ordfile (the old billing system's order file) that step 3
 * writes and steps 4-6 read back: the charge keyed by account and sequence.
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

final readonly class OrdfileLine
{
    /**
     * @param string $ord8 the account number, zero-filled to eight
     * @param string $ord3 the charge's sequence within the account, '001' up
     * @param string $proc the CDM
     * @param string $ind '-' for a cancel, ' ' otherwise
     * @param string $ref the referring doctor's RRMC key
     * @param string $iopat 5 outpatient, 3 inpatient, E emergency
     * @param string $dateA accident date YYYYMMDD, '00000000' when none
     * @param \DateTimeImmutable $dateT date of service
     * @param string $dateAdmit admit date YYYYMMDD (blank when none)
     * @param string $docp the reading doctor's code
     * @param string $dateE the report date YYYYMMDD
     * @param string $accession RRMC's accession, or a diagnosis for sites that send one there
     */
    public function __construct(
        public string $ord8,
        public string $ord3,
        public string $proc,
        public string $ind,
        public string $ref,
        public string $iopat,
        public string $dateA,
        public \DateTimeImmutable $dateT,
        public string $dateAdmit,
        public string $order,
        public string $clinical,
        public string $docp,
        public string $admitDiag,
        public string $visitNo,
        public string $mod2,
        public string $mod3,
        public string $mod4,
        public string $dateE,
        public string $cpt,
        public string $accession,
    ) {
    }

    public function key(): string
    {
        return $this->ord8 . $this->ord3;
    }

    /**
     * The fixed-width text line of the ordfile, as 7.0.2 wrote it (the visit
     * number field is seven wide but holds eight, so it isn't cut).
     */
    public function toText(): string
    {
        return str_pad($this->ord8, 8) . str_pad($this->ord3, 3) . str_pad($this->proc, 4) . str_pad($this->ind, 1)
            . str_pad($this->ref, 3) . str_pad($this->iopat, 1) . str_pad($this->dateA, 8) . $this->dateT->format('Ymd')
            . str_pad($this->dateAdmit, 8) . str_pad($this->order, 4) . str_pad($this->clinical, 38)
            . str_pad($this->docp, 2) . str_pad($this->admitDiag, 17) . str_pad($this->visitNo, 7)
            . str_pad($this->mod2, 2) . str_pad($this->mod3, 2) . str_pad($this->mod4, 2) . str_pad($this->dateE, 8)
            . str_pad($this->cpt, 5);
    }
}
