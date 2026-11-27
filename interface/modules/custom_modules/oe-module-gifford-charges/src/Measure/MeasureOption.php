<?php

/**
 * One answer to a quality measure's question.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Measure;

final readonly class MeasureOption
{
    /**
     * @param int $value what is stored; the coder's code in the billing system
     * @param list<string> $claimCodes HCPCS G-codes added to the claim (claims measures)
     * @param ?string $acrCode the numerator code for the ACR registry row, or null when
     *                         the answer sends nothing (ACR measures)
     */
    public function __construct(
        public int $value,
        public string $label,
        public array $claimCodes,
        public ?string $acrCode,
    ) {
    }
}
