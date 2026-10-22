<?php

/**
 * A street address from the charge file, each part trimmed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile;

final readonly class Address
{
    /**
     * @param string $suite the second street line
     * @param string $zip as sent: five digits, or nine with or without a hyphen
     */
    public function __construct(
        public string $street,
        public string $suite,
        public string $city,
        public string $state,
        public string $zip,
    ) {
    }
}
