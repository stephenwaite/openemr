<?php

/**
 * Lets listeners limit how much visit detail the encounter summary report shows.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Events\Encounter;

use Symfony\Contracts\EventDispatcher\Event;

final class EncounterReportFilterEvent extends Event
{
    public const EVENT_NAME = 'encounter.report.filter';

    /**
     * Whether category, reason, provider, referring provider and POS code are shown.
     */
    private bool $showVisitDetails = true;

    public function showVisitDetails(): bool
    {
        return $this->showVisitDetails;
    }

    public function setShowVisitDetails(bool $show): void
    {
        $this->showVisitDetails = $show;
    }
}
