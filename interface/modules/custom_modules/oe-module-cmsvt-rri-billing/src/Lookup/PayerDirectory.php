<?php

/**
 * Resolves RRMC's payer codes to the site's insurance companies.
 *
 * RRMC's codes are looked up in the hospital file (cmsvt_hospfile), whose
 * insurance key is the old billing system's code. On sites moved from the
 * old system (eis) that key is the insurance company's id.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

interface PayerDirectory
{
    /**
     * The insurance company for RRMC's payer code, or null when the code isn't
     * in the hospital file or its key names no insurance company.
     */
    public function companyForRrmcCode(string $rrmcCode): ?PayerCompany;
}
