<?php

/**
 * One melanoma patient on the MIPS 509 report.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class Mips509Patient
{
    /**
     * @param list<Mips509Encounter> $encounters qualifying visits in the performance year, newest first
     * @param list<string> $excisions excision CPTs in the lookback, "11602 (2026-03-14)"
     * @param list<string> $criteria1Codes "M1388 2026-02-11"
     * @param list<string> $criteria2Codes "M1393 2026-02-11"
     */
    public function __construct(
        public int $pid,
        public string $lname,
        public string $fname,
        public string $mname,
        public string $sex,
        public string $dob,
        public string $dxHistory,
        public array $encounters,
        public array $excisions,
        public array $criteria1Codes,
        public array $criteria2Codes,
        public string $criteria1Status,
        public string $criteria2Status,
    ) {
    }

    /** In the denominator: a melanoma diagnosis and a qualifying visit in the performance year. */
    public function inDenominator(): bool
    {
        return $this->encounters !== [];
    }
}
