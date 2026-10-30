<?php

/**
 * Where posted Gifford encounters are placed: reading facility, billing facility, visit category.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Post;

use OpenEMR\Common\Database\QueryUtils;

final readonly class PostSettings
{
    /** Office Visit, form_encounter.pc_catid's default. */
    public const VISIT_CATEGORY_ID = 5;

    private function __construct(
        public int $facilityId,
        public string $facilityName,
        public int $billingFacilityId,
        public int $visitCategoryId,
    ) {
    }

    /**
     * The settings, or null when either facility is unset (0) or not a facility on the
     * site, in which case lines are staged but nothing is posted.
     */
    public static function load(int $facilityId, int $billingFacilityId): ?self
    {
        if ($facilityId <= 0 || $billingFacilityId <= 0) {
            return null;
        }
        $name = QueryUtils::fetchSingleValue('SELECT `name` FROM `facility` WHERE `id` = ?', 'name', [$facilityId]);
        $billing = QueryUtils::fetchSingleValue('SELECT `id` FROM `facility` WHERE `id` = ? AND `billing_location` = 1', 'id', [$billingFacilityId]);
        if (!is_string($name) || !is_numeric($billing)) {
            return null;
        }

        return new self($facilityId, $name, $billingFacilityId, self::VISIT_CATEGORY_ID);
    }
}
