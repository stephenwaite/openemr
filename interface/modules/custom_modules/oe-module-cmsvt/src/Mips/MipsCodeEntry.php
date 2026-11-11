<?php

/**
 * One tracked code on a fee sheet: the code, its encounter and the encounter's day.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class MipsCodeEntry
{
    public function __construct(
        public string $code,
        public string $day,
        public int $encounter,
    ) {
    }

    /** "M1388 2026-02-11" */
    public function label(): string
    {
        return $this->code . ' ' . $this->day;
    }

    /**
     * @param list<self> $entries oldest first
     * @param list<string> $codes
     * @return list<self>
     */
    public static function filter(array $entries, array $codes, string $from, string $to): array
    {
        return array_values(array_filter(
            $entries,
            static fn(self $e): bool => in_array($e->code, $codes, true) && $e->day >= $from && $e->day <= $to
        ));
    }

    /**
     * @param list<self> $entries
     * @return list<string>
     */
    public static function labels(array $entries): array
    {
        return array_map(static fn(self $e): string => $e->label(), $entries);
    }
}
