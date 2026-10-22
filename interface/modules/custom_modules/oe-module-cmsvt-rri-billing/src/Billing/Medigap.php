<?php

/**
 * Step 6's Medigap secondaries (COBOL rri242): a Medicare (003) primary whose
 * secondary is one of the Medigap plans below becomes secondary 062, assigned,
 * with that plan's Medigap id in the account's group. As in 7.0.2 this changes
 * only the account file (cmsvt_actfile), not the patient's insurance in
 * OpenEMR, and the id goes in the primary group (prgroup).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

use Cmsvt\OpenEMR\Modules\RriBilling\Account\AccountRecord;
use Cmsvt\OpenEMR\Modules\RriBilling\Account\LegacyCode;

final class Medigap
{
    public const MEDIGAP = '062';

    private const MEDICARE = '003';

    /** Secondaries that are never Medigap. */
    private const NOT_MEDIGAP = ['001', '004', '064', '005'];

    /** Secondary 108 with this group is a Medigap plan of its own. */
    private const GROUP_108 = ['0038278', '038278', '38278'];
    private const MEDIGAP_ID_108 = '0005037';

    /** Medigap id by secondary. */
    private const MEDIGAP_ID = [
        '025' => '0005034',
        '116' => '0000567',
        '141' => '0099003',
        '209' => '0000343',
        '216' => '0005004',
        '217' => '0001177',
        '218' => '0000172',
        '220' => '0005004',
        '226' => '0005154',
        '227' => '0000557',
        '228' => '0000582',
        '230' => '0005023',
        '231' => '0001110',
        '234' => '0000762',
        '236' => '0000873',
        '237' => '0000762',
        '238' => '0005041',
        '239' => '0000111',
        '240' => '0005162',
        '241' => '0005034',
        '245' => '0000701',
        '246' => '0005012',
    ];

    /** The Medigap id for this account, or null when it has no Medigap secondary. */
    public static function idFor(AccountRecord $account): ?string
    {
        if (!LegacyCode::same($account->prins, self::MEDICARE) || LegacyCode::oneOf($account->seins, self::NOT_MEDIGAP)) {
            return null;
        }
        if (LegacyCode::same($account->seins, '108')) {
            return in_array(trim($account->segroup), self::GROUP_108, true) ? self::MEDIGAP_ID_108 : null;
        }
        foreach (self::MEDIGAP_ID as $secondary => $id) {
            if (LegacyCode::same($account->seins, (string) $secondary)) {
                return $id;
            }
        }
        return null;
    }

    /** Makes the account's secondary Medigap with this id. */
    public static function apply(AccountRecord $account, string $id): void
    {
        $account->prgroup = $id;
        $account->seins = self::MEDIGAP;
        $account->seassign = 'A';
    }

    /** The file242 line for an account made Medigap. */
    public static function reportLine(AccountRecord $account): string
    {
        return implode(' ', [
            str_pad(str_pad($account->actno, 8, '0', STR_PAD_LEFT), 9),
            str_pad($account->seins, 4),
            str_pad($account->secpol->toString(), 15),
            str_pad($account->segroup, 13),
            str_pad($account->segrpname, 16),
        ]);
    }
}
