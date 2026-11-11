<?php

/**
 * The guarantor or the employer on a Gifford charge row.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Model;

final readonly class PartyRecord
{
    /**
     * @param string $firstName empty for an employer
     */
    public function __construct(
        public string $lastName,
        public string $firstName,
        public Address $address,
    ) {
    }
}
