<?php

/**
 * A payer mnemonic on held lines with no insurance company mapped to it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Review;

final readonly class UnmappedPayer
{
    public function __construct(
        public string $mnemonic,
        public string $giffordName,
        public int $heldLines,
        public string $firstSeen,
    ) {
    }
}
