<?php

/**
 * Withholds visit details from encounter reports on sites configured to do so.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Encounter;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Encounter\EncounterReportFilterEvent;

final readonly class EncounterReportOptions
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(EncounterReportFilterEvent $event): void
    {
        if ($this->globals->getBoolean(CmsvtGlobals::ENCOUNTER_REPORT_HIDE_VISIT_DETAILS)) {
            $event->setShowVisitDetails(false);
        }
    }
}
