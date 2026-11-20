<?php

/**
 * A money amount as X12 writes it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\X12;

final class X12Amount
{
    /**
     * The amount with no leading zero, as the claims sent before OpenEMR had
     * it ("166.67", ".50", "-.50"), except that zero is "0": the X12 decimal
     * type wants at least one digit, and ".00" was taken as no amount at all.
     */
    public static function format(float|int|string $amount): string
    {
        $formatted = sprintf('%.2f', is_numeric($amount) ? (float) $amount : 0.0);
        if ((float) $formatted == 0.0) {
            return '0';
        }
        return preg_replace('/^(-?)0+(?=\.)/', '$1', $formatted) ?? $formatted;
    }
}
