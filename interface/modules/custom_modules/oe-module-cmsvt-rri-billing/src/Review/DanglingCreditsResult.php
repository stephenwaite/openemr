<?php

/**
 * Step 4's dangling credit check: file222 and the lines left for the other checks.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

use Cmsvt\OpenEMR\Modules\RriBilling\Ordfile\OrdfileLine;

final readonly class DanglingCreditsResult
{
    /**
     * @param list<string> $file222 a cancel without its charge on one line; a cancel
     *                              with the charge it cancels on two
     * @param array<int, OrdfileLine> $remaining the ordfile without the matched pairs
     * @param list<string> $warnings accounts not in the account file
     */
    public function __construct(
        public array $file222,
        public array $remaining,
        public array $warnings,
    ) {
    }
}
