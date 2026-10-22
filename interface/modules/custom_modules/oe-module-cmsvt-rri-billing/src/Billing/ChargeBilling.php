<?php

/**
 * Where step 6 finds patients and saves encounters and billing lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

interface ChargeBilling
{
    /** The patient whose public id is the account number without its leading zeros. */
    public function findPatient(string $actno): ?BillingPatient;

    /** Whether this ordfile line already has its billing line (step 6 was run on it before). */
    public function isBilled(int $recordId): bool;

    /**
     * Saves the charge's encounter, its CPT line and, when there is a
     * diagnosis, an ICD10 line, all or nothing.
     *
     * @return int the encounter number
     * @throws BillingException when OpenEMR refuses the encounter
     */
    public function bill(BillingCharge $charge): int;
}
