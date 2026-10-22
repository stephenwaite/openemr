<?php

/**
 * What a step 3 run made: the ordfile lines and the two error files' lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final readonly class StepThreeResult
{
    /**
     * @param list<OrdfileLine> $lines in the order they were made
     * @param list<string> $errors errfile248 (POS, policy numbers and other smart edits)
     * @param list<string> $insuranceErrors err248 (bad insurance information from RRMC)
     * @param int $patients accounts processed (not skipped)
     */
    public function __construct(
        public array $lines,
        public array $errors,
        public array $insuranceErrors,
        public int $patients,
    ) {
    }

    /**
     * @return list<OrdfileLine> sorted by account and sequence, as the ordfile is written
     */
    public function sortedLines(): array
    {
        $lines = $this->lines;
        usort($lines, static fn(OrdfileLine $a, OrdfileLine $b): int => strcmp($a->key(), $b->key()));
        return $lines;
    }
}
