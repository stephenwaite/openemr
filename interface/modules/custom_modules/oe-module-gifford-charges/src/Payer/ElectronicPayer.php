<?php

/**
 * What an electronic payer's insurance company carries: payer ID, X12 partner, address.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Payer;

use Cmsvt\OpenEMR\Modules\GiffordCharges\Model\Address;

final readonly class ElectronicPayer
{
    public function __construct(
        public string $payerId,
        public int $x12PartnerId,
        public Address $address,
    ) {
    }
}
