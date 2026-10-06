<?php

/**
 * Lets listeners adjust which actions the collections report offers.
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

final class CollectionsReportFilterEvent extends Event
{
    public const EVENT_NAME = 'billing.collections.report.filter';

    private bool $showExportToCollections = true;

    public function showExportToCollections(): bool
    {
        return $this->showExportToCollections;
    }

    public function setShowExportToCollections(bool $show): void
    {
        $this->showExportToCollections = $show;
    }
}
