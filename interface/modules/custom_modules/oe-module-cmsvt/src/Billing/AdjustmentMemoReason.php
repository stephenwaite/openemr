<?php

/**
 * Manual posting (sl_eob_invoice.php): store the adjustment reason's title in
 * the memo instead of its id, when the site's setting asks for it. For sites
 * whose Adjustment Reasons list uses codes as ids, so invoices and statements
 * read as text (ported from rel-830-sunflower d96e076974).
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
use OpenEMR\Events\Billing\InvoiceAdjustmentReasonEvent;

final readonly class AdjustmentMemoReason
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(InvoiceAdjustmentReasonEvent $event): void
    {
        if ($this->globals->getBoolean(CmsvtGlobals::ADJUSTMENT_MEMO_USES_TITLE)) {
            $event->useTitle();
        }
    }
}
