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
    public const ENCOUNTER_REPORT_HIDE_VISIT_DETAILS = 'cmsvt_encounter_report_hide_visit_details';
    public const ENCOUNTER_DATE_LAST_SEEN = 'cmsvt_encounter_date_last_seen';
    public const HL7_MATCH_PATIENT_BY_MRN = 'cmsvt_hl7_match_patient_by_mrn';
    public const LAB_RESULTS_PER_LAB = 'cmsvt_lab_results_per_lab';
    public const LAB_LIST_DEFAULT_REVIEWED = 'cmsvt_lab_list_default_reviewed';
    public const CLAIM_ROUTINE_FOOT_CARE_NPIS = 'cmsvt_claim_routine_foot_care_npis';
    public const CLAIM_NDC_SKIP_PAYER_IDS = 'cmsvt_claim_ndc_skip_payer_ids';
    public const CLAIM_CLOSED_FACILITY_NPIS = 'cmsvt_claim_closed_facility_npis';
    public const CLAIM_RENDERING_PROVIDER_ID = 'cmsvt_claim_rendering_provider_id';
    // Press Ganey keys keep their 7.0.1 names so existing site values carry over.
    public const PG_CLIENT_ID = 'pg_client_id';
    public const PG_SURVEY_DESIGNATOR = 'pg_survey_designator';

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
        $service->appendToSection($section, self::ENCOUNTER_REPORT_HIDE_VISIT_DETAILS, new GlobalSetting(
            xl('Hide Visit Details in Encounter Reports'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('Show only the facility for each encounter in patient reports, without category, reason, provider, referring provider or POS code.')
        ));
        $service->appendToSection($section, self::ENCOUNTER_DATE_LAST_SEEN, new GlobalSetting(
            xl('Label Onset Date as Date Last Seen'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('On the encounter form, label the onset/hospitalization date "Date Last Seen" (e.g. podiatry routine foot care).')
        ));
        $service->appendToSection($section, self::HL7_MATCH_PATIENT_BY_MRN, new GlobalSetting(
            xl('Match Lab Results by MRN Only'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('Match incoming lab results to patients on the MRN the lab sends (PID-3 = External ID) alone, instead of name, DOB and SSN.')
        ));
        $service->appendToSection($section, self::LAB_RESULTS_PER_LAB, new GlobalSetting(
            xl('Default Lab Results Processed Per Lab'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('Default for "Results Per Lab" on Electronic Reports (1-50). 0 keeps the stock default of 10.')
        ));
        $service->appendToSection($section, self::LAB_LIST_DEFAULT_REVIEWED, new GlobalSetting(
            xl('Electronic Reports Default to Reviewed'),
            GlobalSetting::DATA_TYPE_BOOL,
            '0',
            xl('Open Electronic Reports filtered to reviewed results instead of received, unreviewed ones.')
        ));
        $service->appendToSection($section, self::CLAIM_ROUTINE_FOOT_CARE_NPIS, new GlobalSetting(
            xl('Claims: Routine Foot Care Billing NPIs'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Billing facility NPIs (comma-separated) whose claims follow the podiatry rules: date last seen (DTP*304), referring/supervising provider, x-ray referrer and Q7-Q9 modifier checks.')
        ));
        $service->appendToSection($section, self::CLAIM_NDC_SKIP_PAYER_IDS, new GlobalSetting(
            xl('Claims: Payers Without NDCs'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Payer IDs (comma-separated) that must not receive NDC drug identification (loop 2410) on claims.')
        ));
        $service->appendToSection($section, self::CLAIM_CLOSED_FACILITY_NPIS, new GlobalSetting(
            xl('Claims: Closed Service Facility NPIs'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Service facility NPIs (comma-separated) that are closed; claims using one get a warning in the claim log.')
        ));
        $service->appendToSection($section, self::CLAIM_RENDERING_PROVIDER_ID, new GlobalSetting(
            xl('Claims: Bill Under Provider'),
            GlobalSetting::DATA_TYPE_NUMBER,
            '0',
            xl('User ID of the provider every claim is billed under ("incident to" billing). 0 bills under each encounter\'s provider.')
        ));
        $service->appendToSection($section, self::PG_CLIENT_ID, new GlobalSetting(
            xl('Press Ganey Client ID'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Client ID written to every row of the Press Ganey survey export.')
        ));
        $service->appendToSection($section, self::PG_SURVEY_DESIGNATOR, new GlobalSetting(
            xl('Press Ganey Survey Designator'),
            GlobalSetting::DATA_TYPE_TEXT,
            '',
            xl('Survey designator written to every row of the Press Ganey survey export.')
        ));
    }
}
