<?php

/**
 * Step 3's account for one charge file account, or why there is none.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;

final readonly class AccountBuildResult
{
    /**
     * @param ?AccountRecord $account null when the patient was skipped (no referring doctor)
     * @param ?AccountRecord $existing the patient's account file row before this run, if any
     * @param string $mrn the MRN zero-filled to eight characters
     * @param string $patientDob MMDDYY, '000000' when the file's wasn't a date
     * @param string $patientSex F or M
     * @param list<string> $errors errfile248 lines (the old ERRFILE)
     * @param list<string> $insuranceErrors err248 lines (bad insurance info from RRMC, the old FILEOUT)
     */
    public function __construct(
        public ?AccountRecord $account,
        public ?AccountRecord $existing,
        public ?ReferringDoctor $referringDoctor,
        public string $mrn,
        public string $patientDob,
        public string $patientSex,
        public array $errors,
        public array $insuranceErrors,
    ) {
    }
}
