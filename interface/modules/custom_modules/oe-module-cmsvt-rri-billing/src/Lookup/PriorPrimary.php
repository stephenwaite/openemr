<?php

/**
 * The patient's current primary insurance in OpenEMR, which stands in for the
 * old system's guarantor file when RRMC sends a miscellaneous payer.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

final readonly class PriorPrimary
{
    /**
     * @param int $patientId patient_data.id (the old "garno")
     * @param string $provider the current primary's insurance company id, '' when there is none
     */
    public function __construct(
        public int $patientId,
        public string $provider,
    ) {
    }
}
