<?php

/**
 * A work comp insurer's address for one charge (the old COMP file), keyed by
 * account, date of service and CDM.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Ordfile;

use Cmsvt\OpenEMR\Modules\RriBilling\ChargeFile\ChargeFileInsurance;

final readonly class WorkCompRecord
{
    public function __construct(
        public string $medrec,
        public \DateTimeImmutable $dateOfService,
        public string $proc,
        public ChargeFileInsurance $insurance,
    ) {
    }

    public function key(): string
    {
        return $this->medrec . $this->dateOfService->format('Ymd') . $this->proc;
    }
}
