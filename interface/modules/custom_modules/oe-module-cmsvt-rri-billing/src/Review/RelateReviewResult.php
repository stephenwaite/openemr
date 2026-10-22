<?php

/**
 * Step 5's review: the table for the biller, w2, and the mismatches.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Review;

final readonly class RelateReviewResult
{
    /**
     * @param list<array{string, string, string, string}> $rows type, relate, name, account
     * @param list<string> $w2 accounts listed in full
     * @param list<array{string, string}> $mismatches guarantor line, subscriber line
     * @param list<string> $missing accounts not in the account file
     */
    public function __construct(
        public array $rows,
        public array $w2,
        public array $mismatches,
        public array $missing,
    ) {
    }
}
