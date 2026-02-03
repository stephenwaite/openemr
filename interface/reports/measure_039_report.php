<?php
/**
 * MIPS Quality Measures Report - 2025 Performance Year
 *
 * Measures included: 039
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
    '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215'
);

// Rank function for measure 039 (higher = better)
function qm039_rank($numerator)
{
    switch ($numerator) {
        case 'G8399': return 2; // Completed
        case 'G8400': return 1; // Not Completed
        default: return 0;
    }
}

// Measure definitions
$measures = array(
    '039' => array('name' => 'Osteoporosis Screening', 'applies_to' => 'female_65_85'),
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

$dd_report_rows = array();
$best_results = array();

if ($res) {
    while ($row = sqlFetchArray($res)) {

        $pid = $row['pid'];
        $mips_enc_date = substr($row['date'], 0, 10);

        // Check for qualifying encounter CPT code
        $bres = sqlStatement(
            "SELECT code FROM billing WHERE encounter = ? AND code_type LIKE 'CPT%'",
            array($row['encounter'])
        );

        $cpt_code = null;
        while ($brow = sqlFetchArray($bres)) {
            if (in_array($brow['code'], $encounter_cpt_codes, true)) {
                $cpt_code = $brow['code'];
                break;
            }
        }
        if (!$cpt_code) {
            continue;
        }

        // Get ALL ICD-10 codes for the encounter (for manual exception assessment)
        $icd10_codes = array();
        $dxres = sqlStatement(
            "SELECT code FROM billing 
             WHERE pid = ? 
               AND encounter = ? 
               AND code_type = 'ICD10'
             ORDER BY code",
            array($pid, $row['encounter'])
        );
        while ($dxrow = sqlFetchArray($dxres)) {
            $icd10_codes[] = $dxrow['code'];
        }
        $icd10_all = implode(', ', $icd10_codes);

        // Get rule_patient_data result for act_osteo
        $rule_result = '';
        $rule_res = sqlStatement(
            "SELECT result FROM rule_patient_data 
             WHERE pid = ? 
               AND item = 'act_osteo'
             ORDER BY id DESC
             LIMIT 1",
            array($pid)
        );
        if ($rule_row = sqlFetchArray($rule_res)) {
            $rule_result = $rule_row['result'];
        }

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
            'icd10' => $icd10_all,
            'rule_result' => $rule_result
        );

        // ========== MEASURE 039: Osteoporosis Screening for Women Aged 65–85 ==========

        $measure_result = null;
        $result_text = '';
        $has_dexa = false;
        $dexa_control_id = '';
        $dexa_date = '';

        // female 65-85
        if (
            in_array(strtoupper(trim($row['sex'])), array('F', 'FEMALE')) &&
            $row['age'] >= 65 && $row['age'] <= 85
        ) {

            // Look for BDENSE procedure order ANYTIME - get the most recent one
            $proc = sqlStatement(
                "SELECT control_id, date_transmitted 
                 FROM procedure_order 
                 WHERE patient_id = ? 
                   AND control_id LIKE '%BDENSE%'
                 ORDER BY date_transmitted DESC
                 LIMIT 1",
                array($pid)
            );

            if ($procrow = sqlFetchArray($proc)) {
                $has_dexa = true;
                $dexa_control_id = $procrow['control_id'];
                $dexa_date = $procrow['date_transmitted'] ? substr($procrow['date_transmitted'], 0, 10) : '';
                $result_text = "DEXA found: " . $dexa_control_id . " on " . $dexa_date;
            } else {
                $result_text = "No DEXA procedure found";
            }

            $measure_result = array(
                'numerator' => $has_dexa ? 'G8399' : 'G8400',
                'modifier' => '',
                'assessment_type' => 'DXA Screening (BDENSE)',
                'assessment_score' => $has_dexa ? 'Completed' : 'Not Completed',
                'result_text' => $result_text,
                'dexa_control_id' => $dexa_control_id,
                'dexa_date' => $dexa_date
            );
        }

        // Ranking logic: store best result per patient
        if ($measure_result) {

            $rank = qm039_rank($measure_result['numerator']);

            if (!isset($best_results[$pid]) || $rank > $best_results[$pid]['rank']) {

                $best_results[$pid] = array(
                    'rank' => $rank,
                    'base' => $patient_base,
                    'measure' => $measure_result
                );
            }
        }
    }
}

// Step 7: Output rows (one per patient)
foreach ($best_results as $pid => $entry) {

    $base = $entry['base'];
    $m = $entry['measure'];

    $dd_report_rows[] = array(
        'pid' => $base['pid'],
        'PatientID' => $base['patient_id'],
        'First Name' => $base['first_name'],
        'Last Name' => $base['last_name'],
        'Date of Birth' => $base['dob'],
        'Sex' => $base['sex'],
        'Age' => $base['age'],
        'Date of Service' => $base['dos'],
        'Measure Number' => '039',
        'CPT' => $base['cpt'],
        'ICD10' => $base['icd10'],
        'Rule Result' => $base['rule_result'],
        'Numerator' => $m['numerator'],
        'Modifier' => $m['modifier'],
        'Assessment Type' => $m['assessment_type'],
        'Assessment Score' => $m['assessment_score'],
        'DEXA Control ID' => $m['dexa_control_id'],
        'DEXA Date' => $m['dexa_date'],
        'Result' => $m['result_text']
    );
}

// ========== CSV Export ==========
if ($export_csv) {
    $filename = "MIPS_2025_QM039_Report_" . date('Y-m-d_His') . ".csv";

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
        'Age',
        'Date_of_Service',
        'Measure_Number',
        'CPT',
        'ICD10_Codes',
        'Rule_Result',
        'Numerator',
        'Numerator_Modifier',
        'Assessment_Type',
        'Assessment_Score',
        'DEXA_Control_ID',
        'DEXA_Date',
        'Result'
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
            $row['Age'],
            $row['Date of Service'],
            $row['Measure Number'],
            $row['CPT'],
            $row['ICD10'],
            $row['Rule Result'],
            $row['Numerator'],
            $row['Modifier'],
            $row['Assessment Type'],
            $row['Assessment Score'],
            $row['DEXA Control ID'],
            $row['DEXA Date'],
            $row['Result']
        ));
    }

    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MIPS 2025 Quality Measure 039 Report</title>
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
            vertical-align: top;
        }
        tr:hover {
            background-color: #f5f5f5;
        }
        .measure-039 { background-color: #e3f2fd; }
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
            background-color: #e8f5e9;
            border: 1px solid #4caf50;
            border-radius: 4px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .info-box h4 {
            margin-top: 0;
            color: #2e7d32;
        }
        .info-box ul {
            margin: 10px 0;
            padding-left: 20px;
        }
    </style>
</head>
<body>

<div class="header">
    <h1>MIPS 2025 - Quality Measure 039: Osteoporosis Screening</h1>
    <p style="margin: 5px 0 0 0; opacity: 0.9;">Women aged 65-85 years with DXA scan</p>
</div>

<div class="info-box">
    <h4>Report Requirements - QM 039</h4>
    <ul>
        <li><strong>Population:</strong> Female patients aged 65-85 at time of encounter</li>
        <li><strong>Encounter Period:</strong> During the performance period (<?php echo oeFormatShortDate($form_from_date) . ' to ' . oeFormatShortDate($form_to_date); ?>)</li>
        <li><strong>ICD-10 Codes:</strong> All diagnosis codes shown for manual exception assessment</li>
        <li><strong>DEXA Procedure:</strong> Searched in procedure_order table where control_id contains 'BDENSE' (at any time)</li>
        <li><strong>Most Recent:</strong> If multiple DEXA procedures exist, only the most recent is displayed</li>
    </ul>
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
            <div class="stat-label">Total Patients</div>
            <div class="stat-value"><?php echo count($dd_report_rows); ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">With DEXA Scan</div>
            <div class="stat-value"><?php 
                $with_dexa = 0;
                foreach ($dd_report_rows as $row) {
                    if ($row['Numerator'] === 'G8399') $with_dexa++;
                }
                echo $with_dexa; 
            ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Without DEXA Scan</div>
            <div class="stat-value"><?php 
                $without_dexa = 0;
                foreach ($dd_report_rows as $row) {
                    if ($row['Numerator'] === 'G8400') $without_dexa++;
                }
                echo $without_dexa; 
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
                <th>Age</th>
                <th>DOS</th>
                <th>Measure #</th>
                <th>CPT</th>
                <th>ICD10 Codes</th>
                <th>Rule Result</th>
                <th>Numerator</th>
                <th>Assessment Score</th>
                <th>DEXA Control ID</th>
                <th>DEXA Date</th>
                <th>Result</th>
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
                <td><?php echo htmlspecialchars($row['Age']); ?></td>
                <td><?php echo htmlspecialchars($row['Date of Service']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['Measure Number']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['CPT']); ?></td>
                <td style="max-width: 200px; word-wrap: break-word;"><?php echo htmlspecialchars($row['ICD10']); ?></td>
                <td><?php echo htmlspecialchars($row['Rule Result']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['Numerator']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['Assessment Score']); ?></td>
                <td><?php echo htmlspecialchars($row['DEXA Control ID']); ?></td>
                <td><?php echo htmlspecialchars($row['DEXA Date']); ?></td>
                <td><?php echo htmlspecialchars($row['Result']); ?></td>
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
        <li><strong>Age:</strong> Patient age at time of encounter</li>
        <li><strong>DOS:</strong> Date of service (encounter date)</li>
        <li><strong>Measure #:</strong> Quality measure number (039)</li>
        <li><strong>CPT:</strong> Encounter CPT code</li>
        <li><strong>ICD10 Codes:</strong> All diagnosis codes for manual exception assessment</li>
        <li><strong>Rule Result:</strong> Result from rule_patient_data table (act_osteo)</li>
        <li><strong>Numerator:</strong> G8399 (completed) or G8400 (not completed)</li>
        <li><strong>DEXA Control ID:</strong> Control ID from procedure_order table</li>
        <li><strong>DEXA Date:</strong> Date transmitted from procedure_order table</li>
        <li><strong>Result:</strong> Summary of DEXA procedure status</li>
    </ul>
    
    <h4 style="margin-top: 20px;">Quality Measure 039 Notes:</h4>
    <ul>
        <li>Patients must be female and between ages 65-85 at the time of the encounter</li>
        <li>Encounter must occur during the performance period</li>
        <li>DEXA procedure search looks for control_id containing 'BDENSE' at any time in patient history</li>
        <li>If multiple DEXA procedures exist, only the most recent is shown</li>
        <li>ICD-10 codes are displayed for manual assessment of diagnosis-based exceptions</li>
    </ul>
</div>

</body>
</html>