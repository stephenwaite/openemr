<?php

/**
 * Per-site ERA posting rules (sl_eob_process.php), from the module's settings:
 * - secondary payers that are Medicaid payers of last resort, whose
 *   adjustments are real write-offs (42 CFR 447.15) rather than notes, so no
 *   phantom patient balance is left behind. Only CO and PI: OA (e.g. OA-23,
 *   the prior payer's adjudication) and PR stay notes, since writing those
 *   off would credit again what the primary already settled;
 * - CO reasons that are contractual for particular procedures, so a line the
 *   payer paid nothing on posts instead of being flagged (e.g. CO-97 on 92015,
 *   bundled refraction).
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
use OpenEMR\Events\Billing\EraAdjustmentPolicyEvent;

final readonly class EraAdjustmentPolicy
{
    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(EraAdjustmentPolicyEvent $event): void
    {
        if (
            !$event->primary
            && in_array($event->groupCode, ['CO', 'PI'], true)
            && $event->payerId !== ''
            && in_array($event->payerId, $this->list(CmsvtGlobals::ERA_SECONDARY_WRITEOFF_PAYER_IDS), true)
        ) {
            $event->postSecondaryAsWriteOff();
            // Paying nothing as the payer of last resort is expected, not an error.
            $event->markContractual();
            $event->setMemo('Medicaid w/o ' . $event->groupCode . '-' . $event->reasonCode);
        }

        if (
            $event->groupCode === 'CO'
            && in_array(
                $event->reasonCode . ':' . strtoupper($event->procedureCode),
                $this->list(CmsvtGlobals::ERA_CONTRACTUAL_CO_BY_PROCEDURE),
                true
            )
        ) {
            $event->markContractual();
        }
    }

    /**
     * @return list<string>
     */
    private function list(string $global): array
    {
        $parts = preg_split('/[\s,]+/', strtoupper($this->globals->getString($global)), -1, PREG_SPLIT_NO_EMPTY);
        return $parts === false ? [] : $parts;
    }
}
