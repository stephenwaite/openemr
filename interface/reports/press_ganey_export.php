<?php

/**
 * Press Ganey Survey Data Export Report
 *
 * Exports patient encounter data in Press Ganey's required CSV format
 * for patient satisfaction surveys.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('encounters', 'coding_a')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Press Ganey Export")]);
    exit;
}

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

// Press Ganey Configuration
$PG_CLIENT_ID = $GLOBALS['pg_client_id'];
$PG_SURVEY_DESIGNATOR = $GLOBALS['pg_survey_designator'];

// Form parameters
$form_from_date = (isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');
$form_provider  = $_POST['form_provider'] ?? null;
$form_facility  = $_POST['form_facility'] ?? null;
$form_encounter_type = $_POST['form_encounter_type'] ?? null;

// Handle CSV export
if (!empty($_POST['form_export'])) {
    generatePressGaneyCSV();
    exit;
}

function generatePressGaneyCSV() {
    global $form_from_date, $form_to_date, $form_provider, $form_facility, $form_encounter_type;
    global $PG_CLIENT_ID, $PG_SURVEY_DESIGNATOR;

    $sqlBindArray = [];

    // Build query to get encounter and patient data
    $query = "SELECT DISTINCT
        fe.encounter,
        fe.date as visit_date,
        fe.facility_id,
        fe.provider_id,
        fe.pc_catid as encounter_type,
        p.pid,
        p.lname,
        p.fname,
        p.mname,
        p.pubpid,
        p.DOB,
        p.sex,
        p.street,
        p.city,
        p.state,
        p.postal_code,
        p.phone_home,
        p.phone_cell,
        p.email,
        fac.id as facility_id,
        fac.name as facility_name,
        fac.street as facility_street,
        fac.city as facility_city,
        fac.state as facility_state,
        fac.postal_code as facility_postal_code,
        u.lname as provider_lname,
        u.fname as provider_fname,
        u.npi as provider_npi,
        u.physician_type,
        u.taxonomy as specialty
    FROM form_encounter AS fe
    LEFT JOIN patient_data AS p ON p.pid = fe.pid
    LEFT JOIN users AS u ON u.id = fe.provider_id
    LEFT JOIN facility AS fac ON fac.id = fe.facility_id
    WHERE fe.date >= ? AND fe.date <= ? ";

    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');

    if ($form_provider) {
        $query .= "AND fe.provider_id = ? ";
        array_push($sqlBindArray, $form_provider);
    }

    if ($form_facility) {
        $query .= "AND fe.facility_id = ? ";
        array_push($sqlBindArray, $form_facility);
    }

    if ($form_encounter_type) {
        $query .= "AND fe.pc_catid = ? ";
        array_push($sqlBindArray, $form_encounter_type);
    }

    $query .= "ORDER BY fe.date, p.lname, p.fname";

    $res = sqlStatement($query, $sqlBindArray);

    // Set headers for CSV download
    $filename = "press_ganey_export_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    // Open output stream
    $output = fopen('php://output', 'w');

    // Add BOM for proper UTF-8 encoding
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));

    // Write CSV headers (Press Ganey field names)
    $headers = [
        'Survey Designator',
        'Client ID',
        'Last Name',
        'Middle Initial',
        'First Name',
        'Address 1',
        'Address 2',
        'City',
        'State',
        'Zip Code',
        'Telephone Number',
        'Mobile Number',
        'Gender',
        'Date of Birth',
        'Language',
        'Medical Record Number',
        'Unique ID',
        'Location Code',
        'Location Name',
        'Attending Physician NPI',
        'Attending Physician Name',
        'Provider Type',
        'Provider Specialty',
        'Site address 1',
        'Site address 2',
        'Site city',
        'Site state',
        'Site zip',
        'Visit or Admin Date',
        'Email',
        'E.O.R. Indicator'
    ];

    fputcsv($output, $headers);

    // Write data rows
    while ($row = sqlFetchArray($res)) {
        // Format phone numbers (remove non-numeric characters)
        $phone_home = preg_replace('/[^0-9]/', '', $row['phone_home'] ?? '');
        $phone_cell = preg_replace('/[^0-9]/', '', $row['phone_cell'] ?? '');

        // Format phone with hyphens if 10 digits
        if (strlen($phone_home) == 10) {
            $phone_home = substr($phone_home, 0, 3) . '-' . substr($phone_home, 3, 3) . '-' . substr($phone_home, 6, 4);
        }
        if (strlen($phone_cell) == 10) {
            $phone_cell = substr($phone_cell, 0, 3) . '-' . substr($phone_cell, 3, 3) . '-' . substr($phone_cell, 6, 4);
        }

        // Format gender (1=Male, 2=Female, M=Unknown)
        $gender = 'M';
        if (!empty($row['sex'])) {
            if (strtoupper($row['sex']) == 'MALE' || strtoupper($row['sex']) == 'M') {
                $gender = '1';
            } elseif (strtoupper($row['sex']) == 'FEMALE' || strtoupper($row['sex']) == 'F') {
                $gender = '2';
            }
        }

    // Format dates (mmddyyyy)
$dob = '';
if (!empty($row['DOB'])) {
    $timestamp = strtotime($row['DOB']);
    if ($timestamp !== false) {
        $formatted_dob = date('n', $timestamp) .      // month without leading zeros
                        date('j', $timestamp) .      // day without leading zeros
                        date('Y', $timestamp);       // 4-digit year
        $dob = $formatted_dob;
    }
}

    $visit_date = '';
if (!empty($row['visit_date'])) {
    $timestamp = strtotime($row['visit_date']);
    if ($timestamp !== false) {
        $formatted_visit = date('n', $timestamp) .      // month without leading zeros
                          date('j', $timestamp) .      // day without leading zeros
                          date('Y', $timestamp);       // 4-digit year
        $visit_date = $formatted_visit;
    }
}

        // Format provider name
        $provider_name = '';
        if (!empty($row['provider_lname']) || !empty($row['provider_fname'])) {
            $provider_name = 'Dr. ' . trim($row['provider_fname'] . ' ' . $row['provider_lname']);
        }

        // Format provider type - replace underscores with spaces and use title case
        $provider_type = '';
        if (!empty($row['physician_type'])) {
            $provider_type = str_replace('_', ' ', $row['physician_type']);
            $provider_type = ucwords(strtolower($provider_type));
        }

        // Get middle initial
        $middle_initial = !empty($row['mname']) ? strtoupper(substr($row['mname'], 0, 1)) : '';

        // Format state to uppercase USPS 2-letter abbreviation (just first 2 chars uppercase)
        $state = !empty($row['state']) ? strtoupper(substr($row['state'], 0, 2)) : '';

        // Format zip code
        $zip_code = !empty($row['postal_code']) ? substr($row['postal_code'], 0, 10) : '';

        // Format facility state to uppercase USPS 2-letter abbreviation (just first 2 chars uppercase)
        $facility_state = !empty($row['facility_state']) ? strtoupper(substr($row['facility_state'], 0, 2)) : '';

        // Format facility zip
        $facility_zip = '';
        if (!empty($row['facility_postal_code'])) {
            $zip = substr($row['facility_postal_code'], 0, 10);

        // Add dash after first 5 digits if zip is 9 digits
            if (strlen($zip) == 9) {
                $zip = substr($zip, 0, 5) . '-' . substr($zip, 5);
            }
            $facility_zip = $zip;
        }

        // Build data row
        $data = [
           $PG_SURVEY_DESIGNATOR,                      // Survey Designator
           $PG_CLIENT_ID,                              // Client ID
           substr($row['lname'] ?? '', 0, 25),         // Last Name
           $middle_initial,                            // Middle Initial
           substr($row['fname'] ?? '', 0, 20),         // First Name
            substr($row['street'] ?? '', 0, 40),        // Address 1
            substr($row['street2'] ?? '', 0, 40),       // Address 2
            substr($row['city'] ?? '', 0, 25),          // City
            $state,                                     // State (uppercase)
            $zip_code,                                    // Zip Code (preserves leading zeros)
            $phone_home,                                // Telephone Number
            $phone_cell,                                // Mobile Number
            $gender,                                    // Gender
            $dob,                                         // Date of Birth
            '',                                           // Language (implement if needed)
            substr($row['pubpid'] ?? '', 0, 20),        // Medical Record Number
            substr($row['encounter'] ?? '', 0, 20),     // Unique ID
            substr($row['facility_id'] ?? '', 0, 20),   // Location Code
            substr($row['facility_name'] ?? '', 0, 50), // Location Name
            substr($row['provider_npi'] ?? '', 0, 50),  // Attending Physician NPI
            substr($provider_name, 0, 50),              // Attending Physician Name
            substr($provider_type, 0, 50),              // Provider Type (formatted)
            substr($row['specialty'] ?? '', 0, 50),     // Provider Specialty
            substr($row['facility_street'] ?? '', 0, 40), // Site address 1
            '',                                             // Site address 2
            substr($row['facility_city'] ?? '', 0, 25),  // Site city
            $facility_state,                             // Site state (uppercase)
            $facility_zip,                                 // Site zip (preserves leading zeros)
            $visit_date,                                   // Visit or Admin Date
            substr($row['email'] ?? '', 0, 60),          // Email
            '$'                                            // E.O.R. Indicator
        ];

        fputcsv($output, $data);
    }

    fclose($output);
}

// Get encounter types for dropdown
$encounter_types = [];
$type_res = sqlStatement("SELECT pc_catid, pc_catname FROM openemr_postcalendar_categories WHERE pc_active = 1 ORDER BY pc_catname");
while ($type_row = sqlFetchArray($type_res)) {
    $encounter_types[] = $type_row;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo xlt('Press Ganey Survey Export'); ?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

    <style>
        @media print {
            #report_parameters {
                visibility: hidden;
                display: none;
            }
        }

        .info-box {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .config-info {
            font-size: 0.875rem;
            color: #6c757d;
        }
    </style>

    <script>
        $(function () {
            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
            });
        });

        function validateAndExport() {
            var fromDate = $('#form_from_date').val();
            var toDate = $('#form_to_date').val();

            if (!fromDate || !toDate) {
                alert(<?php echo xlj('Please select both From and To dates.'); ?>);
                return false;
            }

            if (confirm(<?php echo xlj('Export encounters to Press Ganey CSV format?'); ?>)) {
                $('#form_export').val('1');
                document.forms[0].submit();
            }
            return false;
        }

        function previewData() {
            var fromDate = $('#form_from_date').val();
            var toDate = $('#form_to_date').val();

            if (!fromDate || !toDate) {
                alert(<?php echo xlj('Please select both From and To dates.'); ?>);
                return false;
            }

            $('#form_preview').val('1');
            document.forms[0].submit();
            return false;
        }
    </script>
</head>

<body class="body_top">

<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('Press Ganey Survey Export'); ?></span>

<form method='post' name='theform' id='theform' action='press_ganey_export.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />
<input type="hidden" name="form_export" id="form_export" value="" />
<input type="hidden" name="form_preview" id="form_preview" value="" />

<div id="report_parameters">

    <div class="info-box">
        <h6><?php echo xlt('Press Ganey Configuration'); ?></h6>
        <div class="config-info">
            <strong><?php echo xlt('Client ID'); ?>:</strong> <?php echo text($PG_CLIENT_ID); ?><br>
            <strong><?php echo xlt('Survey Designator'); ?>:</strong> <?php echo text($PG_SURVEY_DESIGNATOR); ?><br>

        </div>
    </div>

    <table class='table table-borderless'>
        <tr>
            <td width='650px'>
                <table class='text'>
                    <tr>
                        <td class='col-form-label'>
                            <?php echo xlt('Facility'); ?>:
                        </td>
                        <td>
                            <?php dropdown_facility($form_facility, 'form_facility', true); ?>
                        </td>
                        <td class='col-form-label'>
                            <?php echo xlt('Provider'); ?>:
                        </td>
                        <td>
                            <?php
                            $query = "SELECT id, lname, fname FROM users WHERE authorized = 1 ORDER BY lname, fname";
                            $ures = sqlStatement($query);

                            echo "<select name='form_provider' class='form-control'>\n";
                            echo "<option value=''>-- " . xlt('All') . " --\n";

                            while ($urow = sqlFetchArray($ures)) {
                                $provid = $urow['id'];
                                echo "<option value='" . attr($provid) . "'";
                                if (!empty($_POST['form_provider']) && ($provid == $_POST['form_provider'])) {
                                    echo " selected";
                                }
                                echo ">" . text($urow['lname']) . ", " . text($urow['fname']) . "\n";
                            }

                            echo "</select>\n";
                            ?>
                        </td>
                    </tr>
                    <tr>
                        <td class='col-form-label'>
                            <?php echo xlt('From'); ?>:
                        </td>
                        <td>
                            <input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>'>
                        </td>
                        <td class='col-form-label'>
                            <?php echo xlt('To{{Range}}'); ?>:
                        </td>
                        <td>
                            <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
                        </td>
                    </tr>
                    <tr>
                        <td class='col-form-label'>
                            <?php echo xlt('Encounter Type'); ?>:
                        </td>
                        <td colspan="3">
                            <select name='form_encounter_type' class='form-control'>
                                <option value=''>-- <?php echo xlt('All'); ?> --</option>
                                <?php
                                foreach ($encounter_types as $etype) {
                                    echo "<option value='" . attr($etype['pc_catid']) . "'";
                                    if (!empty($_POST['form_encounter_type']) && ($etype['pc_catid'] == $_POST['form_encounter_type'])) {
                                        echo " selected";
                                    }
                                    echo ">" . text($etype['pc_catname']) . "</option>\n";
                                }
                                ?>
                            </select>
                        </td>
                    </tr>
                </table>
            </td>
            <td align='left' valign='middle'>
                <table class='w-100 h-100' style='border-left:1px solid;'>
                    <tr>
                        <td>
                            <div class="text-center">
                                <div class="btn-group-vertical" role="group">
                                    <a href='#' class='btn btn-primary btn-save mb-2' onclick='return previewData();'>
                                        <i class="fa fa-search"></i> <?php echo xlt('Preview Records'); ?>
                                    </a>
                                    <a href='#' class='btn btn-success btn-transmit' onclick='return validateAndExport();'>
                                        <i class="fa fa-download"></i> <?php echo xlt('Export CSV'); ?>
                                    </a>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</div>

<?php
// Preview functionality
if (!empty($_POST['form_preview'])) {
    $sqlBindArray = [];

    $query = "SELECT COUNT(DISTINCT fe.encounter) as count
    FROM form_encounter AS fe
    LEFT JOIN patient_data AS p ON p.pid = fe.pid
    WHERE fe.date >= ? AND fe.date <= ? ";

    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');

    if ($form_provider) {
        $query .= "AND fe.provider_id = ? ";
        array_push($sqlBindArray, $form_provider);
    }

    if ($form_facility) {
        $query .= "AND fe.facility_id = ? ";
        array_push($sqlBindArray, $form_facility);
    }

    if ($form_encounter_type) {
        $query .= "AND fe.pc_catid = ? ";
        array_push($sqlBindArray, $form_encounter_type);
    }

    $result = sqlQuery($query, $sqlBindArray);
    $count = $result['count'];
    ?>

    <div class="alert alert-info mt-3">
        <h5><?php echo xlt('Export Preview'); ?></h5>
        <p><strong><?php echo xlt('Total Encounters to Export'); ?>:</strong> <?php echo text($count); ?></p>
        <p><strong><?php echo xlt('Date Range'); ?>:</strong> <?php echo text(oeFormatShortDate($form_from_date)) . " " . xlt('to{{Range}}') . " " . text(oeFormatShortDate($form_to_date)); ?></p>
        <?php if ($count > 0) { ?>
            <p class="mb-0"><em><?php echo xlt('Click "Export CSV" to download the Press Ganey file.'); ?></em></p>
        <?php } else { ?>
            <p class="mb-0 text-danger"><em><?php echo xlt('No encounters found matching the selected criteria.'); ?></em></p>
        <?php } ?>
    </div>

<?php } ?>

</form>

<div class="mt-4">
    <h6><?php echo xlt('Instructions'); ?></h6>
    <ul>
        <li><?php echo xlt('Select date range and optional filters (Facility, Provider, Encounter Type)'); ?></li>
        <li><?php echo xlt('Click "Preview Records" to see how many encounters will be exported'); ?></li>
        <li><?php echo xlt('Click "Export CSV" to download the file in Press Ganey format'); ?></li>
        <li><?php echo xlt('Upload the CSV file to Press Ganey according to their specifications'); ?></li>
    </ul>

    <h6><?php echo xlt('Field Mappings'); ?></h6>
    <small class="text-muted">
        <?php echo xlt('The following OpenEMR fields are mapped to Press Ganey format'); ?>:
        <ul class="small">
            <li><?php echo xlt('Patient demographics (name, address, DOB, gender)'); ?></li>
            <li><?php echo xlt('Contact information (phone, mobile, email)'); ?></li>
            <li><?php echo xlt('Provider information (name, NPI, specialty)'); ?></li>
            <li><?php echo xlt('Facility/site information'); ?></li>
            <li><?php echo xlt('Encounter date and ID'); ?></li>
        </ul>
    </small>
</div>

</body>
</html>
