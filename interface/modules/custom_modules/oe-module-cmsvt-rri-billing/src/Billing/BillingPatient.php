<?php

/**
 * The OpenEMR patient an ordfile account's charges are billed to.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Billing;

final readonly class BillingPatient
{
    public function __construct(
        public int $pid,
        public string $uuid,
        public ?int $referringProviderId,
    ) {
    }
}
