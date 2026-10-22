<?php

/**
 * Where step 3 saves what it made besides the account and the patient: the
 * ordfile and its lines (cmsvt_ordfile, cmsvt_ordfile_record), work comp
 * addresses (cmsvt_compfile) and email/authorization rows (cmsvt_emailauthfile).
 *
 * Values are trimmed to their columns' widths.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

use OpenEMR\Common\Database\QueryUtils;

final class DatabaseStepThreeFiles implements StepThreeFiles
{
    public function createOrdfile(string $fileName): int
    {
        return QueryUtils::sqlInsert('INSERT INTO `cmsvt_ordfile` (`date_created`, `filename`) VALUES (NOW(), ?)', [substr($fileName, 0, 255)]);
    }

    public function saveOrdfileLine(int $ordfileId, OrdfileLine $line): int
    {
        return QueryUtils::sqlInsert(
            'INSERT INTO `cmsvt_ordfile_record` (`ordfile_id`, `accession`, `ord8`, `ord3`, `proc`, `ind`, `ref`, `iopat`,
                `dateA`, `dateT`, `dateAdmit`, `order`, `clinical`, `docp`, `admitDiag`, `visitNo`, `mod2`, `mod3`, `mod4`,
                `dateE`, `cpt`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $ordfileId,
                substr($line->accession, 0, 8),
                substr($line->ord8, 0, 8),
                substr($line->ord3, 0, 3),
                substr($line->proc, 0, 4),
                substr($line->ind, 0, 1),
                substr($line->ref, 0, 3),
                substr($line->iopat, 0, 1),
                substr($line->dateA, 0, 8),
                $line->dateT->format('Ymd'),
                substr($line->dateAdmit, 0, 8),
                substr($line->order, 0, 4),
                substr($line->clinical, 0, 38),
                substr($line->docp, 0, 2),
                substr($line->admitDiag, 0, 17),
                substr($line->visitNo, 0, 17),
                substr($line->mod2, 0, 2),
                substr($line->mod3, 0, 2),
                substr($line->mod4, 0, 2),
                substr($line->dateE, 0, 8),
                substr($line->cpt, 0, 5),
            ]
        );
    }

    public function saveWorkComp(WorkCompRecord $record): void
    {
        $insurance = $record->insurance;
        $address = $insurance->payerAddress;
        $values = [
            substr($record->medrec, 0, 8),
            $record->dateOfService->format('Ymd'),
            substr($record->proc, 0, 4),
            substr($insurance->payerName, 0, 25),
            substr($insurance->payerContact, 0, 25),
            substr($address->street, 0, 20),
            substr($address->suite, 0, 15),
            substr($address->city, 0, 20),
            substr($address->state, 0, 2),
            substr($address->zip, 0, 10),
            substr($insurance->payerPhone, 0, 12),
        ];
        $key = substr($record->key(), 0, 20);
        $exists = QueryUtils::querySingleRow('SELECT 1 FROM `cmsvt_compfile` WHERE `compkey` = ? LIMIT 1', [$key], false) !== false;
        if ($exists) {
            QueryUtils::sqlStatementThrowException(
                'UPDATE `cmsvt_compfile` SET `medrec` = ?, `date` = ?, `proc` = ?, `name` = ?, `contact` = ?, `addr1` = ?,
                    `addr2` = ?, `city` = ?, `state` = ?, `zip` = ?, `phone` = ? WHERE `compkey` = ?',
                [...$values, $key]
            );
            return;
        }
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_compfile` (`compkey`, `medrec`, `date`, `proc`, `name`, `contact`, `addr1`, `addr2`,
                `city`, `state`, `zip`, `phone`) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [$key, ...$values]
        );
    }

    public function saveEmailAuth(EmailAuthRecord $record): void
    {
        QueryUtils::sqlStatementThrowException(
            'INSERT INTO `cmsvt_emailauthfile` (`eakey`, `medrec`, `name`, `email`, `auth`, `date`, `ssn`)
                SELECT COALESCE(MAX(`eakey`), 0) + 1, ?, ?, ?, ?, ?, ? FROM `cmsvt_emailauthfile`',
            [
                substr($record->medrec, 0, 8),
                substr($record->name, 0, 24),
                substr($record->email, 0, 30),
                substr($record->authorization, 0, 20),
                $record->date,
                substr($record->ssn, 0, 9),
            ]
        );
    }
}
