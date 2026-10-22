<?php

/**
 * Adds Fees > Gifford Charges, the review page, after the Billing Manager.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Review;

use OpenEMR\Menu\MenuEvent;
use stdClass;

final class GiffordMenu
{
    public const URL = '/interface/modules/custom_modules/oe-module-gifford-charges/public/review.php';

    public function apply(MenuEvent $event): void
    {
        $menu = $event->getMenu();
        foreach ($menu as $item) {
            if (!$item instanceof stdClass || ($item->menu_id ?? null) !== 'feeimg') {
                continue;
            }
            $children = is_array($item->children ?? null) ? $item->children : [];
            $result = [];
            $added = false;
            foreach ($children as $child) {
                $result[] = $child;
                if ($child instanceof stdClass && ($child->menu_id ?? null) === 'bil0') {
                    $result[] = self::entry();
                    $added = true;
                }
            }
            if (!$added) {
                $result[] = self::entry();
            }
            $item->children = $result;
        }
        $event->setMenu($menu);
    }

    private static function entry(): stdClass
    {
        $entry = new stdClass();
        $entry->label = xl('Gifford Charges');
        $entry->menu_id = 'gif0';
        $entry->target = 'gif';
        $entry->url = self::URL;
        $entry->children = [];
        $entry->requirement = 0;
        $entry->acl_req = ['acct', 'bill', 'write'];

        return $entry;
    }
}
