<?php

/**
 * A patient's current primary insurance in OpenEMR, by MRN (pubpid), as 7.0.2
 * read it: the newest primary policy of the patient with that pubpid.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Services\InsuranceService;

final readonly class DatabasePatientInsuranceHistory implements PatientInsuranceHistory
{
    public function __construct(private InsuranceService $insurance)
    {
    }

    public function currentPrimary(string $pubpid): ?PriorPrimary
    {
        $patient = QueryUtils::querySingleRow('SELECT `id`, `pid` FROM `patient_data` WHERE `pubpid` = ? LIMIT 1', [$pubpid], false);
        if ($patient === false || !is_numeric($patient['id'] ?? null) || !is_numeric($patient['pid'] ?? null)) {
            return null;
        }
        $policies = $this->insurance->getPoliciesOrganizedByTypeForPatientPid($patient['pid']);
        $primary = is_array($policies) ? ($policies['primary'] ?? null) : null;
        $current = is_array($primary) ? ($primary['current'] ?? null) : null;
        $provider = is_array($current) ? ($current['provider'] ?? null) : null;
        return new PriorPrimary((int) $patient['id'], is_scalar($provider) ? (string) $provider : '');
    }
}
