<?php
/**
 * MIPS Quality Measures Report - 2025 Performance Year
 *
 * Measures included: 176
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
    '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
    '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
    '99424', '99426', 'G0402', 'G0468'
);

// Biologic and immune response modifier medications per MIPS 176 specification
$biologic_medications = array(
    'Adalimumab', 'Adalimumab-aacf', 'Adalimumab-aaty', 'Adalimumab-adaz', 'Adalimumab-adbm',
    'Adalimumab-afzb', 'YusimryAdalimumab-atto', 'Adalimumab-aqvh', 'Adalimumab-bwwd', 'Adalimumab-fkjp',
    'Anakinra', 'Baricitinib', 'Brodalumab', 'Canakinumab', 'Certolizumab',
    'lyophilized certolizumab pegol', 'Etanercept', 'Golimumab', 'Guselkumab', 'Infliximab',
    'Infliximab-abda', 'Infliximab-axxq', 'Infliximab-dyyb', 'Ixekizumab', 'Risankizumab-rzaa',
    'Sarilumab', 'Secukinumab', 'Tildrakizumab', 'Tocilizumab', 'Tofacitinib',
    'Upadacitinib', 'Ustekinumab', 'Orencia', 'HUMIRA', 'Idacio',
    'Yuflyma', 'Hyrimoz', 'Cyltezo', 'Abrilada', 'Amjevita',
    'Hadlima', 'Hulio', 'Kineret', 'Olumiant', 'Siliq',
    'ILARIS', 'CIMZIA', 'Enbrel', 'Simponi', 'Tremfya',
    'REMICADE', 'Renflexis', 'Avsola', 'Inflectra', 'Taltz',
    'Skyrizi', 'KEVZARA', 'Cosentyx', 'Ilumya', 'ACTEMRA',
    'XELJANZ', 'RINVOQ', 'STELARA', 'therapy', 'biologic',
    'immune', 'modifier'
);

// Helper function to build medication WHERE clause
function build_medication_where_clause($medications) {
    $conditions = array();
    foreach ($medications as $med) {
        $conditions[] = "l.title LIKE '%" . mysqli_real_escape_string($GLOBALS['dbh'], $med) . "%'";
    }
    return '(' . implode(' OR ', $conditions) . ')';
}

// Rank function for measure 176 (higher = better)
function qm176_rank($numerator)
{
    switch ($numerator) {
        case 'M1003': return 2; // TB screening performed
        case 'M1004': return 1; // Medical exception
        case 'M1005': return 0; // Not performed
        default: return 0;
    }
}

// Measure definitions
$measures = array(
    '176' => array('name' => 'TB Screening Prior to First Course Biologic Therapy', 'applies_to' => 'adults_18_plus'),
);

$sqlBindArray = array();

// Build medication WHERE clause from array
$medication_where = build_medication_where_clause($biologic_medications);

$query = "SELECT COUNT(DISTINCT p.pid) as total_patients
FROM form_encounter AS fe
INNER JOIN forms AS f 
    ON f.pid = fe.pid 
    AND f.encounter = fe.encounter 
    AND f.formdir = 'newpatient'
INNER JOIN patient_data AS p 
    ON p.pid = fe.pid
INNER JOIN billing AS b 
    ON b.encounter = fe.encounter 
    AND b.code_type = 'CPT4'
    AND b.code IN ('" . implode("','", $encounter_cpt_codes) . "')
INNER JOIN lists AS l
    ON l.pid = p.pid
    AND l.type = 'medication' 
    AND " . $medication_where . "
    AND l.date >= '2025-01-01'
WHERE fe.date >= ? AND fe.date <= ?
  AND TIMESTAMPDIFF(YEAR, p.dob, fe.date) >= 18";

array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');

// Main query to get individual patient records
$detail_query = "SELECT " .
    "fe.encounter, fe.date, fe.reason, " .
    "f.formdir, f.form_name, " .
    "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.dob, p.sex, " .
    "TIMESTAMPDIFF(YEAR, p.dob, fe.date) AS age, " .
    "l.title as medication_title, l.date as medication_date, " .
    "u.lname AS ulname, u.fname AS ufname, u.mname AS umname " .
    "FROM form_encounter AS fe " .
    "INNER JOIN forms AS f ON f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient' " .
    "INNER JOIN patient_data AS p ON p.pid = fe.pid " .
    "INNER JOIN billing AS b ON b.encounter = fe.encounter AND b.code_type = 'CPT4' " .
    "INNER JOIN lists AS l ON l.pid = p.pid AND l.type = 'medication' " .
    "LEFT JOIN users AS u ON u.id = fe.provider_id " .
    "WHERE fe.date >= ? AND fe.date <= ? " .
    "AND TIMESTAMPDIFF(YEAR, p.dob, fe.date) >= 18 " .
    "AND b.code IN ('" . implode("','", $encounter_cpt_codes) . "') " .
    "AND " . $medication_where . " " .
    "AND l.date >= '2025-01-01' " .
    "ORDER BY p.pid ASC";

$sqlBindArray2 = array($form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');
$res = sqlStatement($detail_query, $sqlBindArray2);

$dd_report_rows = array();
$best_results = array();

if ($res) {
    while ($row = sqlFetchArray($res)) {

        $pid = $row['pid'];
        $mips_enc_date = substr($row['date'], 0, 10);

        // Get the CPT code for this encounter
        $bres = sqlStatement(
            "SELECT code FROM billing WHERE encounter = ? AND code_type = 'CPT4'",
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

        // Get rule_patient_data result for act_tb within 12 months prior to encounter
        // MIPS 176 requires TB screening within 12 months BEFORE biologic initiation
        $twelve_months_prior = date('Y-m-d', strtotime('-12 months', strtotime($mips_enc_date)));
        $rule_result = '';
        $rule_res = sqlStatement(
            "SELECT result, date FROM rule_patient_data 
             WHERE pid = ? 
               AND item = 'act_tb'
               AND date > ?
               AND date <= ?
             ORDER BY date DESC
             LIMIT 1",
            array($pid, $twelve_months_prior, $mips_enc_date)
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
            'medication_title' => $row['medication_title'],
            'medication_date' => substr($row['medication_date'], 0, 10),
            'rule_result' => $rule_result
        );

        // ========== MEASURE 176: TB Screening Prior to First Course Biologic Therapy ==========

        $measure_result = null;
        $result_text = '';

        // Check if patient is 18 or older
        if ($row['age'] >= 18) {

            // Determine numerator based on rule_result
            $numerator = 'M1005'; // Default: not performed
            $assessment_score = 'Not Performed';
            
            if (!empty($rule_result)) {
                // Check for date-based evidence (e.g., "2019", "2020", "2024")
                // OR keyword-based evidence (e.g., "performed", "completed")
                if (stripos($rule_result, '19') !== false || 
                    stripos($rule_result, '20') !== false ||
                    stripos($rule_result, 'performed') !== false || 
                    stripos($rule_result, 'completed') !== false ||
                    stripos($rule_result, 'positive') !== false ||
                    stripos($rule_result, 'negative') !== false) {
                    $numerator = 'M1003';
                    $assessment_score = 'TB Screening Performed';
                    $result_text = "TB screening performed and documented: " . $rule_result;
                } elseif (stripos($rule_result, 'exception') !== false || 
                          stripos($rule_result, 'medical reason') !== false ||
                          stripos($rule_result, 'past treatment') !== false ||
                          stripos($rule_result, 'anti-TB') !== false) {
                    $numerator = 'M1004';
                    $assessment_score = 'Medical Exception';
                    $result_text = "Medical exception documented: " . $rule_result;
                } else {
                    $result_text = "TB screening record found but inconclusive: " . $rule_result;
                }
            } else {
                $result_text = "No TB screening record found within 12 months prior to encounter";
            }

            $measure_result = array(
                'numerator' => $numerator,
                'modifier' => '',
                'assessment_type' => 'TB Screening',
                'assessment_score' => $assessment_score,
                'result_text' => $result_text
            );
        }

        // Ranking logic: store best result per patient
        if ($measure_result) {

            $rank = qm176_rank($measure_result['numerator']);

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
        'Measure Number' => '176',
        'CPT' => $base['cpt'],
        'ICD10' => $base['icd10'],
        'Medication' => $base['medication_title'],
        'Medication Date' => $base['medication_date'],
        'Rule Result' => $base['rule_result'],
        'Numerator' => $m['numerator'],
        'Assessment Score' => $m['assessment_score'],
        'Result' => $m['result_text']
    );
}

// CSV Export
if ($export_csv) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="mips_176_report_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if (count($dd_report_rows) > 0) {
        fputcsv($output, array_keys($dd_report_rows[0]));
        foreach ($dd_report_rows as $row) {
            fputcsv($output, $row);
        }
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
    <title>MIPS 2025 - Quality Measure 176</title>
    <link rel="stylesheet" href="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-datetimepicker/build/jquery.datetimepicker.min.css">
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery/dist/jquery.min.js"></script>
    <script src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-datetimepicker/build/jquery.datetimepicker.full.min.js"></script>
    
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            min-height: 100vh;
        }
        .header {
            background: white;
            padding: 25px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            color: #2c5aa0;
            font-size: 28px;
            margin-bottom: 5px;
        }
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .button {
            background-color: #2c5aa0;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            font-size: 14px;
            font-weight: 500;
            transition: background-color 0.2s;
        }
        .button:hover {
            background-color: #1e3a6f;
        }
        .button.secondary {
            background-color: #4caf50;
        }
        .button.secondary:hover {
            background-color: #388e3c;
        }
        .stats {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .stats h3 {
            color: #333;
            margin-bottom: 15px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .stat-box {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 5px;
            border-left: 4px solid #2c5aa0;
        }
        .stat-label {
            font-size: 12px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 5px;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            color: #2c5aa0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 12px;
            background: white;
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
        .measure-176 { background-color: #e3f2fd; }
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
    <h1>MIPS 2025 - Quality Measure 176: TB Screening Prior to First Course Biologic Therapy</h1>
    <p style="margin: 5px 0 0 0; opacity: 0.9;">Patients aged 18+ receiving first-time biologic and/or immune response modifier therapy</p>
</div>

<div class="info-box">
    <h4>Report Requirements - QM 176</h4>
    <ul>
        <li><strong>Population:</strong> Patients aged 18+ at time of encounter</li>
        <li><strong>Encounter Period:</strong> During the performance period (<?php echo oeFormatShortDate($form_from_date) . ' to ' . oeFormatShortDate($form_to_date); ?>)</li>
        <li><strong>Qualifying Medications:</strong> First-time prescription of biologic and/or immune response modifier on or after 1/1/2025</li>
        <li><strong>TB Screening:</strong> Must be documented in rule_patient_data (item = 'act_tb') within 12 months PRIOR to encounter date</li>
        <li><strong>Denominator Code:</strong> G2182 (Patient receiving first-time biologic and/or immune response modifier therapy)</li>
        <li><strong>Numerator Codes:</strong> M1003 (performed), M1004 (medical exception), M1005 (not performed)</li>
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
            <div class="stat-label">TB Screening Performed</div>
            <div class="stat-value"><?php 
                $performed = 0;
                foreach ($dd_report_rows as $row) {
                    if ($row['Numerator'] === 'M1003') $performed++;
                }
                echo $performed; 
            ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Medical Exception</div>
            <div class="stat-value"><?php 
                $exception = 0;
                foreach ($dd_report_rows as $row) {
                    if ($row['Numerator'] === 'M1004') $exception++;
                }
                echo $exception; 
            ?></div>
        </div>
        <div class="stat-box">
            <div class="stat-label">Not Performed</div>
            <div class="stat-value"><?php 
                $not_performed = 0;
                foreach ($dd_report_rows as $row) {
                    if ($row['Numerator'] === 'M1005') $not_performed++;
                }
                echo $not_performed; 
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
                <th>Medication</th>
                <th>Medication Date</th>
                <th>Rule Result</th>
                <th>Numerator</th>
                <th>Assessment Score</th>
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
                <td style="max-width: 250px; word-wrap: break-word;"><?php echo htmlspecialchars($row['Medication']); ?></td>
                <td><?php echo htmlspecialchars($row['Medication Date']); ?></td>
                <td><?php echo htmlspecialchars($row['Rule Result']); ?></td>
                <td><strong><?php echo htmlspecialchars($row['Numerator']); ?></strong></td>
                <td><?php echo htmlspecialchars($row['Assessment Score']); ?></td>
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



<script>
jQuery(function($) {
    $('.datepicker').datetimepicker({
        timepicker: false,
        format: 'Y-m-d'
    });
});
</script>

</body>
</html>