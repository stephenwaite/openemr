<?php

/**
 * The Gifford charge import's settings (Admin > Config > Gifford Charge Import).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Globals;

use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Services\Globals\GlobalSetting;

final class GiffordGlobals
{
    public const SECTION = 'Gifford Charge Import';

    public const DROP_DIR = 'gifford_charges_drop_dir';

    public function register(GlobalsInitializedEvent $event): void
    {
        $service = $event->getGlobalsService();
        $section = xl(self::SECTION);
        $service->createSection($section);

        $service->appendToSection($section, self::DROP_DIR, new GlobalSetting(
            xl('Charge File Directory'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Directory, as the server sees it, where Gifford\'s charge files arrive. The hourly import reads new files there and moves each one into processed/ or failed/ beside it. Blank: the import does nothing.')
        ));
    }
}
