<?php

/**
 * A qualifying performance-year visit on the MIPS 509 report.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Mips;

final readonly class Mips509Encounter
{
    /**
     * @param list<string> $measureCodes tracked codes on this fee sheet, "M1388 2026-02-11"
     */
    public function __construct(
        public int $encounter,
        public string $date,
        public string $age,
        public string $visitCodes,
        public array $measureCodes,
    ) {
    }
}
