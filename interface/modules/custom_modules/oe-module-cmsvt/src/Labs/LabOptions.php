<?php

/**
 * CMS lab results handling: per-site patient matching and Electronic Reports
 * defaults, plus the filing rules every CMS site uses for results that arrive
 * without an order.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Labs;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Orders\Hl7ResultsImportFilterEvent;
use OpenEMR\Events\Orders\LabResultsListFilterEvent;

final readonly class LabOptions
{
    private const REVIEWED = 2;

    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function applyToImport(Hl7ResultsImportFilterEvent $event): void
    {
        $event->setMatchPatientByPubpid($this->globals->getBoolean(CmsvtGlobals::HL7_MATCH_PATIENT_BY_MRN));
        // Results that arrive without an order are filed under the lab's visit
        // number, without a new encounter and without a provider notice.
        $event->setExternalIdFromVisitNumber(true);
        $event->setCreateEncounterForResultsOnlyOrder(false);
        $event->setNotifyProviderOfResultsOnlyOrder(false);
    }

    public function applyToList(LabResultsListFilterEvent $event): void
    {
        $perLab = $this->globals->getInt(CmsvtGlobals::LAB_RESULTS_PER_LAB);
        if ($perLab > 0) {
            $event->setDefaultMaxResultsPerLab($perLab);
        }
        if ($this->globals->getBoolean(CmsvtGlobals::LAB_LIST_DEFAULT_REVIEWED)) {
            $event->setDefaultReviewedFilter(self::REVIEWED);
        }
    }
}
