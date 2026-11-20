<?php

/**
 * A postal address as given in a Gifford charge file.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class Address
{
    public function __construct(
        public string $street,
        public string $street2,
        public string $city,
        public string $state,
        public string $postalCode,
    ) {
    }
}
