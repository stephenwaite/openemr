<?php

/**
 * The RRI billing module's settings (Admin > Config > RRI Billing), per site.
 *
 * The keys keep the 7.0.2 module's names so a site's values carry over with its
 * database. A setting nobody has saved reads as blank/0/false, so every default
 * here must also be what an unsaved value means.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\RriBilling\Globals;

use OpenEMR\Events\Globals\GlobalsInitializedEvent;
use OpenEMR\Services\Globals\GlobalSetting;

final class RriGlobals
{
    public const SECTION = 'RRI Billing';

    public const SFTP_URL = 'cmsvt_rri_sftp_url';
    public const SFTP_PORT = 'cmsvt_rri_sftp_port';
    public const SFTP_REMOTE_FOLDER = 'cmsvt_rri_sftp_remote_folder';
    public const SFTP_USERNAME = 'cmsvt_rri_sftp_username';
    public const SFTP_PASSWORD = 'cmsvt_rri_sftp_password';
    public const DEBUG = 'cmsvt_rri_debug';
    public const AUTOSAVE_INTERVAL = 'cmsvt_rri_autosave_interval';

    public function register(GlobalsInitializedEvent $event): void
    {
        $service = $event->getGlobalsService();
        $section = xl(self::SECTION);
        $service->createSection($section);

        $service->appendToSection($section, self::SFTP_URL, new GlobalSetting(
            xl('Charge File SFTP Host'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Host name of the SFTP server RRMC puts the daily charge file on. Blank: step 1 cannot fetch files.')
        ));
        $service->appendToSection($section, self::SFTP_PORT, new GlobalSetting(
            xl('Charge File SFTP Port'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Port of the SFTP server. 0 means 22.')
        ));
        $service->appendToSection($section, self::SFTP_REMOTE_FOLDER, new GlobalSetting(
            xl('Charge File SFTP Folder'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Folder on the SFTP server that holds the charge files, e.g. prod/. Blank: the login folder.')
        ));
        $service->appendToSection($section, self::SFTP_USERNAME, new GlobalSetting(
            xl('Charge File SFTP Username'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('SFTP login name.')
        ));
        $service->appendToSection($section, self::SFTP_PASSWORD, new GlobalSetting(
            xl('Charge File SFTP Password'),
            GlobalSetting::DATA_TYPE_ENCRYPTED,
            '',
            xl('SFTP password, stored encrypted.')
        ));
        $service->appendToSection($section, self::DEBUG, new GlobalSetting(
            xl('Debug Output'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('Show extra detail on the RRI billing page and in the console steps.')
        ));
        $service->appendToSection($section, self::AUTOSAVE_INTERVAL, new GlobalSetting(
            xl('Coding Page Auto-Save Seconds'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('How often the RRI coding page saves on its own, in seconds (at least 5). 0 means 15.')
        ));
    }
}
