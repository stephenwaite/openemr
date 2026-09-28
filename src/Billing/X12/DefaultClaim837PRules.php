<?php

/**
 * The stock 837P decisions (see Claim837PRules).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace OpenEMR\Billing\X12;

use OpenEMR\Billing\Claim;

class DefaultClaim837PRules implements Claim837PRules
{
    public function submitterIdentifier(Claim $claim): ?string
    {
        return null;
    }

    public function requiresLastSeenDate(Claim $claim): bool
    {
        return false;
    }

    public function requiresReferringProvider(Claim $claim): bool
    {
        return false;
    }

    public function sendsClia(Claim $claim): bool
    {
        // Required by Medicare when in-house labs are done.
        return $claim->claimType() === 'MB';
    }

    public function sendsEpsdtAsNote(Claim $claim): bool
    {
        return false;
    }

    public function sendsServiceFacility(Claim $claim, bool $default): bool
    {
        return $default;
    }

    public function sendsOtherPayer(Claim $claim, int $ins): bool
    {
        return true;
    }

    public function otherPayerIdentifier(Claim $claim, int $ins): ?string
    {
        return null;
    }

    public function sendsLineRenderingProvider(Claim $claim, int $prockey, bool $default): bool
    {
        return $default;
    }

    public function sendsNdc(Claim $claim, int $prockey): bool
    {
        return true;
    }

    public function lineAdjustmentReason(Claim $claim, int $ins, string $groupCode, string $reasonCode): string
    {
        return $reasonCode;
    }

    public function extraLineAdjustments(Claim $claim, int $ins, int $prockey): array
    {
        return [];
    }

    public function warnings(Claim $claim): array
    {
        return [];
    }
}
