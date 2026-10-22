<?php

/**
 * Where a study was done, from the ordfile's inpatient/outpatient code
 * (iopat), with the encounter class and calendar category step 6 files it
 * under.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

/** Backed by the CMS place-of-service code saved on the encounter. */
enum PlaceOfService: int
{
    case InpatientHospital = 21;
    case OutpatientHospital = 22;
    case EmergencyRoom = 23;

    /** E, 5 or 3; null for any other code (the biller is asked). */
    public static function fromIopat(string $iopat): ?self
    {
        return match (trim($iopat)) {
            'E' => self::EmergencyRoom,
            '5' => self::OutpatientHospital,
            '3' => self::InpatientHospital,
            default => null,
        };
    }

    /** The _ActEncounterCode list option. */
    public function classCode(): string
    {
        return match ($this) {
            self::InpatientHospital => 'IMP',
            self::OutpatientHospital => 'AMB',
            self::EmergencyRoom => 'EMER',
        };
    }

    /** pc_constant_id of the calendar category table.sql adds for it. */
    public function category(): string
    {
        return match ($this) {
            self::InpatientHospital => 'inpatient_visit',
            self::OutpatientHospital => 'outpatient_visit',
            self::EmergencyRoom => 'emergency_visit',
        };
    }
}
