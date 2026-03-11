<?php
/**
 * Encounter Medication Reconciliation Report
 *
 * Displays patient encounters with qualifying CPT/HCPCS billing codes
 * during the performance period, along with the closest medication list
 * modification date on or after each encounter date.
 *
 * Columns:
 *   - Patient ID, External ID (pubpid), Last Name, First Name, DOB, Sex
 *   - Age at Encounter, Encounter Date
 *   - Billing Codes (all qualifying CPT/HCPCS codes on that encounter)
 *   - ICD-10 Codes (all diagnosis codes on that encounter)
 *   - Closest Medication List Modify Date (>= encounter date)
 */

// Include OpenEMR required files
require_once("../globals.php");
require_once("$srcdir/sql.inc.php");

// ── Performance Period ────────────────────────────────────────────────────────
$performancePeriodStart = '2025-01-01';
$performancePeriodEnd   = '2025-12-31';

// ── Qualifying Billing Codes (CPT4 + HCPCS) ──────────────────────────────────
$billingCodes = array('59400', '59510', '59610', '59618', '90791', '90792', '90832', '90834', '90837', '90839', '92002', '92004', '92012', '92014', '92507', '92508', '92526', 
'92537', '92538', '92540', '92541', '92542', '92544', '92545', '92548', '92549', '92550', '92557', '92567', '92568', '92570', '92588', '92622', '92626', '92650', '92651', '92652', 
'92653', '96116', '96156', '96158', '97129', '97161', '97162', '97163', '97164', '97165', '97166', '97167', '97168', '97802', '97803', '97804', '98000', '98001', '98002', '98003', 
'98004', '98005', '98006', '98007', '98008', '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016', '98960', '98961', '98962', '99202', '99203', '99204', '99205', 
'99212', '99213', '99214', '99215', '99221', '99222', '99223', '99236', '99281', '99282', '99283', '99284', '99285', '99304', '99305', '99306', '99307', '99308', '99309', '99310', 
'99315', '99316', '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350', '99385', '99386', '99387', '99395', '99396', '99397', '99424', '99491', '99495', '99496', 
'G0101', 'G0108', 'G0270', 'G0402', 'G0438', 'G0439');
$billingCodesStr = "'" . implode("','", $billingCodes) . "'";

// ── SQL ───────────────────────────────────────────────────────────────────────
// One row per encounter. Billing codes are aggregated into a single cell.
// The subquery finds the earliest medication list modifydate >= encounter date.
$sql = "
SELECT
    pd.pid                                                             AS pid,
    pd.pubpid                                                          AS external_id,
    pd.lname                                                           AS last_name,
    pd.fname                                                           AS first_name,
    pd.DOB                                                             AS date_of_birth,
    pd.sex                                                             AS sex,
    TIMESTAMPDIFF(YEAR, pd.DOB, DATE(fe.date))                        AS age_at_encounter,
    DATE(fe.date)                                                      AS encounter_date,
    fe.encounter                                                       AS encounter_id,
    GROUP_CONCAT(DISTINCT b.code ORDER BY b.code SEPARATOR ', ')      AS billing_codes,
    GROUP_CONCAT(DISTINCT bdx.code ORDER BY bdx.code SEPARATOR ', ') AS icd10_codes,
    (
        SELECT MIN(l.modifydate)
        FROM lists l
        WHERE l.pid      = fe.pid
          AND l.type     = 'medication'
          AND DATE(l.modifydate) >= DATE(fe.date)
    )                                                                  AS closest_med_modifydate
FROM form_encounter fe
JOIN patient_data pd
    ON pd.pid = fe.pid
JOIN billing b
    ON  b.encounter  = fe.encounter
    AND b.pid        = fe.pid
    AND b.activity   = 1
    AND b.code_type  IN ('CPT4', 'HCPCS')
    AND b.code       IN ($billingCodesStr)
LEFT JOIN billing bdx
    ON  bdx.encounter = fe.encounter
    AND bdx.pid       = fe.pid
    AND bdx.activity  = 1
    AND bdx.code_type = 'ICD10'
WHERE
    fe.date BETWEEN '$performancePeriodStart 00:00:00'
                AND '$performancePeriodEnd 23:59:59'
    AND TIMESTAMPDIFF(YEAR, pd.DOB, DATE(fe.date)) >= 0
GROUP BY
    pd.pid,
    pd.pubpid,
    pd.lname,
    pd.fname,
    pd.DOB,
    pd.sex,
    fe.encounter,
    DATE(fe.date)
ORDER BY
    pd.lname,
    pd.fname,
    fe.date
";

// ── Execute ───────────────────────────────────────────────────────────────────
$results = array();
$res = sqlStatement($sql);
while ($row = sqlFetchArray($res)) {
    $results[] = $row;
}

// ── CSV Export ────────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="Encounter_Med_Reconciliation_' . date('Y-m-d') . '.csv"');

    $out = fopen('php://output', 'w');

    fputcsv($out, array(
        'Patient ID',
        'External ID',
        'Last Name',
        'First Name',
        'Date of Birth',
        'Sex',
        'Age at Encounter',
        'Encounter Date',
        'Encounter ID',
        'Billing Codes',
        'ICD-10 Codes',
        'Closest Medication List Date (>= Encounter)'
    ));

    foreach ($results as $row) {
        fputcsv($out, array(
            $row['pid'],
            $row['external_id'],
            $row['last_name'],
            $row['first_name'],
            $row['date_of_birth'],
            $row['sex'],
            $row['age_at_encounter'],
            $row['encounter_date'],
            $row['encounter_id'],
            $row['billing_codes'],
            $row['icd10_codes'] ?? '',
            $row['closest_med_modifydate'] ?? ''
        ));
    }

    fclose($out);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Encounter Medication Reconciliation Report</title>
    <style>
        body        { font-family: Arial, sans-serif; margin: 20px; font-size: 13px; }
        h1          { color: #2c3e50; margin-bottom: 5px; }
        .report-info {
            background: #eaf0fb; padding: 14px 18px; margin-bottom: 18px;
            border-radius: 6px; border-left: 5px solid #3a7bd5;
        }
        .report-info p  { margin: 4px 0; }
        .summary        { font-size: 15px; font-weight: bold; margin-bottom: 10px; }
        .export-btn {
            display: inline-block; padding: 9px 20px; background: #3a7bd5;
            color: #fff; text-decoration: none; border-radius: 5px; margin-top: 8px;
        }
        .export-btn:hover { background: #2c60b0; }
        .note {
            background: #fff8e1; padding: 10px 14px; margin-bottom: 14px;
            border-left: 4px solid #f0c040; border-radius: 3px;
        }
        table           { border-collapse: collapse; width: 100%; margin-top: 10px; }
        th {
            background-color: #3a7bd5; color: #fff;
            padding: 10px 8px; text-align: left; white-space: nowrap;
        }
        td              { border: 1px solid #d0d8e8; padding: 7px 8px; vertical-align: top; }
        tr:nth-child(even) { background-color: #f4f7fc; }
        tr:hover        { background-color: #dce8fa; }
        .no-data        { color: #888; font-style: italic; }
        .highlight-none { color: #c0392b; font-style: italic; }
    </style>
</head>
<body>

<h1>Encounter Medication Reconciliation Report</h1>

<div class="report-info">
    <p><strong>Description:</strong> Patient encounters with qualifying billing codes, with the closest medication list update date on or after each encounter.</p>
    <p><strong>Report Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    <p><strong>Performance Period:</strong> <?php echo $performancePeriodStart; ?> &mdash; <?php echo $performancePeriodEnd; ?></p>
    <p><a href="?export=csv" class="export-btn">&#8659; Export to CSV</a></p>
</div>

<div class="note">
    <strong>Medication Date Column:</strong> Shows the earliest date a medication list entry was modified on or after the encounter date for that patient.
    <em>"None found"</em> means no medication list update was recorded on or after that encounter.
</div>

<div class="summary">Total Encounters: <?php echo count($results); ?></div>

<?php if (count($results) > 0): ?>
<table>
    <thead>
    <tr>
        <th>Patient ID</th>
        <th>External ID</th>
        <th>Last Name</th>
        <th>First Name</th>
        <th>DOB</th>
        <th>Sex</th>
        <th>Age</th>
        <th>Encounter Date</th>
        <th>Encounter ID</th>
        <th>Billing Codes</th>
        <th>ICD-10 Codes</th>
        <th>Closest Med List Date (&#8805; Encounter)</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($results as $row): ?>
    <tr>
        <td><?php echo htmlspecialchars($row['pid']); ?></td>
        <td><?php echo htmlspecialchars($row['external_id']); ?></td>
        <td><?php echo htmlspecialchars($row['last_name']); ?></td>
        <td><?php echo htmlspecialchars($row['first_name']); ?></td>
        <td><?php echo htmlspecialchars($row['date_of_birth']); ?></td>
        <td><?php echo htmlspecialchars($row['sex']); ?></td>
        <td><?php echo htmlspecialchars($row['age_at_encounter']); ?></td>
        <td><?php echo htmlspecialchars($row['encounter_date']); ?></td>
        <td><?php echo htmlspecialchars($row['encounter_id']); ?></td>
        <td><?php echo htmlspecialchars($row['billing_codes']); ?></td>
        <td><?php echo htmlspecialchars($row['icd10_codes'] ?? ''); ?></td>
        <td>
            <?php if (!empty($row['closest_med_modifydate'])): ?>
                <?php echo htmlspecialchars($row['closest_med_modifydate']); ?>
            <?php else: ?>
                <span class="highlight-none">None found</span>
            <?php endif; ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p class="no-data">No encounters found matching the specified criteria for the performance period.</p>
<?php endif; ?>

</body>
</html>