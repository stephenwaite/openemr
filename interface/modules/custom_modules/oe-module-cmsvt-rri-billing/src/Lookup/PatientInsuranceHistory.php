<?php

/**
 * Looks up a patient's current primary insurance by MRN.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

interface PatientInsuranceHistory
{
    /**
     * @param string $pubpid the MRN without leading zeros
     * @return ?PriorPrimary null when no patient has that MRN
     */
    public function currentPrimary(string $pubpid): ?PriorPrimary;
}
