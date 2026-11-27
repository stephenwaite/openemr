<?php

/**
 * The records read from a fee file, and the lines that could not be read.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

final readonly class ProcFileParseResult
{
    /**
     * @param list<ProcFileRecord> $records
     * @param list<string> $errors one message per line that was not a record
     */
    public function __construct(
        public array $records,
        public array $errors,
    ) {
    }
}
