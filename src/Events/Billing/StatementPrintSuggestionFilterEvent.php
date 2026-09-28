<?php

/**
 * Lets listeners flag patients whose statements should be printed rather than
 * emailed, for example because emailed statements have gone unpaid.
 *
 * The statements screen leaves a flagged patient's box unchecked and badges
 * the row, so a biller can email the checked set and then print the rest.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Billing;

use Symfony\Contracts\EventDispatcher\Event;

final class StatementPrintSuggestionFilterEvent extends Event
{
    public const EVENT_NAME = 'billing.statement.print_suggestion.filter';

    /** @var array<int, int> pid => number of unpaid email statements */
    private array $unpaidEmailCounts = [];

    public function suggestPrint(int $pid, int $unpaidEmailCount): void
    {
        $this->unpaidEmailCounts[$pid] = $unpaidEmailCount;
    }

    public function shouldPrint(int $pid): bool
    {
        return isset($this->unpaidEmailCounts[$pid]);
    }

    public function getUnpaidEmailCount(int $pid): int
    {
        return $this->unpaidEmailCounts[$pid] ?? 0;
    }
}
