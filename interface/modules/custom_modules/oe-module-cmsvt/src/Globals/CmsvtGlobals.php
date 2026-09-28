<?php

/**
 * Per-site settings for the CMS Vermont customizations, shown under Administration > Globals.
 *
 * Globals live in each site's database, so every value here is per site. They replace the
 * hardcoded $_SESSION['site_id'] checks of the 7.0.1 customizations.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Globals;

use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Services\Globals\GlobalSetting;

final class CmsvtGlobals
{
    public const SECTION = 'CMS Vermont';

    public const ELIG_PROVIDER_ID = 'cmsvt_elig_provider_id';
    public const ELIG_RECEIVER_NAME = 'cmsvt_elig_receiver_name';
    public const BILLING_MANAGER_DOS_MONTHS = 'cmsvt_billing_manager_dos_months';
    public const COLLECTIONS_HIDE_AGENCY_EXPORT = 'cmsvt_collections_hide_agency_export';

    public function register(GlobalsInitializedEvent $event): void
    {
        $service = $event->getGlobalsService();
        $section = xl(self::SECTION);
        $service->createSection($section);

        $service->appendToSection($section, self::ELIG_PROVIDER_ID, new GlobalSetting(
            xl('Eligibility (270) Provider Override'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('User ID of the provider whose NPI is sent on every real-time eligibility request. 0 uses each patient\'s own provider.')
        ));
        $service->appendToSection($section, self::ELIG_RECEIVER_NAME, new GlobalSetting(
            xl('Eligibility (270) Receiver Name Override'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Organization name sent in the eligibility request instead of the facility name. Blank keeps the facility name.')
        ));
        $service->appendToSection($section, self::BILLING_MANAGER_DOS_MONTHS, new GlobalSetting(
            xl('Billing Manager Default Date-of-Service Months'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Default Billing Manager search: 0 lists all unbilled encounters, N limits it to the last N months, -1 keeps the stock default (today only).')
        ));
        $service->appendToSection($section, self::COLLECTIONS_HIDE_AGENCY_EXPORT, new GlobalSetting(
            xl('Hide Export to Collections'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('Hide the "Export Selected to Collections" button on the collections report, for sites that do not use a collection agency.')
        ));
    }
}
