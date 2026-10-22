<?php

/**
 * The account file in the site's cmsvt_actfile table.
 *
 * Saving writes every column, trimmed to the column's width, so the result
 * doesn't depend on the server's SQL mode (7.0.2 relied on the silent
 * truncation of a non-strict session). Unlike 7.0.2 it saves the secondary
 * policy as secpol (7.0.2 saved the primary's there) and the ZIP into
 * zip5/zip4 (7.0.2 never wrote them).
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

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\PolicyNumber;
use OpenEMR\Common\Database\QueryUtils;

final class DatabaseAccountFile implements AccountFile
{
    /** Text columns and their widths (table.sql). */
    private const TEXT_COLUMNS = [
        'actno' => 12, 'garname' => 35, 'billadd' => 30, 'street' => 50, 'city' => 25, 'state' => 2,
        'zip5' => 5, 'zip4' => 4, 'phone' => 12, 'sex' => 1, 'relate' => 2, 'mstat' => 1, 'dob' => 8,
        'prmplr' => 4, 'prins' => 3, 'prassign' => 1, 'proffice' => 4, 'prgroup' => 10, 'pripol' => 20,
        'prname' => 35, 'prrelate' => 2, 'semplr' => 4, 'seins' => 3, 'seassign' => 1, 'seoffice' => 4,
        'segroup' => 15, 'secpol' => 20, 'sename' => 35, 'serelate' => 2, 'inspend' => 7, 'lastbill' => 8,
        'assignm' => 1, 'private' => 1, 'garno' => 10, 'prgrpname' => 20, 'segrpname' => 20, 'namekey' => 3,
    ];

    /** Numeric columns: written as numbers, or NULL when blank. */
    private const NUMBER_COLUMNS = ['collt', 'dunning', 'acctstat', 'billcycle', 'delete'];

    public function find(string $actno): ?AccountRecord
    {
        $row = QueryUtils::querySingleRow('SELECT * FROM `cmsvt_actfile` WHERE `actno` = ? LIMIT 1', [$actno], false);
        if ($row === false) {
            return null;
        }
        $text = static function (string $column) use ($row): string {
            $value = $row[$column] ?? null;
            return is_scalar($value) ? (string) $value : '';
        };
        $account = new AccountRecord();
        $account->actno = $text('actno');
        $account->garname = $text('garname');
        $account->billadd = $text('billadd');
        $account->street = $text('street');
        $account->city = $text('city');
        $account->state = $text('state');
        $account->zip = $text('zip4') === '' ? $text('zip5') : $text('zip5') . '-' . $text('zip4');
        $account->collt = $text('collt');
        $account->phone = $text('phone');
        $account->sex = $text('sex');
        $account->relate = $text('relate');
        $account->mstat = $text('mstat');
        $account->dob = $text('dob');
        $account->dunning = $text('dunning');
        $account->acctstat = $text('acctstat');
        $account->prmplr = $text('prmplr');
        $account->prins = $text('prins');
        $account->prassign = $text('prassign');
        $account->proffice = $text('proffice');
        $account->prgroup = $text('prgroup');
        $account->pripol = PolicyNumber::fromString($text('pripol'));
        $account->prname = $text('prname');
        $account->prrelate = $text('prrelate');
        $account->semplr = $text('semplr');
        $account->seins = $text('seins');
        $account->seassign = $text('seassign');
        $account->seoffice = $text('seoffice');
        $account->segroup = $text('segroup');
        $account->secpol = PolicyNumber::fromString($text('secpol'));
        $account->sename = $text('sename');
        $account->serelate = $text('serelate');
        $account->inspend = $text('inspend');
        $account->lastbill = $text('lastbill');
        $account->assignm = $text('assignm');
        $account->private = $text('private');
        $account->billcycle = $text('billcycle');
        $account->delete = $text('delete');
        $account->garno = ($row['garno'] ?? null) === null ? null : $text('garno');
        $account->prgrpname = $text('prgrpname');
        $account->segrpname = $text('segrpname');
        $account->namekey = $text('namekey');
        return $account;
    }

    /**
     * Inserts or replaces the account (by actno).
     */
    public function save(AccountRecord $account): void
    {
        [$zip5, $zip4] = array_pad(explode('-', $account->zip, 2), 2, '');
        $values = [
            'actno' => $account->actno, 'garname' => $account->garname, 'billadd' => $account->billadd,
            'street' => $account->street, 'city' => $account->city, 'state' => $account->state,
            'zip5' => $zip5, 'zip4' => $zip4, 'collt' => $account->collt, 'phone' => $account->phone,
            'sex' => $account->sex, 'relate' => $account->relate, 'mstat' => $account->mstat, 'dob' => $account->dob,
            'dunning' => $account->dunning, 'acctstat' => $account->acctstat, 'prmplr' => $account->prmplr,
            'prins' => $account->prins, 'prassign' => $account->prassign, 'proffice' => $account->proffice,
            'prgroup' => $account->prgroup, 'pripol' => $account->pripol->toString(), 'prname' => $account->prname,
            'prrelate' => $account->prrelate, 'semplr' => $account->semplr, 'seins' => $account->seins,
            'seassign' => $account->seassign, 'seoffice' => $account->seoffice, 'segroup' => $account->segroup,
            'secpol' => $account->secpol->toString(), 'sename' => $account->sename, 'serelate' => $account->serelate,
            'inspend' => $account->inspend, 'lastbill' => $account->lastbill, 'assignm' => $account->assignm,
            'private' => $account->private, 'billcycle' => $account->billcycle, 'delete' => $account->delete,
            'garno' => $account->garno, 'prgrpname' => $account->prgrpname, 'segrpname' => $account->segrpname,
            'namekey' => $account->namekey,
        ];
        $bind = [];
        foreach ($values as $column => $value) {
            if (in_array($column, self::NUMBER_COLUMNS, true)) {
                $bind[] = is_string($value) && ctype_digit($value) ? (int) $value : null;
            } elseif ($value === null) {
                $bind[] = null;
            } else {
                $bind[] = substr($value, 0, self::TEXT_COLUMNS[$column]);
            }
        }
        $columns = array_map(static fn(string $column): string => '`' . $column . '`', array_keys($values));
        $updates = array_map(static fn(string $column): string => $column . ' = VALUES(' . $column . ')', array_slice($columns, 1));
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_actfile` (' . implode(', ', $columns) . ') VALUES (' . implode(', ', array_fill(0, count($columns), '?'))
                . ') ON DUPLICATE KEY UPDATE ' . implode(', ', $updates),
            $bind
        );
    }
}
