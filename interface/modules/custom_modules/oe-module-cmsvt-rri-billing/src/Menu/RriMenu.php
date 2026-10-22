<?php

/**
 * Adds the coding page to the Fees menu as "CMSVT Billing", for users with
 * Fees > Billing access.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Menu;

use OpenEMR\Menu\MenuEvent;
use stdClass;

final class RriMenu
{
    private const FEES_MENU_ID = 'feeimg';
    private const CODING_URL = '/interface/modules/custom_modules/oe-module-cmsvt-rri-billing/public/index.php';

    public function apply(MenuEvent $event): void
    {
        $menu = $event->getMenu();
        foreach ($menu as $item) {
            if ($item instanceof stdClass && ($item->menu_id ?? null) === self::FEES_MENU_ID) {
                $children = is_array($item->children ?? null) ? $item->children : [];
                $children[] = self::codingEntry();
                $item->children = $children;
                break;
            }
        }
        $event->setMenu($menu);
    }

    private static function codingEntry(): stdClass
    {
        $entry = new stdClass();
        $entry->label = xl('CMSVT Billing');
        $entry->menu_id = 'bill0';
        $entry->target = 'bill';
        $entry->url = self::CODING_URL;
        $entry->children = [];
        $entry->requirement = 0;
        $entry->acl_req = ['acct', 'bill'];
        return $entry;
    }
}
