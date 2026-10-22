<?php

/**
 * How step 3 runs: interactively, or with --yes and its default insurances.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Account;

final readonly class StepThreeOptions
{
    /**
     * @param bool $assumeYes answer confirmations with yes and use the defaults below (--yes)
     * @param ?int $defaultPrimary insurance company id for a miscellaneous primary (--y-primary, 95 with --yes)
     * @param ?int $defaultSecondary the same for the secondary (--y-2ndary, 95 with --yes)
     */
    public function __construct(
        public bool $assumeYes = false,
        public ?int $defaultPrimary = null,
        public ?int $defaultSecondary = null,
    ) {
    }
}
