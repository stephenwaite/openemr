<?php

/**
 * The database rows a measure builds its report from.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsData
{
    /**
     * @param list<array<mixed>> $patients pid, lname, fname, mname, sex, DOB, dx_history; in display order
     * @param list<array<mixed>> $encounters pid, encounter, date, age, visit_codes; newest first
     * @param list<array<mixed>> $codes pid, encounter, code, encounter_date; oldest first
     * @param list<array<mixed>> $mentions pid, encounter_date: other visits the measure looks at
     *                                      (410: reason names a medication; 178 and 180: RA visits the year before)
     * @param list<array<mixed>> $ruleEntries pid, date, item, complete, result: clinical rule entries; oldest first
     * @param list<array<mixed>> $orders pid, control_id, date_transmitted: procedure orders; oldest first
     */
    public function __construct(
        public array $patients,
        public array $encounters,
        public array $codes,
        public array $mentions = [],
        public array $ruleEntries = [],
        public array $orders = [],
    ) {
    }
}
