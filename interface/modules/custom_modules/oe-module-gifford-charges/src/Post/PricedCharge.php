<?php

/**
 * How a Gifford charge is billed on the site: modifiers, unit fee and description.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

final readonly class PricedCharge
{
    /**
     * @param string $modifier colon separated, as billing.modifier stores them ("26:RT")
     */
    public function __construct(
        public string $modifier,
        public int $unitFeeCents,
        public string $codeText,
    ) {
    }

    /**
     * billing.fee is the line total.
     */
    public function fee(int $units): string
    {
        $cents = $this->unitFeeCents * $units;

        return sprintf('%d.%02d', intdiv($cents, 100), $cents % 100);
    }
}
