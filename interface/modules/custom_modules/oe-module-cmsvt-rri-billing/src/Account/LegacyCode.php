<?php

/**
 * Compares the old system's codes the way the 7.0.2 module's == did.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final class LegacyCode
{
    /**
     * Equal as PHP's == compares two strings: as numbers when both are numeric,
     * so "3", "03" and "003" are the same payer code, and RRMC's "00091" is "091".
     */
    public static function same(string $a, string $b): bool
    {
        if (is_numeric($a) && is_numeric($b)) {
            return (($a + 0) <=> ($b + 0)) === 0;
        }
        return $a === $b;
    }

    /**
     * @param list<string> $codes
     */
    public static function oneOf(string $code, array $codes): bool
    {
        foreach ($codes as $candidate) {
            if (self::same($code, $candidate)) {
                return true;
            }
        }
        return false;
    }

    /** A company id as the old three-digit code ('095'). */
    public static function forId(int $id): string
    {
        return str_pad((string) $id, 3, '0', STR_PAD_LEFT);
    }
}
