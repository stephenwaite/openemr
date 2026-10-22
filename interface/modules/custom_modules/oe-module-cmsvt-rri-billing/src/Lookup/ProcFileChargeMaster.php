<?php

/**
 * The charge master in the site's cmsvt_procfile table.
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

final class ProcFileChargeMaster implements ChargeMaster
{
    private const PROFESSIONAL_COMPONENT = '26';

    public function find(string $cdm, string $code): ?ChargeMasterEntry
    {
        // As in 7.0.2: exact matches, and a row stored with a NULL modifier
        // matches neither lookup.
        return $this->row($cdm, $code, self::PROFESSIONAL_COMPONENT) ?? $this->row($cdm, $code, '');
    }

    private function row(string $cdm, string $code, string $modifier): ?ChargeMasterEntry
    {
        $row = QueryUtils::querySingleRow(
            'SELECT `proc_amount` FROM `cmsvt_procfile` WHERE `proc_cdm` = ? AND `proc_cpt` = ? AND `proc_mod` = ? LIMIT 1',
            [$cdm, $code, $modifier],
            false
        );
        if ($row === false) {
            return null;
        }
        return new ChargeMasterEntry($cdm, $code, $modifier, self::cents($row['proc_amount'] ?? null));
    }

    private static function cents(mixed $amount): int
    {
        // DECIMAL(6,2): a numeric string; NULL counts as $0, as it did in 7.0.2.
        return is_numeric($amount) ? (int) round((float) $amount * 100) : 0;
    }
}
