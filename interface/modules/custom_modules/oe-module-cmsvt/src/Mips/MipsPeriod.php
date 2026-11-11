<?php

/**
 * A MIPS performance year, and date arithmetic on Y-m-d days around it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

use DateTimeImmutable;

final readonly class MipsPeriod
{
    public function __construct(public int $year)
    {
    }

    public function start(): string
    {
        return $this->year . '-01-01';
    }

    public function end(): string
    {
        return $this->year . '-12-31';
    }

    /** First second of the period, for comparisons with form_encounter.date. */
    public function startTs(): string
    {
        return $this->start() . ' 00:00:00';
    }

    /** Last second of the period, so encounters late on 12/31 count. */
    public function endTs(): string
    {
        return $this->end() . ' 23:59:59';
    }

    public function contains(string $day): bool
    {
        return $day >= $this->start() && $day <= $this->end();
    }

    /** A Y-m-d day moved by whole months (negative = earlier) or days. */
    public static function shift(string $day, int $months, int $days = 0): string
    {
        $date = new DateTimeImmutable($day);
        if ($months !== 0) {
            $date = $date->modify(sprintf('%+d months', $months));
        }
        if ($days !== 0) {
            $date = $date->modify(sprintf('%+d days', $days));
        }
        return $date->format('Y-m-d');
    }
}
