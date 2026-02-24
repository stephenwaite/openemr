<?php
/**
 * MIPS Quality Measure 176 - Tuberculosis Screening Prior to First Course 
 * of Biologic and/or Immune Response Modifier Therapy
 * 
 * DENOMINATOR REPORT ONLY
 * This report identifies patients who meet the denominator criteria.
 * 
 * Denominator Criteria:
 * - Patients aged >= 18 years on date of encounter
 * - Patient encounter during the performance period with qualifying CPT/HCPCS codes
 * - Diagnosis of PsA, Lupus SLE, Vasculitis, or RA during the performance period
 * - Patient receiving first-time biologic and/or immune response modifier therapy (G2182)
 * - Dictation text (fd.dictation) contains biologic/immune therapy keywords
 */

// Include OpenEMR required files
require_once("../globals.php");
require_once("$srcdir/sql.inc.php");

// Performance period dates (modify as needed)
$performancePeriodStart = '2025-01-01';
$performancePeriodEnd = '2025-12-31';

// Qualifying encounter CPT/HCPCS codes for 2025
$encounterCodes = array(
    '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
    '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
    '99424', '99426', 'G0402', 'G0468'
);
$encounterCodesStr = "'" . implode("','", $encounterCodes) . "'";

// PsA DX codes — removed trailing spaces from originals
$psaDxCodes = array('L40.50','L40.51','L40.52','L40.53','L40.54','L40.55','L40.56','L40.57','L40.58','L40.59');

// Lupus SLE DX codes — removed trailing spaces from originals
$lupusDxCodes = array('D68.62','H01.121','H01.122','H01.123','H01.124','H01.125','H01.126','H01.129',
    'L93.0','L93.1','M32.0','M32.10','M32.11','M32.12','M32.13','M32.14',
    'M32.15','M32.19','M32.8','M32.9');

// Vasculitis DX codes — removed trailing spaces from originals
$vasculitisDxCodes = array('H35.061','H35.062','H35.063','H35.069','I77.82','L95.0','L95.8','L95.9',
    'M05.20','M05.211','M05.212','M05.219','M05.221','M05.222','M05.229',
    'M05.231','M05.232','M05.239','M05.241','M05.242','M05.249','M05.251',
    'M05.252','M05.259','M05.261','M05.262','M05.269','M05.271','M05.272','M05.279','M05.29','M38.0B');

// RA DX codes
$raDxCodes = array(
    'M05.00', 'M05.011', 'M05.012', 'M05.019', 'M05.021', 'M05.022', 'M05.029', 'M05.031', 'M05.032', 'M05.039',
    'M05.041', 'M05.042', 'M05.049', 'M05.051', 'M05.052', 'M05.059', 'M05.061', 'M05.062', 'M05.069', 'M05.071',
    'M05.072', 'M05.079', 'M05.09', 'M05.10', 'M05.111', 'M05.112', 'M05.119', 'M05.121', 'M05.122', 'M05.129',
    'M05.131', 'M05.132', 'M05.139', 'M05.141', 'M05.142', 'M05.149', 'M05.151', 'M05.152', 'M05.159', 'M05.161',
    'M05.162', 'M05.169', 'M05.171', 'M05.172', 'M05.179', 'M05.19', 'M05.20', 'M05.211', 'M05.212', 'M05.219',
    'M05.221', 'M05.222', 'M05.229', 'M05.231', 'M05.232', 'M05.239', 'M05.241', 'M05.242', 'M05.249', 'M05.251',
    'M05.252', 'M05.259', 'M05.261', 'M05.262', 'M05.269', 'M05.271', 'M05.272', 'M05.279', 'M05.29', 'M05.30',
    'M05.311', 'M05.312', 'M05.319', 'M05.321', 'M05.322', 'M05.329', 'M05.331', 'M05.332', 'M05.339', 'M05.341',
    'M05.342', 'M05.349', 'M05.351', 'M05.352', 'M05.359', 'M05.361', 'M05.362', 'M05.369', 'M05.371', 'M05.372',
    'M05.379', 'M05.39', 'M05.40', 'M05.411', 'M05.412', 'M05.419', 'M05.421', 'M05.422', 'M05.429', 'M05.431',
    'M05.432', 'M05.439', 'M05.441', 'M05.442', 'M05.449', 'M05.451', 'M05.452', 'M05.459', 'M05.461', 'M05.462',
    'M05.469', 'M05.471', 'M05.472', 'M05.479', 'M05.49', 'M05.50', 'M05.511', 'M05.512', 'M05.519', 'M05.521',
    'M05.522', 'M05.529', 'M05.531', 'M05.532', 'M05.539', 'M05.541', 'M05.542', 'M05.549', 'M05.551', 'M05.552',
    'M05.559', 'M05.561', 'M05.562', 'M05.569', 'M05.571', 'M05.572', 'M05.579', 'M05.59', 'M05.60', 'M05.611',
    'M05.612', 'M05.619', 'M05.621', 'M05.622', 'M05.629', 'M05.631', 'M05.632', 'M05.639', 'M05.641', 'M05.642',
    'M05.649', 'M05.651', 'M05.652', 'M05.659', 'M05.661', 'M05.662', 'M05.669', 'M05.671', 'M05.672', 'M05.679',
    'M05.69', 'M05.7A', 'M05.70', 'M05.711', 'M05.712', 'M05.719', 'M05.721', 'M05.722', 'M05.729', 'M05.731',
    'M05.732', 'M05.739', 'M05.741', 'M05.742', 'M05.749', 'M05.751', 'M05.752', 'M05.759', 'M05.761', 'M05.762',
    'M05.769', 'M05.771', 'M05.772', 'M05.779', 'M05.79', 'M05.8A', 'M05.80', 'M05.811', 'M05.812', 'M05.819',
    'M05.821', 'M05.822', 'M05.829', 'M05.831', 'M05.832', 'M05.839', 'M05.841', 'M05.842', 'M05.849', 'M05.851',
    'M05.852', 'M05.859', 'M05.861', 'M05.862', 'M05.869', 'M05.871', 'M05.872', 'M05.879', 'M05.89', 'M05.9',
    'M06.0A', 'M06.00', 'M06.011', 'M06.012', 'M06.019', 'M06.021', 'M06.022', 'M06.029', 'M06.031', 'M06.032',
    'M06.039', 'M06.041', 'M06.042', 'M06.049', 'M06.051', 'M06.052', 'M06.059', 'M06.061', 'M06.062', 'M06.069',
    'M06.071', 'M06.072', 'M06.079', 'M06.08', 'M06.09', 'M06.20', 'M06.211', 'M06.212', 'M06.219', 'M06.221',
    'M06.222', 'M06.229', 'M06.231', 'M06.232', 'M06.239', 'M06.241', 'M06.242', 'M06.249', 'M06.251', 'M06.252',
    'M06.259', 'M06.261', 'M06.262', 'M06.269', 'M06.271', 'M06.272', 'M06.279', 'M06.28', 'M06.29', 'M06.30',
    'M06.311', 'M06.312', 'M06.319', 'M06.321', 'M06.322', 'M06.329', 'M06.331', 'M06.332', 'M06.339', 'M06.341',
    'M06.342', 'M06.349', 'M06.351', 'M06.352', 'M06.359', 'M06.361', 'M06.362', 'M06.369', 'M06.371', 'M06.372',
    'M06.379', 'M06.38', 'M06.39', 'M06.8A', 'M06.80', 'M06.811', 'M06.812', 'M06.819', 'M06.821', 'M06.822',
    'M06.829', 'M06.831', 'M06.832', 'M06.839', 'M06.841', 'M06.842', 'M06.849', 'M06.851', 'M06.852', 'M06.859',
    'M06.861', 'M06.862', 'M06.869', 'M06.871', 'M06.872', 'M06.879', 'M06.88', 'M06.89', 'M06.9');

// Merge all DX code arrays into one flat IN list
// (separate comma-delimited IN groups are invalid SQL)
$allDxCodes    = array_merge($psaDxCodes, $lupusDxCodes, $vasculitisDxCodes, $raDxCodes);
$allDxCodesStr = "'" . implode("','", $allDxCodes) . "'";

// Build therapy keyword conditions against fd.dictation (case-insensitive)
$therapyKeywords = "
    (LOWER(fd.dictation) LIKE '%abatacept%'
    OR LOWER(fd.dictation) LIKE '%adalimumab%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-aacf%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-aaty%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-adaz%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-adbm%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-afzb%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-atto%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-aqvh%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-bwwd%'
    OR LOWER(fd.dictation) LIKE '%adalimumab-fkjp%'
    OR LOWER(fd.dictation) LIKE '%anakinra%'
    OR LOWER(fd.dictation) LIKE '%baricitinib%'
    OR LOWER(fd.dictation) LIKE '%brodalumab%'
    OR LOWER(fd.dictation) LIKE '%canakinumab%'
    OR LOWER(fd.dictation) LIKE '%certolizumab%'
    OR LOWER(fd.dictation) LIKE '%lyophilized certolizumab pegol%'
    OR LOWER(fd.dictation) LIKE '%etanercept%'
    OR LOWER(fd.dictation) LIKE '%golimumab%'
    OR LOWER(fd.dictation) LIKE '%guselkumab%'
    OR LOWER(fd.dictation) LIKE '%infliximab%'
    OR LOWER(fd.dictation) LIKE '%infliximab-abda%'
    OR LOWER(fd.dictation) LIKE '%infliximab-axxq%'
    OR LOWER(fd.dictation) LIKE '%infliximab-dyyb%'
    OR LOWER(fd.dictation) LIKE '%ixekizumab%'
    OR LOWER(fd.dictation) LIKE '%risankizumab-rzaa%'
    OR LOWER(fd.dictation) LIKE '%sarilumab%'
    OR LOWER(fd.dictation) LIKE '%secukinumab%'
    OR LOWER(fd.dictation) LIKE '%tildrakizumab%'
    OR LOWER(fd.dictation) LIKE '%tocilizumab%'
    OR LOWER(fd.dictation) LIKE '%tofacitinib%'
    OR LOWER(fd.dictation) LIKE '%upadacitinib%'
    OR LOWER(fd.dictation) LIKE '%ustekinumab%'
    OR LOWER(fd.dictation) LIKE '%orencia%'
    OR LOWER(fd.dictation) LIKE '%humira%'
    OR LOWER(fd.dictation) LIKE '%idacio%'
    OR LOWER(fd.dictation) LIKE '%yuflyma%'
    OR LOWER(fd.dictation) LIKE '%hyrimoz%'
    OR LOWER(fd.dictation) LIKE '%cyltezo%'
    OR LOWER(fd.dictation) LIKE '%abrilada%'
    OR LOWER(fd.dictation) LIKE '%amjevita%'
    OR LOWER(fd.dictation) LIKE '%hadlima%'
    OR LOWER(fd.dictation) LIKE '%hulio%'
    OR LOWER(fd.dictation) LIKE '%kineret%'
    OR LOWER(fd.dictation) LIKE '%olumiant%'
    OR LOWER(fd.dictation) LIKE '%siliq%'
    OR LOWER(fd.dictation) LIKE '%ilaris%'
    OR LOWER(fd.dictation) LIKE '%cimzia%'
    OR LOWER(fd.dictation) LIKE '%enbrel%'
    OR LOWER(fd.dictation) LIKE '%simponi%'
    OR LOWER(fd.dictation) LIKE '%tremfya%'
    OR LOWER(fd.dictation) LIKE '%remicade%'
    OR LOWER(fd.dictation) LIKE '%renflexis%'
    OR LOWER(fd.dictation) LIKE '%avsola%'
    OR LOWER(fd.dictation) LIKE '%inflectra%'
    OR LOWER(fd.dictation) LIKE '%taltz%'
    OR LOWER(fd.dictation) LIKE '%skyrizi%'
    OR LOWER(fd.dictation) LIKE '%kevzara%'
    OR LOWER(fd.dictation) LIKE '%cosentyx%'
    OR LOWER(fd.dictation) LIKE '%ilumya%'
    OR LOWER(fd.dictation) LIKE '%actemra%'
    OR LOWER(fd.dictation) LIKE '%xeljanz%'
    OR LOWER(fd.dictation) LIKE '%rinvoq%'
    OR LOWER(fd.dictation) LIKE '%stelara%'
    OR LOWER(fd.dictation) LIKE '%therapy%'
    OR LOWER(fd.dictation) LIKE '%biologic%'
    OR LOWER(fd.dictation) LIKE '%immune%'
    OR LOWER(fd.dictation) LIKE '%modifier%')
";

$sql = "
SELECT
    pd.pid,
    pd.lname AS last_name,
    pd.fname AS first_name,
    pd.DOB AS date_of_birth,
    pd.sex,
    TIMESTAMPDIFF(YEAR, pd.DOB, MIN(fe.date)) AS age_at_first_encounter,
    COUNT(DISTINCT fe.encounter) AS encounter_count,
    MIN(fe.date) AS first_encounter_date,
    MAX(fe.date) AS last_encounter_date,
    GROUP_CONCAT(DISTINCT fe.encounter ORDER BY fe.date SEPARATOR ', ') AS encounter_ids,
    GROUP_CONCAT(DISTINCT b.code ORDER BY b.code SEPARATOR ', ') AS billing_codes
FROM form_dictation fd
JOIN forms f ON f.form_id = fd.id
    AND f.formdir = 'dictation'
    AND f.deleted = 0
JOIN form_encounter fe ON fe.encounter = f.encounter
    AND fe.pid = f.pid
JOIN patient_data pd ON pd.pid = f.pid
JOIN billing b ON b.encounter = f.encounter
    AND b.pid = f.pid
    AND b.activity = 1
WHERE
    fe.date BETWEEN '$performancePeriodStart' AND '$performancePeriodEnd'
    AND TIMESTAMPDIFF(YEAR, pd.DOB, fe.date) >= 18
    AND b.code_type = 'ICD10'
    AND b.code IN ($allDxCodesStr)
    AND $therapyKeywords
GROUP BY
    pd.pid, pd.lname, pd.fname, pd.DOB, pd.sex
ORDER BY
    pd.lname, pd.fname
";

// Execute query
$results = array();
$res = sqlStatement($sql);

while ($row = sqlFetchArray($res)) {
    $results[] = $row;
}

// Check if CSV export is requested
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="MIPS_176_Report_' . date('Y-m-d') . '.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, array(
        'Patient ID',
        'Last Name',
        'First Name',
        'Date of Birth',
        'Sex',
        'Age at First Encounter',
        'Encounter Count',
        'First Encounter Date',
        'Last Encounter Date',
        'Encounter IDs',
        'Billing Codes'
    ));

    foreach ($results as $row) {
        fputcsv($output, array(
            $row['pid'],
            $row['last_name'],
            $row['first_name'],
            $row['date_of_birth'],
            $row['sex'],
            $row['age_at_first_encounter'],
            $row['encounter_count'],
            $row['first_encounter_date'],
            $row['last_encounter_date'],
            $row['encounter_ids'],
            $row['billing_codes']
        ));
    }

    fclose($output);
    exit;
}

// Generate HTML report output
?>
<!DOCTYPE html>
<html>
<head>
    <title>MIPS 176 TB Screening Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h1 { color: #333; }
        .report-info { background: #f0f0f0; padding: 15px; margin-bottom: 20px; border-radius: 5px; }
        .report-info p { margin: 5px 0; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; font-size: 12px; }
        th { background-color: #4CAF50; color: white; padding: 12px; text-align: left; }
        td { border: 1px solid #ddd; padding: 8px; }
        tr:nth-child(even) { background-color: #f2f2f2; }
        tr:hover { background-color: #ddd; }
        .summary { margin-top: 20px; font-weight: bold; font-size: 16px; }
        .note { background: #fff3cd; padding: 10px; margin: 10px 0; border-left: 4px solid #ffc107; }
        .export-btn { display: inline-block; padding: 10px 20px; background: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 10px; }
        .export-btn:hover { background: #45a049; }
        .reason-cell { max-width: 250px; word-wrap: break-word; }
    </style>
</head>
<body>

    <div class="report-info">
        <p><strong>Measure Description:</strong> Tuberculosis Screening Prior to First Course of Biologic and/or Immune Response Modifier Therapy</p>
        <p><strong>Report Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        <p><strong>Performance Period:</strong> <?php echo $performancePeriodStart; ?> to <?php echo $performancePeriodEnd; ?></p>
        <p><a href="?export=csv" class="export-btn">Export to CSV</a></p>
    </div>

    <div class="summary">Total Patients in Denominator: <?php echo count($results); ?></div>

    <?php if (count($results) > 0): ?>
        <table>
            <tr>
                <th>Patient ID</th>
                <th>Patient Name</th>
                <th>DOB</th>
                <th>Age</th>
                <th>Sex</th>
                <th># Encounters</th>
                <th>First Encounter</th>
                <th>Last Encounter</th>
                <th>Encounter IDs</th>
                <th>Billing Codes</th>
            </tr>

            <?php foreach ($results as $row): ?>
            <tr>
                <td><?php echo htmlspecialchars($row['pid']); ?></td>
                <td><?php echo htmlspecialchars($row['last_name'] . ', ' . $row['first_name']); ?></td>
                <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
                <td><?php echo htmlspecialchars($row['age_at_first_encounter']); ?></td>
                <td><?php echo htmlspecialchars($row['sex']); ?></td>
                <td><?php echo htmlspecialchars($row['encounter_count']); ?></td>
                <td><?php echo htmlspecialchars($row['first_encounter_date']); ?></td>
                <td><?php echo htmlspecialchars($row['last_encounter_date']); ?></td>
                <td><?php echo htmlspecialchars($row['encounter_ids']); ?></td>
                <td><?php echo htmlspecialchars($row['billing_codes']); ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
    <?php else: ?>
        <p>No patients found meeting the denominator criteria for the specified performance period.</p>
    <?php endif; ?>

</body>
</html>