<?php
/**
 * MIPS Quality Measures Report - 2025 Performance Year
 *
 * Measures included: 039, 176, 177, 178, 180, 374
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Updated for 2025 MIPS specifications
 * @copyright Copyright (c) 2025
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

// Check if CSV download is requested
$export_csv = isset($_GET['export']) && $_GET['export'] === 'csv';

// Updated RA ICD-10 codes for 2025
$ra_icd10_codes = "M05.00, M05.011, M05.012, M05.019, M05.021, M05.022, M05.029, M05.031, M05.032, M05.039, " .
    "M05.041, M05.042, M05.049, M05.051, M05.052, M05.059, M05.061, M05.062, M05.069, M05.071, M05.072, " .
    "M05.079, M05.09, M05.10, M05.111, M05.112, M05.119, M05.121, M05.122, M05.129, M05.131, M05.132, " .
    "M05.139, M05.141, M05.142, M05.149, M05.151, M05.152, M05.159, M05.161, M05.162, M05.169, M05.171, " .
    "M05.172, M05.179, M05.19, M05.20, M05.211, M05.212, M05.219, M05.221, M05.222, M05.229, M05.231, " .
    "M05.232, M05.239, M05.241, M05.242, M05.249, M05.251, M05.252, M05.259, M05.261, M05.262, M05.269, " .
    "M05.271, M05.272, M05.279, M05.29, M05.30, M05.311, M05.312, M05.319, M05.321, M05.322, M05.329, " .
    "M05.331, M05.332, M05.339, M05.341, M05.342, M05.349, M05.351, M05.352, M05.359, M05.361, M05.362, " .
    "M05.369, M05.371, M05.372, M05.379, M05.39, M05.40, M05.411, M05.412, M05.419, M05.421, M05.422, " .
    "M05.429, M05.431, M05.432, M05.439, M05.441, M05.442, M05.449, M05.451, M05.452, M05.459, M05.461, " .
    "M05.462, M05.469, M05.471, M05.472, M05.479, M05.49, M05.50, M05.511, M05.512, M05.519, M05.521, " .
    "M05.522, M05.529, M05.531, M05.532, M05.539, M05.541, M05.542, M05.549, M05.551, M05.552, M05.559, " .
    "M05.561, M05.562, M05.569, M05.571, M05.572, M05.579, M05.59, M05.60, M05.611, M05.612, M05.619, " .
    "M05.621, M05.622, M05.629, M05.631, M05.632, M05.639, M05.641, M05.642, M05.649, M05.651, M05.652, " .
    "M05.659, M05.661, M05.662, M05.669, M05.671, M05.672, M05.679, M05.69, M05.7A, M05.70, M05.711, " .
    "M05.712, M05.719, M05.721, M05.722, M05.729, M05.731, M05.732, M05.739, M05.741, M05.742, M05.749, " .
    "M05.751, M05.752, M05.759, M05.761, M05.762, M05.769, M05.771, M05.772, M05.779, M05.79, M05.8A, " .
    "M05.80, M05.811, M05.812, M05.819, M05.821, M05.822, M05.829, M05.831, M05.832, M05.839, M05.841, " .
    "M05.842, M05.849, M05.851, M05.852, M05.859, M05.861, M05.862, M05.869, M05.871, M05.872, M05.879, " .
    "M05.89, M05.9, M06.0A, M06.00, M06.011, M06.012, M06.019, M06.021, M06.022, M06.029, M06.031, " .
    "M06.032, M06.039, M06.041, M06.042, M06.049, M06.051, M06.052, M06.059, M06.061, M06.062, M06.069, " .
    "M06.071, M06.072, M06.079, M06.08, M06.09, M06.20, M06.211, M06.212, M06.219, M06.221, M06.222, " .
    "M06.229, M06.231, M06.232, M06.239, M06.241, M06.242, M06.249, M06.251, M06.252, M06.259, M06.261, " .
    "M06.262, M06.269, M06.271, M06.272, M06.279, M06.28, M06.29, M06.30, M06.311, M06.312, M06.319, " .
    "M06.321, M06.322, M06.329, M06.331, M06.332, M06.339, M06.341, M06.342, M06.349, M06.351, M06.352, " .
    "M06.359, M06.361, M06.362, M06.369, M06.371, M06.372, M06.379, M06.38, M06.39, M06.8A, M06.80, " .
    "M06.811, M06.812, M06.819, M06.821, M06.822, M06.829, M06.831, M06.832, M06.839, M06.841, M06.842, " .
    "M06.849, M06.851, M06.852, M06.859, M06.861, M06.862, M06.869, M06.871, M06.872, M06.879, M06.88, " .
    "M06.89, M06.9";

$ra_codes_array = explode(", ", $ra_icd10_codes);

set_time_limit(0);

// Set performance period dates for 2025
$form_from_date = isset($_POST['form_from_date']) && $_POST['form_from_date'] ? 
    DateToYYYYMMDD($_POST['form_from_date']) : '2025-01-01';
$form_to_date = isset($_POST['form_to_date']) && $_POST['form_to_date'] ? 
    DateToYYYYMMDD($_POST['form_to_date']) : '2025-12-31';

// Updated CPT codes for 2025
$encounter_cpt_codes = array(
    '98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007', '98008', '98009',
    '98010', '98011', '98012', '98013', '98014', '98015', '98016',
    '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
    '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
    '99424', '99426', 'G0402', 'G0468'
);

// Measure definitions
$measures = array(
    '039' => array('name' => 'Osteoporosis Screening', 'applies_to' => 'female_65_85'),
    '176' => array('name' => 'TB Screening', 'applies_to' => 'all_ra'),
    '177' => array('name' => 'Disease Activity Assessment', 'applies_to' => 'all_ra'),
    '178' => array('name' => 'Functional Status Assessment', 'applies_to' => 'all_ra'),
    '180' => array('name' => 'Glucocorticoid Management', 'applies_to' => 'all_ra'),
    '374' => array('name' => 'Referral Loop', 'applies_to' => 'all_ra')
);

$sqlBindArray = array();

$query = "SELECT " .
    "fe.encounter, fe.date, fe.reason, " .
    "f.formdir, f.form_name, " .
    "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.dob, p.sex, " .
    "TIMESTAMPDIFF(YEAR, p.dob, fe.date) AS age, " .
    "u.lname AS ulname, u.fname AS ufname, u.mname AS umname " .
    "FROM (form_encounter AS fe, forms AS f) " .
    "LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid " .
    "LEFT JOIN users AS u ON u.id = fe.provider_id " .
    "WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient' ";

if ($form_to_date) {
    $query .= "AND fe.date >= ? AND fe.date <= ? ";
    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');
}

$query .= "ORDER BY p.pid ASC";
$res = sqlStatement($query, $sqlBindArray);

$patient_data = array();
$dd_report_rows = array(); // Array to hold DD template rows
$prior_pt = '';

if ($res) {
    while ($row = sqlFetchArray($res)) {
        $pid = $row['pid'];
        $mips_enc_date = substr($row['date'], 0, 10);

        // Skip duplicate patients
        if ($pid == $prior_pt) {
            continue;
        }
        $prior_pt = $pid;

        // Age requirement: >= 18 years for most RA measures
        if ($row['age'] < 18) {
            continue;
        }

        // Check for RA diagnosis
        $has_ra_billing = false;
        $icd10_ra = '';
        
        $dres = sqlStatement(
            "SELECT code FROM billing WHERE code_type = 'ICD10' AND pid = ?",
            array($pid)
        );

        while ($drow = sqlFetchArray($dres)) {
            if (in_array($drow['code'], $ra_codes_array)) {
                $icd10_ra = $drow['code'];
                $has_ra_billing = true;
                break;
            }
        }

        // Check problem list
        $has_ra_emr = false;
        if (!$has_ra_billing) {
            $ra_res = sqlStatement(
                "SELECT title FROM lists WHERE pid = ? AND type = 'medical_problem' " .
                "AND title LIKE '%RHEUMATOID ARTHRITIS%' LIMIT 1",
                array($pid)
            );
            $ra_row = sqlFetchArray($ra_res);
            
            if ($ra_row) {
                $icd10_ra = "M06.9";
                $has_ra_emr = true;
            }
        }

        // Skip if no RA diagnosis
        if (!$has_ra_billing && !$has_ra_emr) {
            continue;
        }

        // Check for qualifying encounter CPT code
        $bres = sqlStatement(
            "SELECT code FROM billing WHERE code_type = 'CPT4' AND encounter = ?",
            array($row['encounter'])
        );
        $brow = sqlFetchArray($bres);
        
        if (!in_array($brow['code'], $encounter_cpt_codes)) {
            continue;
        }
        $cpt_code = $brow['code'];

        // Store patient base data
        $patient_base = array(
            'pid' => $pid,
            'patient_id' => $row['pubpid'],
            'first_name' => $row['fname'],
            'last_name' => $row['lname'],
            'dob' => $row['dob'],
            'sex' => $row['sex'],
            'age' => $row['age'],
            'dos' => $mips_enc_date,
            'cpt' => $cpt_code,
            'icd10' => $icd10_ra
        );

        // Initialize measures array
        $measure_results = array();

        // ========== MEASURE 039: Osteoporosis Screening ==========
        if ($row['sex'] == 'Female' && $row['age'] >= 65 && $row['age'] <= 85) {
            $dexa_res = sqlStatement(
                "SELECT result FROM rule_patient_data WHERE item = 'act_osteo' AND pid = ?",
                array($pid)
            );
            
            $has_dexa = false;
            while ($dexa_row = sqlFetchArray($dexa_res)) {
                $result = $dexa_row['result'];
                if (stripos($result, '201') !== false || stripos($result, '202') !== false || 
                    stripos($result, 'scheduled') !== false) {
                    $has_dexa = true;
                    break;
                }
            }
            
            $measure_results['039'] = array(
                'numerator' => $has_dexa ? 'G8399' : 'G8400',
                'modifier' => '',
                'assessment_type' => 'DXA Screening',
                'assessment_score' => $has_dexa ? 'Completed' : 'Not Completed'
            );
        }

        // ========== MEASURE 176: TB Screening ==========
        $tb_res = sqlStatement(
            "SELECT result FROM rule_patient_data WHERE item = 'act_tb' AND pid = ? " .
            "AND date >= DATE_SUB(?, INTERVAL 12 MONTH)",
            array($pid, $mips_enc_date)
        );
        $tb_row = sqlFetchArray($tb_res);
        
        $tb_done = $tb_row && stripos($tb_row['result'], '20') !== false;
        $measure_results['176'] = array(
            'numerator' => $tb_done ? 'M1003' : 'M1005',
            'modifier' => '',
            'assessment_type' => 'TB Screening',
            'assessment_score' => $tb_done ? 'Completed' : 'Not Completed'
        );

        // ========== MEASURE 177: Disease Activity Assessment ==========
        $enc_arr = getEncounters($pid, $form_from_date, $form_to_date);
        $total_encounters = count($enc_arr);
        
        $activity_res = sqlStatement(
            "SELECT COUNT(*) as cnt FROM rule_patient_data " .
            "WHERE item = 'act_cdai' AND pid = ? " .
            "AND date >= ? AND date <= ?",
            array($pid, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59')
        );
        $activity_row = sqlFetchArray($activity_res);
        $activity_count = $activity_row['cnt'];
        
        $numerator_177 = 'M1006';
        $assessment_pct = 0;
        if ($total_encounters > 0) {
            $assessment_pct = ($activity_count / $total_encounters) * 100;
            if ($assessment_pct >= 50) {
                $numerator_177 = 'M1007';
            } elseif ($activity_count > 0) {
                $numerator_177 = 'M1008';
            }
        }
        
        $measure_results['177'] = array(
            'numerator' => $numerator_177,
            'modifier' => 'M1374',
            'assessment_type' => 'Disease Activity',
            'assessment_score' => round($assessment_pct, 1) . '%'
        );

        // ========== MEASURE 178: Functional Status ==========
        $func_res = sqlStatement(
            "SELECT result FROM rule_patient_data WHERE item = 'act_rafunc' AND pid = ? " .
            "AND date >= ? AND date <= ?",
            array($pid, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59')
        );
        
        $func_done = sqlFetchArray($func_res) ? true : false;
        $measure_results['178'] = array(
            'numerator' => $func_done ? '1170F' : '1170F',
            'modifier' => $func_done ? 'M1375' : '8P,M1375',
            'assessment_type' => 'Functional Status',
            'assessment_score' => $func_done ? 'Completed' : 'Not Completed'
        );

        // ========== MEASURE 180: Glucocorticoid Management ==========
        $glu_res = sqlStatement(
            "SELECT title FROM lists WHERE pid = ? AND type = 'medication' " .
            "AND (title LIKE '%SONE%' OR title LIKE '%LONE%') AND enddate = ''",
            array($pid)
        );
        
        $on_glucocorticoid = false;
        while ($glu_row = sqlFetchArray($glu_res)) {
            $on_glucocorticoid = true;
            break;
        }

        $numerator_180 = '4192F';
        $modifier_180 = 'M1376';
        $gluco_status = 'Not on glucocorticoids';
        
        if ($on_glucocorticoid) {
            $gluco_res = sqlStatement(
                "SELECT result FROM rule_patient_data WHERE item = 'act_glucocorticoid' AND pid = ?",
                array($pid)
            );
            $gluco_row = sqlFetchArray($gluco_res);
            
            if ($gluco_row) {
                $result = strtolower($gluco_row['result']);
                if (stripos($result, 'no') !== false || stripos($result, 'off') !== false) {
                    $numerator_180 = '4192F';
                    $gluco_status = 'Not receiving';
                } elseif (stripos($result, 'low-dose') !== false || stripos($result, 'less than') !== false) {
                    $numerator_180 = 'G2112';
                    $gluco_status = 'Low dose';
                } else {
                    $numerator_180 = 'G2113';
                    $modifier_180 = '0540F,M1376';
                    $gluco_status = 'High dose with plan';
                }
            } else {
                $numerator_180 = '4194F';
                $modifier_180 = '8P,M1376';
                $gluco_status = 'Not documented';
            }
        }
        
        $measure_results['180'] = array(
            'numerator' => $numerator_180,
            'modifier' => $modifier_180,
            'assessment_type' => 'Glucocorticoid',
            'assessment_score' => $gluco_status
        );

        // ========== MEASURE 374: Referral Loop ==========
        $ref_res = sqlStatement(
            "SELECT result FROM rule_patient_data WHERE item = 'act_ref_sent_sum' AND pid = ?",
            array($pid)
        );
        
        $ref_received = sqlFetchArray($ref_res) ? true : false;
        $measure_results['374'] = array(
            'numerator' => $ref_received ? 'G9969' : 'G9970',
            'modifier' => 'G9968',
            'assessment_type' => 'Referral',
            'assessment_score' => $ref_received ? 'Report Received' : 'No Report'
        );

        // Create DD template rows (one per measure)
        foreach ($measure_results as $measure_num => $measure_data) {
            $dd_report_rows[] = array(
                'pid' => $patient_base['pid'],
                'PatientID' => $patient_base['patient_id'],
                'First Name' => $patient_base['first_name'],
                'Last Name' => $patient_base['last_name'],
                'Date of Birth' => $patient_base['dob'],
                'Sex' => $patient_base['sex'],
                'Date of Service' => $patient_base['dos'],
                'Measure Number' => $measure_num,
                'CPT' => $patient_base['cpt'],
                'Modifier' => '', // CPT modifier (usually blank)
                'ICD10' => $patient_base['icd10'],
                'Numerator' => $measure_data['numerator'],
                'Modifier' => $measure_data['modifier'],
                'Assessment Type' => $measure_data['assessment_type'],
                'Assessment Score' => $measure_data['assessment_score']
            );
        }
    }
}

// ========== CSV Export ==========
if ($export_csv) {
    $filename = "MIPS_2025_Report_" . date('Y-m-d_His') . ".csv";
    
     header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: max-age=0');
    
    $output = fopen('php://output', 'w');

     // CSV header
    fputcsv($output, array(
        'PID',
        'Patient_ID',
        'First_Name',
        'Last_Name',
        'Date_of_Birth',
        'Sex',
        'Date_of_Service',
        'Measure_Number',
        'CPT',
        'CPT_Modifier',
        "ICD10",
        'Numerator',
        'Numerator_Modifier',
        'Assessment_Type',
        'Assessment_Score'
    ));
    
     // CSV data rows
    foreach ($dd_report_rows as $row) {
        fputcsv($output, array( 
            $row['pid'],
            $row['PatientID'],
            $row['First Name'],
            $row['Last Name'],
            $row['Date of Birth'],  
            $row['Sex'],
            $row['Date of Service'],
            $row['Measure Number'],
            $row['CPT'],
            $row['CPT Modifier'],
            $row['ICD10'],
            $row['Numerator'],
            $row['Numerator Modifier'],
            $row['Assessment Type'],
            $row['Assessment Score']
        ));     
     
    }
    
  fclose($output);
    exit;
}

// ========== HTML OUTPUT ==========
?>
<!DOCTYPE html>
<html>
<head>
    <title>MIPS 2025 Quality Measures Report</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background-color: #2c5aa0;
            color: white;
            padding: 20px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .controls {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .button {
            background-color: #4CAF50;
            color: white;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            display: inline-block;
            margin-right: 10px;
            border: none;
            cursor: pointer;
            font-size: 14px;
        }
        .button:hover {
            background-color: #45a049;
        }
        .button.secondary {
            background-color: #2196F3;
        }
        .button.secondary:hover {
            background-color: #0b7dda;
        }
        .stats {
            background-color: white;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 10px;
        }
        .stat-box {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 4px;
            border-left: 4px solid #2c5aa0;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
            margin-top: 5px;
        }
        table {
            background-color: white;
            border-collapse: collapse;
            width: 100%;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            font-size: 11px;
        }
        th {
            background-color: #2c5aa0;
            color: white;
            padding: 10px 6px;
            text-align: left;
            position: sticky;
            top: 0;
            z-index: 10;
            font-size: 11px;
        }
        td {
            padding: 8px 6px;
            border-bottom: 1px solid #ddd;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .measure-039 { background-color: #e3f2fd; }
        .measure-176 { background-color: #f3e5f5; }
        .measure-177 { background-color: #e8f5e9; }
        .measure-178 { background-color: #fff3e0; }
        .measure-180 { background-color: #fce4ec; }
        .measure-374 { background-color: #e0f2f1; }
        .date-form {
            display: inline-block;
            margin-right: 20px;
        }
        .date-form label {
            font-weight: bold;
            margin-right: 5px;
        }
        .date-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            margin-right: 10px;
        }
        .info-box {
            background-color: #fff3cd;
            border: 1px solid #ffc107;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box h4 {
            margin-top: 0;
            color: #856404;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>MIPS 2025 Quality Measures Report</h1>
    <p>One row per measure per patient | Ready for registry submission</p>
</div>



<div class="controls">
    <form method="POST" action="" style="display: inline;">
        <div class="date-form">
            <label>From Date:</label>
            <input type="text" name="form_from_date" value="<?php echo oeFormatShortDate($form_from_date); ?>" 
                   class="datepicker" placeholder="YYYY-MM-DD">
        </div>
        <div class="date-form">
            <label>To Date:</label>
            <input type="text" name="form_to_date" value="<?php echo oeFormatShortDate($form_to_date); ?>" 
                   class="datepicker" placeholder="YYYY-MM-DD">
        </div>
        <button type="submit" class="button secondary">Run Report</button>
    </form>
    
    <a href="?export=csv<?php echo isset($_POST['form_from_date']) ? '&form_from_date=' . urlencode($_POST['form_from_date']) : ''; ?><?php echo isset($_POST['form_to_date']) ? '&form_to_date=' . urlencode($_POST['form_to_date']) : ''; ?>" 
       class="button">
        Export to CSV
    </a>
</div>

<div class="stats">
    <h3>Report Summary</h3>
    <div class="stats-grid">
        <div class="stat-box">
            <div class="stat-label">Performance Period</div>
            <div class="stat-value" style="font-size: 16px;"><?php echo oeFormatShortDate($form_from_date) . ' to ' . oeFormatShortDate($form_to_date); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Total Rows</div>
            <div class="stat-value"><?php echo count($dd_report_rows); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Unique Patients</div>
            <div class="stat-value"><?php 
                $unique_patients = array();
                foreach ($dd_report_rows as $row) {
                    $unique_patients[$row['PatientID']] = 1;
                }
                echo count($unique_patients); 
            ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Report Generated</div>
            <div class="stat-value" style="font-size: 16px;"><?php echo date('Y-m-d H:i:s'); ?></div>
        </div>
    </div>
</div>

<?php if (count($dd_report_rows) > 0): ?>
<div style="overflow-x: auto; background-color: white; border-radius: 5px; padding: 10px;">
    <table>
        <thead>
            <tr>
                <th>PID</th>
                <th>Patient ID</th>
                <th>First Name</th>
                <th>Last Name</th>
                <th>DOB</th>
                <th>Sex</th>
                <th>DOS</th>
                <th>Measure #</th>
                <th>CPT</th>
                <th>CPT Mod</th>
                <th>ICD10</th>
                <th>Numerator</th>
                <th>Num Mod</th>
                <th>Assessment Type</th>
                <th>Assessment Score</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($dd_report_rows as $row): ?>
            <tr class="measure-<?php echo htmlspecialchars($row['Measure Number']); ?>">
                <td><?php echo htmlspecialchars($row['pid']); ?></td>   
                <td><?php echo htmlspecialchars($row['PatientID']); ?></td>
                <td><?php echo htmlspecialchars($row['First Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Last Name']); ?></td>
                <td><?php echo htmlspecialchars($row['Date of Birth']); ?></td>
                <td><?php echo htmlspecialchars($row['Sex']); ?></td>
                <td><?php echo htmlspecialchars($row['Date of Service']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['Measure Number']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['CPT']); ?></td>
                <td><?php echo htmlspecialchars($row['Modifier']); ?></td>
                <td><?php echo htmlspecialchars($row['ICD10']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['Numerator']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['Modifier']); ?></td>
                <td><?php echo htmlspecialchars($row['Assessment Type']); ?></td>
                <td><?php echo htmlspecialchars($row['Assessment Score']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
<div class="stats">
    <p style="text-align: center; color: #666; padding: 40px;">
        No patients found matching the criteria for the selected date range.
    </p>
</div>
<?php endif; ?>

<div style="margin-top: 20px; padding: 15px; background-color: white; border-radius: 5px; font-size: 12px; color: #666;">
    <h4>Column Descriptions:</h4>
    <ul style="columns: 2; -webkit-columns: 2; -moz-columns: 2;">
        <li><strong>Patient ID:</strong> Medical record number</li>
        <li><strong>Measure #:</strong> Quality measure number (039, 176, 177, 178, 180, 374)</li>
        <li><strong>CPT:</strong> Encounter CPT code</li>
        <li><strong>CPT Mod:</strong> CPT modifier (if applicable)</li>
        <li><strong>ICD10:</strong> Rheumatoid arthritis diagnosis code</li>
        <li><strong>Numerator:</strong> Performance/numerator code</li>
        <li><strong>Num Mod:</strong> Additional codes/modifiers for measure</li>
        <li><strong>Assessment Type:</strong> Type of assessment performed</li>
        <li><strong>Assessment Score:</strong> Result or status of assessment</li>
    </ul>
</div>

</body>
</html>