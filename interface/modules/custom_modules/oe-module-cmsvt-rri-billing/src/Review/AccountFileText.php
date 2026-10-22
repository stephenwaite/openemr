<?php

/**
 * An account as the old account file's fixed-width record (7.0.2
 * ActFileRecord::toString), for step 5's w2 listing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;

final class AccountFileText
{
    /** The first 309 characters, as rri247 listed them. */
    public static function line(AccountRecord $account): string
    {
        $fields = [
            [str_pad($account->actno, 8, '0', STR_PAD_LEFT), 8], [$account->garname, 24], [$account->billadd, 22],
            [$account->street, 22], [$account->city, 18], [$account->state, 12], [$account->zip, 9], [$account->collt, 1],
            [$account->phone, 10], [$account->sex, 1], [$account->relate, 1], [$account->mstat, 1], [$account->dob, 8],
            [$account->dunning, 1], [$account->acctstat, 1], ['', 4], [$account->prins, 3], [$account->prassign, 1],
            [$account->proffice, 4], [$account->prgroup, 10], [$account->pripol->toString(), 16], [$account->prname, 24],
            [$account->prrelate, 1], ['', 4], [$account->seins, 3], [$account->seassign, 1], [$account->trinsind, 1],
            [$account->trins, 3], [$account->segroup, 10], [$account->secpol->toString(), 16], [$account->sename, 24],
            [$account->serelate, 1], [$account->inspend, 7], [$account->lastbill, 8], [$account->assignm, 1],
            [$account->private, 1], [$account->billcycle, 1], [$account->delete, 1], ['', 3], [$account->garno ?? '', 8],
            [$account->prgrpname, 15], [$account->segrpname, 15], [$account->namekey, 3],
        ];
        $text = '';
        foreach ($fields as [$value, $width]) {
            $text .= str_pad($value, $width);
        }
        return substr($text, 0, 309);
    }
}
