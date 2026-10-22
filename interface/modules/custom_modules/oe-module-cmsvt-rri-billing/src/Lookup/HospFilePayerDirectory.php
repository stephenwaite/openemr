<?php

/**
 * Payer lookups through the site's hospital file: RRMC code → hospital file
 * insurance key → insurance_companies.id (the key read as a number, so '003'
 * is company 3), and the assignment flag from the old insurance file, as the
 * 7.0.2 module's lookups did.
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
    public function insuranceKeyForRrmcCode(string $rrmcCode): ?string
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
        return is_string($key) ? trim($key) : '';
    }

    public function companyById(int $id): ?PayerCompany
    {
        $company = QueryUtils::querySingleRow(
            'SELECT c.`name`, c.`cms_id`, f.`insassign` FROM `insurance_companies` c
                LEFT JOIN `cmsvt_insfile` f ON f.`inskey` = c.`id`
                WHERE c.`id` = ? LIMIT 1',
            [$id],
            false
        );
        if ($company === false) {
            return null;
        }
        $cmsId = $company['cms_id'] ?? null;
        $assign = $company['insassign'] ?? null;
        $name = $company['name'] ?? null;
        return new PayerCompany(
            $id,
            is_string($cmsId) && trim($cmsId) !== '',
            is_string($assign) ? $assign : '',
            is_string($name) ? $name : ''
        );
    }

    public function companyForRrmcCode(string $rrmcCode): ?PayerCompany
    {
        $key = $this->insuranceKeyForRrmcCode($rrmcCode);
        if ($key === null) {
            return null;
        }
        return $this->companyById(ctype_digit($key) ? (int) $key : 0);
    }
}
