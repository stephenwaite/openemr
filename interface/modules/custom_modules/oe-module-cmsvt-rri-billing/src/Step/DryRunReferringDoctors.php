<?php

/**
 * Referring doctors for a dry run: found as usual, but "adding" one only makes
 * a placeholder (user 0, key "<initial>??"), so nothing is written.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Step;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\PersonName;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctor;
use Cmsvt\OpenEMR\Modules\RriBilling\Lookup\ReferringDoctors;

final readonly class DryRunReferringDoctors implements ReferringDoctors
{
    public function __construct(private ReferringDoctors $doctors)
    {
    }

    public function findByNpi(string $npi): array
    {
        return $this->doctors->findByNpi($npi);
    }

    public function add(PersonName $name, string $npi): ReferringDoctor
    {
        return new ReferringDoctor(0, substr($name->full, 0, 1) . '??', $name->last, $name->first, $npi);
    }
}
