<?php

/**
 * Updates the patient and insurance from step 3's account.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Patient;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileAccount;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;

interface PatientUpdates
{
    /**
     * @return list<string> errfile248 lines
     */
    public function update(
        ChargeFileAccount $file,
        AccountRecord $account,
        string $mrn,
        string $patientDob,
        string $patientSex,
        ReferringDoctor $doctor,
    ): array;
}
