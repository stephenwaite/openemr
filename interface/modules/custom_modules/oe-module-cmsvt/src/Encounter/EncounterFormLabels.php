<?php

/**
 * Relabels the encounter form's onset date as "Date Last Seen" on sites configured to do so.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Encounter;

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Events\Core\TemplatePageEvent;

final readonly class EncounterFormLabels
{
    private const ENCOUNTER_FORM_PAGE = 'newpatient/common.php';

    public function __construct(private OEGlobalsBag $globals)
    {
    }

    public function apply(TemplatePageEvent $event): void
    {
        if ($event->getPageName() !== self::ENCOUNTER_FORM_PAGE || !$this->globals->getBoolean(CmsvtGlobals::ENCOUNTER_DATE_LAST_SEEN)) {
            return;
        }
        $variables = $event->getTwigVariables();
        $variables['onsetDateLastSeen'] = true;
        $event->setTwigVariables($variables);
    }
}
