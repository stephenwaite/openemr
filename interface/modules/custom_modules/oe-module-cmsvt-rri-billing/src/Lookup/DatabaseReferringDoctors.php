<?php

/**
 * Referring doctors as address book users (abook_type external_provider, the
 * RRMC key in notes) paired with cmsvt_refphy by NPI and key.
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

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Uuid\UuidRegistry;

final class DatabaseReferringDoctors implements ReferringDoctors
{
    public function findByNpi(string $npi): array
    {
        $rows = QueryUtils::fetchRecords(
            'SELECT u.`id`, u.`lname`, u.`fname`, u.`npi`, r.`refkey` FROM `users` u
                LEFT JOIN `cmsvt_refphy` r ON r.`npi` = u.`npi` AND r.`refkey` = u.`notes`
                WHERE u.`npi` = ? ORDER BY u.`id`',
            [$npi],
            true
        );
        $doctors = [];
        foreach ($rows as $row) {
            $text = static fn(string $column): string => is_scalar($row[$column] ?? null) ? (string) $row[$column] : '';
            $doctors[] = new ReferringDoctor((int) $text('id'), $text('refkey'), $text('lname'), $text('fname'), $text('npi'));
        }
        return $doctors;
    }

    public function add(PersonName $name, string $npi): ReferringDoctor
    {
        $initial = substr($name->full, 0, 1);
        [$refkey, $userId] = QueryUtils::inTransaction(static function () use ($name, $npi, $initial): array {
            $last = QueryUtils::fetchSingleValue(
                'SELECT `refkey` FROM `cmsvt_refphy` WHERE `refkey` LIKE ? ORDER BY `refkey` DESC LIMIT 1 FOR UPDATE',
                'refkey',
                [$initial . '%']
            );
            $refkey = RefKeySequence::next($initial, is_string($last) ? $last : null);
            QueryUtils::sqlStatementThrowException(
                'INSERT INTO `cmsvt_refphy` (`refkey`, `name`, `npi`) VALUES (?, ?, ?)',
                [$refkey, substr(str_replace(',', ';', $name->full), 0, 35), $npi]
            );
            $userId = QueryUtils::sqlInsert(
                'INSERT INTO `users` (`uuid`, `lname`, `fname`, `npi`, `abook_type`, `notes`, `authorized`)
                    VALUES (?, ?, ?, ?, ?, ?, 0)',
                [(new UuidRegistry(['table_name' => 'users']))->createUuid(), $name->last, $name->first, $npi, 'external_provider', $refkey]
            );
            return [$refkey, $userId];
        });
        return new ReferringDoctor($userId, $refkey, $name->last, $name->first, $npi);
    }
}
