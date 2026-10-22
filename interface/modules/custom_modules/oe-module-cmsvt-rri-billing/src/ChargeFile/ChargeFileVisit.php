<?php

/**
 * The visit part of a charge file account: admission, accident, referring
 * doctor and RRMC's diagnosis text.
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

final readonly class ChargeFileVisit
{
    /**
     * @param string $admitDateRaw MM?DD?YYYY as sent (any separator)
     * @param string $workComp work comp flag
     * @param ?\DateTimeImmutable $accidentDate null when blank or not a real date
     * @param string $accidentDateRaw MMDDYY as sent, for messages
     * @param string $referringIndicator R2-REFIND
     * @param string $doctorCode R2-DOC, RRMC's referring doctor code
     * @param string $authorization R1 authorization (after the secondary block)
     * @param string $admitDateDuplicateRaw the ++ record's copy of the admit date, YYMMDD
     * @param string $dischargeDateRaw YYMMDD as sent
     */
    public function __construct(
        public string $admitDateRaw,
        public string $admitTime,
        public string $workComp,
        public ?\DateTimeImmutable $accidentDate,
        public string $accidentDateRaw,
        public string $accidentTime,
        public PersonName $referringName,
        public string $referringNpi,
        public string $referringIndicator,
        public string $doctorCode,
        public string $rrmcDiagnosis,
        public string $authorization,
        public string $admitDateDuplicateRaw,
        public string $dischargeDateRaw,
    ) {
    }

    /**
     * The admit date as YYYYMMDD, taken by position from the MM?DD?YYYY field
     * (blank positions stay blank), as the 7.0.2 getAdmitDateAsYYMMDD did.
     */
    public function admitDateYmd(): string
    {
        $date = str_pad($this->admitDateRaw, 10);
        return substr($date, 6, 4) . substr($date, 0, 2) . substr($date, 3, 2);
    }
}
