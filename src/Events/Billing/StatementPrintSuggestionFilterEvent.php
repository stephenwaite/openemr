<?php

/**
 * Lets listeners flag invoices (patient and encounter) whose statements should be
 * printed rather than emailed, for example because emailed statements for them
 * have gone unpaid.
 *
 * The statements screen leaves a flagged invoice's box unchecked and badges the
 * row, so a biller can email the checked set and then print the rest.
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

    /** @var array<int, array<int, int>> pid => encounter => number of unpaid email statements */
    private array $unpaidEmailCounts = [];

    public function suggestPrint(int $pid, int $encounter, int $unpaidEmailCount): void
    {
        $this->unpaidEmailCounts[$pid][$encounter] = $unpaidEmailCount;
    }

    public function shouldPrint(int $pid, int $encounter): bool
    {
        return isset($this->unpaidEmailCounts[$pid][$encounter]);
    }

    public function getUnpaidEmailCount(int $pid, int $encounter): int
    {
        return $this->unpaidEmailCounts[$pid][$encounter] ?? 0;
    }
}
