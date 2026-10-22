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
    public const READING_DOCTOR_CODE = 'cmsvt_rri_reading_doctor_code';
    public const READING_DOCTOR_USER = 'cmsvt_rri_reading_doctor_user';
    public const OUTPATIENT_FACILITY = 'cmsvt_rri_outpatient_facility';
    public const INPATIENT_FACILITY = 'cmsvt_rri_inpatient_facility';

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
        $service->appendToSection($section, self::READING_DOCTOR_CODE, new GlobalSetting(
            xl('Reading Doctor Code'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Two-character doctor code step 3 puts on every ordfile charge (docp). Blank means 01, the single reading doctor.')
        ));
        $service->appendToSection($section, self::READING_DOCTOR_USER, new GlobalSetting(
            xl('Reading Doctor User ID'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Users id step 6 bills the reading doctor code\'s charges under, as their encounter provider. 0 means 5000.')
        ));
        $service->appendToSection($section, self::OUTPATIENT_FACILITY, new GlobalSetting(
            xl('Outpatient Facility ID'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Facility id of step 6\'s outpatient and emergency room encounters. 0 means 4.')
        ));
        $service->appendToSection($section, self::INPATIENT_FACILITY, new GlobalSetting(
            xl('Inpatient Facility ID'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Facility id of step 6\'s inpatient encounters. 0 means 5.')
        ));
        $service->appendToSection($section, self::AUTOSAVE_INTERVAL, new GlobalSetting(
            xl('Coding Page Auto-Save Seconds'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('How often the RRI coding page saves on its own, in seconds (at least 5). 0 means 15.')
        ));
    }
}
