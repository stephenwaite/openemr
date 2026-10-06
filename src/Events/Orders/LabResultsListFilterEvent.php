<?php

/**
 * Lets listeners change the defaults of Procedures → Electronic Reports
 * (interface/orders/list_reports.php). Defaults apply only until the user
 * picks a value.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Orders;

use Symfony\Contracts\EventDispatcher\Event;

final class LabResultsListFilterEvent extends Event
{
    public const EVENT_NAME = 'orders.lab_results_list.filter';

    /** Review filter values from the page's select: 1 all, 2 reviewed, 3 received/unreviewed, 4 sent/not received, 5 not sent. */
    public const REVIEWED_FILTERS = [1, 2, 3, 4, 5];

    private int $defaultMaxResultsPerLab = 10;
    private int $defaultReviewedFilter = 3;

    public function getDefaultMaxResultsPerLab(): int
    {
        return $this->defaultMaxResultsPerLab;
    }

    public function setDefaultMaxResultsPerLab(int $max): void
    {
        $this->defaultMaxResultsPerLab = max(0, min(50, $max));
    }

    public function getDefaultReviewedFilter(): int
    {
        return $this->defaultReviewedFilter;
    }

    public function setDefaultReviewedFilter(int $filter): void
    {
        if (in_array($filter, self::REVIEWED_FILTERS, true)) {
            $this->defaultReviewedFilter = $filter;
        }
    }
}
