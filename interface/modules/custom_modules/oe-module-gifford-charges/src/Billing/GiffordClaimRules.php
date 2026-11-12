<?php

/**
 * The 837P service location for Gifford reads. A Gifford encounter's facility is
 * Gifford, where the image was done, and commercial claims send it as the service
 * location (loop 2310C, BCBSVT's guidance). Medicare and Medicaid claims leave 2310C
 * out, so the service location is the billing provider, where the radiologist reads.
 * Every other decision is the wrapped rules' (the site's, e.g. the CMS ones).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Billing;

use OpenEMR\Billing\Claim;
use OpenEMR\Billing\X12\Claim837PRules;

final readonly class GiffordClaimRules implements Claim837PRules
{
    /** Claim filing indicators (SBR09) billed from where the radiologist reads: Medicare A/B, Medicaid. */
    private const READ_LOCATION_CLAIM_TYPES = ['MA', 'MB', 'MC'];

    public function __construct(private Claim837PRules $inner, private int $giffordFacilityId)
    {
    }

    public static function isGiffordEncounter(Claim $claim, int $giffordFacilityId): bool
    {
        $facility = is_array($claim->facility) ? $claim->facility : [];
        $id = $facility['id'] ?? null;
        return $giffordFacilityId > 0 && is_numeric($id) && (int) $id === $giffordFacilityId;
    }

    public function sendsServiceFacility(Claim $claim, bool $default): bool
    {
        $claimType = $claim->claimType(0);
        if (
            self::isGiffordEncounter($claim, $this->giffordFacilityId)
            && is_string($claimType)
            && in_array(strtoupper(trim($claimType)), self::READ_LOCATION_CLAIM_TYPES, true)
        ) {
            return false;
        }
        return $this->inner->sendsServiceFacility($claim, $default);
    }

    public function submitterIdentifier(Claim $claim): ?string
    {
        return $this->inner->submitterIdentifier($claim);
    }

    public function requiresLastSeenDate(Claim $claim): bool
    {
        return $this->inner->requiresLastSeenDate($claim);
    }

    public function requiresReferringProvider(Claim $claim): bool
    {
        return $this->inner->requiresReferringProvider($claim);
    }

    public function reportsPriorPayerAdjustments(Claim $claim): bool
    {
        return $this->inner->reportsPriorPayerAdjustments($claim);
    }

    public function sendsClia(Claim $claim): bool
    {
        return $this->inner->sendsClia($claim);
    }

    public function sendsEpsdtAsNote(Claim $claim): bool
    {
        return $this->inner->sendsEpsdtAsNote($claim);
    }

    public function sendsOtherPayer(Claim $claim, int $ins): bool
    {
        return $this->inner->sendsOtherPayer($claim, $ins);
    }

    public function otherPayerIdentifier(Claim $claim, int $ins): ?string
    {
        return $this->inner->otherPayerIdentifier($claim, $ins);
    }

    public function sendsLineRenderingProvider(Claim $claim, int $prockey, bool $default): bool
    {
        return $this->inner->sendsLineRenderingProvider($claim, $prockey, $default);
    }

    public function sendsNdc(Claim $claim, int $prockey): bool
    {
        return $this->inner->sendsNdc($claim, $prockey);
    }

    public function lineAdjustmentReason(Claim $claim, int $ins, string $groupCode, string $reasonCode): string
    {
        return $this->inner->lineAdjustmentReason($claim, $ins, $groupCode, $reasonCode);
    }

    /**
     * @return list<array{group: string, reason: string, amount: float}>
     */
    public function extraLineAdjustments(Claim $claim, int $ins, int $prockey): array
    {
        return $this->inner->extraLineAdjustments($claim, $ins, $prockey);
    }

    /**
     * @return list<string>
     */
    public function warnings(Claim $claim): array
    {
        return $this->inner->warnings($claim);
    }
}
