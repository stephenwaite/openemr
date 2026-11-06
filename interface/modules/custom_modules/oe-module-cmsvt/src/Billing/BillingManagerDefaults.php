<?php

/**
 * Applies the site's default date-of-service window to the Billing Manager's first search.
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
use OpenEMR\Events\Billing\BillingManagerDefaultsFilterEvent;

final readonly class BillingManagerDefaults
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(BillingManagerDefaultsFilterEvent $event): void
    {
        $months = $this->globals->getInt(CmsvtGlobals::BILLING_MANAGER_DOS_MONTHS);
        if ($months >= 0) {
            $event->setDosMonths($months);
        }
    }
}
