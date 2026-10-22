<?php

/**
 * RRMC's referring doctor keys: the first letter of the name and two more
 * characters, counting 0-9 then A-Z (X00, X01 ... X09, X0A ... X0Z, X10 ...).
 *
 * 7.0.2 meant the same but called chr('A') after a 9 (a TypeError, so the
 * eleventh new key for a letter could not be made) and wrote '[' after a Z.
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

final class RefKeySequence
{
    /**
     * @param string $initial the doctor's first character
     * @param ?string $last the highest existing key with that initial, if any
     * @throws \OverflowException when the initial's keys are used up
     */
    public static function next(string $initial, ?string $last): string
    {
        if ($last === null || strlen($last) < 3) {
            return $initial . '00';
        }
        $third = self::after($last[2]);
        if ($third !== null) {
            return $initial . $last[1] . $third;
        }
        $second = self::after($last[1]);
        if ($second === null) {
            throw new \OverflowException('No referring doctor keys left for ' . $initial);
        }
        return $initial . $second . '0';
    }

    private static function after(string $character): ?string
    {
        return match (true) {
            $character === '9' => 'A',
            $character === 'Z' => null,
            ctype_digit($character), ctype_upper($character) => chr(ord($character) + 1),
            default => null,
        };
    }
}
