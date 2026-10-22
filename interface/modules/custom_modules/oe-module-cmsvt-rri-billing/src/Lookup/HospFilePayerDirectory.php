<?php

/**
 * Payer lookups through the site's hospital file: RRMC code → hospital file
 * insurance key → insurance_companies.id (the key read as a number, so '003'
 * is company 3), as the 7.0.2 rrmc007 did.
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

final class HospFilePayerDirectory implements PayerDirectory
{
    public function companyForRrmcCode(string $rrmcCode): ?PayerCompany
    {
        $hospital = QueryUtils::querySingleRow(
            'SELECT `insurancekey` FROM `cmsvt_hospfile` WHERE `hospkey` = ? LIMIT 1',
            [$rrmcCode],
            false
        );
        if ($hospital === false) {
            return null;
        }
        $key = $hospital['insurancekey'] ?? null;
        $id = is_string($key) && ctype_digit(trim($key)) ? (int) trim($key) : 0;
        $company = QueryUtils::querySingleRow(
            'SELECT `cms_id` FROM `insurance_companies` WHERE `id` = ? LIMIT 1',
            [$id],
            false
        );
        if ($company === false) {
            return null;
        }
        $cmsId = $company['cms_id'] ?? null;
        return new PayerCompany($id, is_string($cmsId) && trim($cmsId) !== '');
    }
}
