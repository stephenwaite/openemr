<?php

/**
 * Lets listeners change the Billing Manager's default search when it is first opened.
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

final class BillingManagerDefaultsFilterEvent extends Event
{
    public const EVENT_NAME = 'billing.manager.defaults.filter';

    /**
     * Date-of-service window of the default search: null = today only (the stock
     * default), 0 = no date-of-service filter, N = the last N months.
     */
    private ?int $dosMonths = null;

    public function getDosMonths(): ?int
    {
        return $this->dosMonths;
    }

    public function setDosMonths(?int $dosMonths): void
    {
        $this->dosMonths = $dosMonths !== null ? max(0, $dosMonths) : null;
    }
}
