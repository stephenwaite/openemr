<?php

/**
 * Reads RRMC's six-digit MMDDYY dates.
 *
 * The year is taken as 20YY unless that is after today, then 19YY (the 7.0.2
 * module's rule, so a 1990s accident date stays in the past).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final class ShortDate
{
    /**
     * @return ?\DateTimeImmutable midnight on that date, or null when the field is
     *                             blank or not a real MMDDYY date
     */
    public static function parse(string $mmddyy, \DateTimeImmutable $today): ?\DateTimeImmutable
    {
        if (preg_match('/^(\d\d)(\d\d)(\d\d)$/', trim($mmddyy), $parts) !== 1) {
            return null;
        }
        $month = (int) $parts[1];
        $day = (int) $parts[2];
        $year = 2000 + (int) $parts[3];
        if (!checkdate($month, $day, $year)) {
            return null;
        }
        $date = $today->setDate($year, $month, $day)->setTime(0, 0);
        if ($date > $today) {
            $date = $date->setDate($year - 100, $month, $day);
        }
        return $date;
    }
}
