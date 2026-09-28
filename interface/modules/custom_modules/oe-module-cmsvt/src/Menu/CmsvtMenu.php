<?php

/**
 * CMS menu adjustments: tab targets for billing screens and the Press Ganey export.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Menu;

use OpenEMR\Menu\MenuEvent;
use stdClass;

final class CmsvtMenu
{
    /**
     * Open these screens in their own tabs: url => [stock target, CMS target].
     */
    private const RETARGET = [
        '/interface/patient_file/front_payment.php' => ['enc', 'pay'],
        '/interface/billing/sl_eob_search.php' => ['bil', 'edi'],
        '/interface/billing/edih_view.php' => ['edi', 'edih'],
        '/interface/orders/list_reports.php' => ['pat', 'lab'],
    ];

    private const PRESS_GANEY_URL = '/interface/modules/custom_modules/oe-module-cmsvt/public/press_ganey_export.php';

    public function apply(MenuEvent $event): void
    {
        $menu = $event->getMenu();
        $this->walk($menu, []);
        $event->setMenu($menu);
    }

    /**
     * @param array<mixed> $items
     * @param list<string> $path labels of the parent items
     */
    private function walk(array $items, array $path): void
    {
        foreach ($items as $item) {
            if (!$item instanceof stdClass) {
                continue;
            }
            $url = is_string($item->url ?? null) ? $item->url : '';
            $target = is_string($item->target ?? null) ? $item->target : '';
            if (isset(self::RETARGET[$url]) && self::RETARGET[$url][0] === $target) {
                $item->target = self::RETARGET[$url][1];
            }

            $label = is_string($item->label ?? null) ? $item->label : '';
            $children = is_array($item->children ?? null) ? $item->children : [];
            if ($path === ['Reports'] && $label === 'Visits') {
                $item->children = $this->withPressGaney($children);
                $children = $item->children;
            }
            $this->walk($children, [...$path, $label]);
        }
    }

    /**
     * Insert the Press Ganey export after "Encounters" (or at the end).
     *
     * @param array<mixed> $children
     * @return list<mixed>
     */
    private function withPressGaney(array $children): array
    {
        $entry = new stdClass();
        $entry->label = xl('Press Ganey Export');
        $entry->menu_id = 'rep0';
        $entry->target = 'rep';
        $entry->url = self::PRESS_GANEY_URL;
        $entry->children = [];
        $entry->requirement = 0;
        $entry->acl_req = ['encounters', 'coding_a'];

        $result = [];
        $inserted = false;
        foreach ($children as $child) {
            $result[] = $child;
            if (!$inserted && $child instanceof stdClass && ($child->label ?? null) === 'Encounters') {
                $result[] = $entry;
                $inserted = true;
            }
        }
        if (!$inserted) {
            $result[] = $entry;
        }
        return $result;
    }
}
