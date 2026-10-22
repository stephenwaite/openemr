<?php

/**
 * What step 3 made of one charge: its ordfile line (none when it has no code
 * to bill), the work comp records to save, and errfile248 lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

final readonly class OrdfileCharge
{
    /**
     * @param list<WorkCompRecord> $workComp in the order to save them
     * @param list<string> $errors
     */
    public function __construct(
        public ?OrdfileLine $line,
        public array $workComp,
        public array $errors,
    ) {
    }
}
