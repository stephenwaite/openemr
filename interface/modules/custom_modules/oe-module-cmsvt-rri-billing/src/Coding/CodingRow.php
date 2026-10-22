<?php

/**
 * One unbilled ordfile charge as the coding page shows it: what RRMC sent,
 * the patient and payer, and the provider and diagnoses the biller sets.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Coding;

final readonly class CodingRow
{
    /**
     * @param string $payer the primary insurance company id as the old three-digit code, 001 (self pay) when none
     */
    public function __construct(
        public int $recordId,
        public int $pid,
        public int $encounter,
        public ?int $providerId,
        public string $providerName,
        public string $mrn,
        public string $patientName,
        public string $dateOfService,
        public string $admitDate,
        public string $guarantorNumber,
        public string $procedureTitle,
        public string $procedureKey,
        public string $mod2,
        public string $mod3,
        public string $payer,
        public string $place,
        public string $subscriberDob,
        public string $clinical,
        public string $admitDiagnosis,
        public string $accession,
        public CodingDiagnosis $dx1,
        public CodingDiagnosis $dx2,
    ) {
    }
}
