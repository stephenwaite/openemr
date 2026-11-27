<?php

/**
 * What a visit's Misc Billing Options form says when the visit is an accident claim:
 * the related cause the 837P sends in CLM11 and the accident date it sends as DTP*439.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\EncounterRecord;

final readonly class AccidentOptions
{
    /** Claim filing indicators of the payers that only cover accidents: work comp, auto, liability. */
    private const WORK_COMP = 'WC';
    private const AUTO = 'AM';
    private const LIABILITY = ['LI', 'LM'];

    private function __construct(
        public bool $employmentRelated,
        public bool $autoAccident,
        public bool $otherAccident,
        public ?\DateTimeImmutable $accidentDate,
    ) {
    }

    /**
     * The options for a visit, or null when it is not an accident claim. It is one when
     * Gifford says work comp or gives an accident date, or when the primary payer is a
     * work comp, auto or liability one. One cause is set:
     * - employment related, when Gifford says work comp or the payer is a work comp one;
     * - else auto accident, when the payer is an auto one. The file has no state for
     *   it, so a person adds the state on the form before the claim goes;
     * - else other accident.
     *
     * @param string $primaryClaimType the primary payer's claim filing indicator (insurance_type_codes.claim_type); '' with no payer
     */
    public static function forVisit(EncounterRecord $visit, string $primaryClaimType): ?self
    {
        $type = strtoupper(trim($primaryClaimType));
        $accidentPayer = $type === self::WORK_COMP || $type === self::AUTO || in_array($type, self::LIABILITY, true);
        if (!$visit->workersComp && $visit->accidentDate === null && !$accidentPayer) {
            return null;
        }
        $employment = $visit->workersComp || $type === self::WORK_COMP;
        $auto = !$employment && $type === self::AUTO;

        return new self($employment, $auto, !$employment && !$auto, $visit->accidentDate);
    }
}
