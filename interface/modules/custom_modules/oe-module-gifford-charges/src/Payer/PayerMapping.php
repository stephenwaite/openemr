<?php

/**
 * One Gifford payer mnemonic and the insurance company it becomes on the site.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

/**
 * An electronic payer has a payer ID, an X12 partner and an address; a paper payer
 * has none of them (billers fill them in when its first claim comes up).
 */
final readonly class PayerMapping
{
    public function __construct(
        public string $mnemonic,
        public string $name,
        public int $insTypeCode,
        public ?ElectronicPayer $electronic,
    ) {
    }
}
