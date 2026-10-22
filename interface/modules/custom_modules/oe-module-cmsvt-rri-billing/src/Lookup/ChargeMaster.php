<?php

/**
 * Finds a charge's CDM and code in RRMC's charge master.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

interface ChargeMaster
{
    /**
     * The professional component (modifier 26) row for the CDM and code, or else
     * the row without a modifier; null when neither exists.
     */
    public function find(string $cdm, string $code): ?ChargeMasterEntry;
}
