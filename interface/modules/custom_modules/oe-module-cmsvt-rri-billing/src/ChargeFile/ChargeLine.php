<?php

/**
 * One $$ charge (or cancel) record of the charge file, as RRMC sent it.
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

final readonly class ChargeLine
{
    private const CANCEL_INDICATOR = 'CAN';

    /**
     * @param string $indicator 'CAN' for a cancel, otherwise blank or another code
     * @param string $glCode R3-GLC; '0' is RRMC's AUC (appropriate use) line
     * @param string $cdm RRMC's four-character charge master number
     * @param ?\DateTimeImmutable $dateOfService null when the field isn't a real date
     * @param string $dateOfServiceRaw the MMDDYY field as sent, for messages
     * @param string $placeOfService RRMC's place: EMER, INPT, ...
     * @param string $readingDoctor the reading doctor's name; its first four
     *                              characters are the doctor prefix
     * @param string $location e.g. RVOC
     * @param string $accession the RRMC accession, or a diagnosis for sites that send one there
     */
    public function __construct(
        public string $indicator,
        public string $department,
        public string $glCode,
        public string $cdm,
        public ?\DateTimeImmutable $dateOfService,
        public string $dateOfServiceRaw,
        public string $units,
        public string $clinical,
        public string $placeOfService,
        public string $readingDoctor,
        public string $cpt,
        public string $hcpcs,
        public string $modifier1,
        public string $modifier2,
        public string $modifier3,
        public string $observation,
        public string $location,
        public string $accession,
    ) {
    }

    public function isCancel(): bool
    {
        return $this->indicator === self::CANCEL_INDICATOR;
    }

    /** The first four characters of the reading doctor's name (MITC, SHEL, ...). */
    public function doctorPrefix(): string
    {
        return substr($this->readingDoctor, 0, 4);
    }
}
