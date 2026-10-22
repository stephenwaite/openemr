<?php

/**
 * One row of RRMC's charge master (cmsvt_procfile).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

final readonly class ChargeMasterEntry
{
    /**
     * @param string $code CPT or HCPCS
     * @param string $modifier '26' (professional component) or ''
     * @param int $feeCents the charge master fee
     */
    public function __construct(
        public string $cdm,
        public string $code,
        public string $modifier,
        public int $feeCents,
    ) {
    }

    public function isZeroDollar(): bool
    {
        return $this->feeCents === 0;
    }
}
