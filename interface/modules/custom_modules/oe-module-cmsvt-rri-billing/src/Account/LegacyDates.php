<?php

/**
 * The old system's date guesses for six-digit dates, ported as they were.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final class LegacyDates
{
    /**
     * MMDDYY as YYYYMMDD, 20YY unless that date is after today (then 19YY). The
     * parts are copied as they are, so '000000' gives '20000000' (7.0.2's
     * CMSVTDateUtils::getEstimatedDateFromTwoDigitYearDate).
     */
    public static function yyyymmdd(string $mmddyy, \DateTimeImmutable $today): string
    {
        $mm = substr($mmddyy, 0, 2);
        $dd = substr($mmddyy, 2, 2);
        $yy = substr($mmddyy, 4, 2);
        $century = '20';
        if (ctype_digit($mm . $dd . $yy) && strlen($mm . $dd . $yy) === 6) {
            // Out-of-range parts roll over, as strtotime() did.
            $date = $today->setTime(0, 0)->setDate(2000 + (int) $yy, (int) $mm, (int) $dd);
            if ($date > $today) {
                $century = '19';
            }
        }
        return $century . $yy . $mm . $dd;
    }

    /** Six digits: what 7.0.2 could read as a date (anything else became '000000'). */
    public static function isReadable(string $mmddyy): bool
    {
        return strlen($mmddyy) === 6 && ctype_digit($mmddyy);
    }

    /**
     * The old relate code from sex and MMDDYY birth date: over 23 is an adult
     * (2 male, K female), otherwise a child (4, M). The age arithmetic is the
     * old system's, two-digit years and all.
     */
    public static function relateCode(string $sex, string $mmddyy, \DateTimeImmutable $today): string
    {
        $mm = (int) substr($mmddyy, 0, 2);
        $dd = (int) substr($mmddyy, 2, 2);
        $yy = (int) substr($mmddyy, 4, 2);
        $birth = $today->setDate(2000 + $yy, $mm, $dd);
        $century = $today < $birth ? 19 : 20;
        $currentMonth = (int) $today->format('m');
        $currentDay = (int) $today->format('d');
        $age = 100 * (intdiv((int) $today->format('Y'), 100) - $century) + ((int) $today->format('y') - $yy);
        if ($mm > $currentMonth || ($mm === $currentMonth && $dd > $currentDay)) {
            $age--;
        }
        $adult = $age > 23;
        if ($sex === 'F') {
            return $adult ? 'K' : 'M';
        }
        return $adult ? '2' : '4';
    }
}
