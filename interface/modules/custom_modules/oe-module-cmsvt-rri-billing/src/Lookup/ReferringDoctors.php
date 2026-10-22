<?php

/**
 * Finds and adds referring doctors.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Lookup;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;

interface ReferringDoctors
{
    /**
     * @return list<ReferringDoctor> users with this NPI
     */
    public function findByNpi(string $npi): array;

    /**
     * Adds the doctor as an address book user with the next free RRMC key for
     * the first letter of the name (the COBOL P62).
     */
    public function add(PersonName $name, string $npi): ReferringDoctor;

    /**
     * The NPI cmsvt_refphy has for RRMC's key: null when the key isn't there,
     * '' when it has no NPI.
     */
    public function npiForRefkey(string $refkey): ?string;
}
