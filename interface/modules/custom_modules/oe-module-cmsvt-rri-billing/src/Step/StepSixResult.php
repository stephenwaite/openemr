<?php

/**
 * What step 6 did: the Medigap changes (file242), the charges it could not
 * bill (errfile240) and how many it billed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

final readonly class StepSixResult
{
    /**
     * @param list<string> $file242 accounts given a Medigap secondary
     * @param list<string> $errors errfile240: charges not billed, and why
     * @param int $billed charges given an encounter and billing line now
     * @param int $billedBefore charges a previous run billed
     * @param int $paired charges left out with their cancel
     */
    public function __construct(
        public array $file242,
        public array $errors,
        public int $billed,
        public int $billedBefore,
        public int $paired,
    ) {
    }
}
