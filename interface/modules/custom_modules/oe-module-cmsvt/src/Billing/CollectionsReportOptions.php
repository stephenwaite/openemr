<?php

/**
 * Hides the collection-agency export on sites that don't use an agency.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Billing;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Billing\CollectionsReportFilterEvent;

final readonly class CollectionsReportOptions
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(CollectionsReportFilterEvent $event): void
    {
        if ($this->globals->getBoolean(CmsvtGlobals::COLLECTIONS_HIDE_AGENCY_EXPORT)) {
            $event->setShowExportToCollections(false);
        }
    }
}
