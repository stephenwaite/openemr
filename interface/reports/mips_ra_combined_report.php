<?php
/**
 * MIPS Quality Measures 177, 178, 180 - Rheumatoid Arthritis (RA) Combined Report
 * 2025 Specifications
 *
 * Measures included:
 *   177 - RA: Periodic Assessment of Disease Activity      (rule item: act_cdai)
 *   178 - RA: Functional Status Assessment                 (rule item: act_rafunc)
 *   180 - RA: Glucocorticoid Management                    (rule item: act_glucocorticoid)
 *
 * Denominator Criteria (shared across all three measures):
 *   - Age >= 18 years at time of encounter
 *   - Diagnosis of rheumatoid arthritis (RA) using ICD-10-CM codes
 *   - Two or more encounters at least 90 days apart (spanning current and prior period)
 *   - Qualifying CPT/HCPCS encounter codes
 *   - Excludes telehealth encounters
 *   - Performance period: 2025-01-01 to 2025-12-31
 *   - Prior period for 90-day rule: 2024-01-01 to 2024-12-31
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/sql.inc.php");

// ─── Performance period dates ────────────────────────────────────────────────
$performancePeriodStart = '2025-01-01';
$performancePeriodEnd   = '2025-12-31';
$priorPeriodStart       = '2024-01-01';
$priorPeriodEnd         = '2024-12-31';

// ─── Measure definitions ─────────────────────────────────────────────────────
$measures = array(
    '177' => array(
        'number'      => '177',
        'name'        => 'RA: Periodic Assessment of Disease Activity',
        'rule_item'   => 'act_cdai',
    ),
    '178' => array(
        'number'      => '178',
        'name'        => 'RA: Functional Status Assessment',
        'rule_item'   => 'act_rafunc',
    ),
    '180' => array(
        'number'      => '180',
        'name'        => 'RA: Glucocorticoid Management',
        'rule_item'   => 'act_glucocorticoid',
    ),
);

// ─── RA diagnosis codes (ICD-10-CM) ──────────────────────────────────────────
$raDxCodes = array(
    'M05.00','M05.011','M05.012','M05.019','M05.021','M05.022','M05.029','M05.031','M05.032','M05.039',
    'M05.041','M05.042','M05.049','M05.051','M05.052','M05.059','M05.061','M05.062','M05.069','M05.071',
    'M05.072','M05.079','M05.09','M05.10','M05.111','M05.112','M05.119','M05.121','M05.122','M05.129',
    'M05.131','M05.132','M05.139','M05.141','M05.142','M05.149','M05.151','M05.152','M05.159','M05.161',
    'M05.162','M05.169','M05.171','M05.172','M05.179','M05.19','M05.20','M05.211','M05.212','M05.219',
    'M05.221','M05.222','M05.229','M05.231','M05.232','M05.239','M05.241','M05.242','M05.249','M05.251',
    'M05.252','M05.259','M05.261','M05.262','M05.269','M05.271','M05.272','M05.279','M05.29','M05.30',
    'M05.311','M05.312','M05.319','M05.321','M05.322','M05.329','M05.331','M05.332','M05.339','M05.341',
    'M05.342','M05.349','M05.351','M05.352','M05.359','M05.361','M05.362','M05.369','M05.371','M05.372',
    'M05.379','M05.39','M05.40','M05.411','M05.412','M05.419','M05.421','M05.422','M05.429','M05.431',
    'M05.432','M05.439','M05.441','M05.442','M05.449','M05.451','M05.452','M05.459','M05.461','M05.462',
    'M05.469','M05.471','M05.472','M05.479','M05.49','M05.50','M05.511','M05.512','M05.519','M05.521',
    'M05.522','M05.529','M05.531','M05.532','M05.539','M05.541','M05.542','M05.549','M05.551','M05.552',
    'M05.559','M05.561','M05.562','M05.569','M05.571','M05.572','M05.579','M05.59','M05.60','M05.611',
    'M05.612','M05.619','M05.621','M05.622','M05.629','M05.631','M05.632','M05.639','M05.641','M05.642',
    'M05.649','M05.651','M05.652','M05.659','M05.661','M05.662','M05.669','M05.671','M05.672','M05.679',
    'M05.69','M05.7A','M05.70','M05.711','M05.712','M05.719','M05.721','M05.722','M05.729','M05.731',
    'M05.732','M05.739','M05.741','M05.742','M05.749','M05.751','M05.752','M05.759','M05.761','M05.762',
    'M05.769','M05.771','M05.772','M05.779','M05.79','M05.8A','M05.80','M05.811','M05.812','M05.819',
    'M05.821','M05.822','M05.829','M05.831','M05.832','M05.839','M05.841','M05.842','M05.849','M05.851',
    'M05.852','M05.859','M05.861','M05.862','M05.869','M05.871','M05.872','M05.879','M05.89','M05.9',
    'M06.0A','M06.00','M06.011','M06.012','M06.019','M06.021','M06.022','M06.029','M06.031','M06.032',
    'M06.039','M06.041','M06.042','M06.049','M06.051','M06.052','M06.059','M06.061','M06.062','M06.069',
    'M06.071','M06.072','M06.079','M06.08','M06.09','M06.20','M06.211','M06.212','M06.219','M06.221',
    'M06.222','M06.229','M06.231','M06.232','M06.239','M06.241','M06.242','M06.249','M06.251','M06.252',
    'M06.259','M06.261','M06.262','M06.269','M06.271','M06.272','M06.279','M06.28','M06.29','M06.30',
    'M06.311','M06.312','M06.319','M06.321','M06.322','M06.329','M06.331','M06.332','M06.339','M06.341',
    'M06.342','M06.349','M06.351','M06.352','M06.359','M06.361','M06.362','M06.369','M06.371','M06.372',
    'M06.379','M06.38','M06.39','M06.8A','M06.80','M06.811','M06.812','M06.819','M06.821','M06.822',
    'M06.829','M06.831','M06.832','M06.839','M06.841','M06.842','M06.849','M06.851','M06.852','M06.859',
    'M06.861','M06.862','M06.869','M06.871','M06.872','M06.879','M06.88','M06.89','M06.9'
);

// ─── Qualifying encounter CPT/HCPCS codes ────────────────────────────────────
$encounterCodes = array(
    '99202','99203','99204','99205','99212','99213','99214','99215',
    '99341','99342','99344','99345','99347','99348','99349','99350',
    '99424','99426','G0402','G0468'
);

// ─── Telehealth exclusions ────────────────────────────────────────────────────
$telehealthModifiers = array('GQ','GT','95');
$telehealthPOS       = array('02','10');

// ─── Build SQL-safe strings ───────────────────────────────────────────────────
$raDxCodesStr     = "'" . implode("','", $raDxCodes) . "'";
$encounterCodesStr = "'" . implode("','", $encounterCodes) . "'";
$telehealthModStr  = "'" . implode("','", $telehealthModifiers) . "'";

// ─── Main patient query (runs once for all three measures) ───────────────────
$sql = "
SELECT DISTINCT
    p.pid,
    p.pubpid,
    p.lname  AS last_name,
    p.fname  AS first_name,
    p.mname  AS middle_name,
    p.DOB    AS date_of_birth,
    p.sex
FROM
    patient_data p
INNER JOIN form_encounter fe ON p.pid = fe.pid
    AND fe.date BETWEEN '$priorPeriodStart' AND '$performancePeriodEnd'
INNER JOIN billing b ON fe.encounter = b.encounter
    AND fe.pid  = b.pid
    AND b.code IN ($encounterCodesStr)
    AND b.activity = 1
WHERE
    p.deceased_date IS NULL
    AND TIMESTAMPDIFF(YEAR, p.DOB, fe.date) >= 18
    AND EXISTS (
        SELECT 1 FROM billing b_dx
        WHERE b_dx.pid      = p.pid
          AND b_dx.code     IN ($raDxCodesStr)
          AND b_dx.activity = 1
    )
ORDER BY p.lname, p.fname
";

$res = sqlStatement($sql);

// ─── Counters (per measure) ───────────────────────────────────────────────────
$stats = array();
foreach ($measures as $mNum => $m) {
    $stats[$mNum] = array(
        'denominator'       => 0,
        'excluded'          => 0,
        'numerator'         => 0,
    );
}

// ─── Results array – one entry per patient × measure ─────────────────────────
$rows = array();

while ($row = sqlFetchArray($res)) {
    $pid = $row['pid'];
    $dob = $row['date_of_birth'];

    // ── Fetch all qualifying encounters for this patient (both periods) ────
    $encounterQuery = "
    SELECT DISTINCT
        fe.encounter,
        DATE(fe.date) AS encounter_date,
        fe.pos_code,
        (SELECT GROUP_CONCAT(DISTINCT b_dx.code ORDER BY b_dx.code SEPARATOR ', ')
         FROM billing b_dx
         WHERE b_dx.encounter = fe.encounter
           AND b_dx.pid       = fe.pid
           AND b_dx.code     IN ($raDxCodesStr)
           AND b_dx.activity  = 1
        ) AS ra_diagnosis_codes,
        (SELECT GROUP_CONCAT(DISTINCT b_cpt.code ORDER BY b_cpt.code SEPARATOR ', ')
         FROM billing b_cpt
         WHERE b_cpt.encounter = fe.encounter
           AND b_cpt.pid       = fe.pid
           AND b_cpt.code     IN ($encounterCodesStr)
           AND b_cpt.activity  = 1
        ) AS encounter_cpt_codes
    FROM form_encounter fe
    INNER JOIN billing b ON fe.encounter = b.encounter
        AND fe.pid   = b.pid
        AND b.code  IN ($encounterCodesStr)
        AND b.activity = 1
    WHERE
        fe.pid  = ?
        AND fe.date BETWEEN '$priorPeriodStart' AND '$performancePeriodEnd'
        AND TIMESTAMPDIFF(YEAR, ?, fe.date) >= 18
        AND EXISTS (
            SELECT 1 FROM billing b_dx
            WHERE b_dx.encounter = fe.encounter
              AND b_dx.pid       = fe.pid
              AND b_dx.code     IN ($raDxCodesStr)
              AND b_dx.activity  = 1
        )
    ORDER BY fe.date
    ";

    $encRes = sqlStatement($encounterQuery, array($pid, $dob));

    $encounters        = array();   // all qualifying (non-telehealth) encounters
    $perfEncounters    = array();   // 2025 encounters only
    $allEncDates       = array();   // date strings for display (2024–2025)
    $allRaCodes        = array();
    $allCptCodes2025   = array();

    while ($encRow = sqlFetchArray($encRes)) {
        // Check telehealth via modifier
        $hasTelehealth = false;
        $modRes = sqlStatement(
            "SELECT modifier FROM billing WHERE encounter = ? AND modifier IN ($telehealthModStr)",
            array($encRow['encounter'])
        );
        if (sqlNumRows($modRes) > 0) {
            $hasTelehealth = true;
        }
        // Check place of service
        if (!$hasTelehealth && in_array($encRow['pos_code'], $telehealthPOS)) {
            $hasTelehealth = true;
        }
        if ($hasTelehealth) {
            continue;
        }

        $encounters[] = $encRow;
        $allEncDates[] = $encRow['encounter_date'];
        if ($encRow['ra_diagnosis_codes']) {
            $allRaCodes[] = $encRow['ra_diagnosis_codes'];
        }

        // 2025 performance period encounter
        if ($encRow['encounter_date'] >= $performancePeriodStart &&
            $encRow['encounter_date'] <= $performancePeriodEnd) {
            $perfEncounters[] = $encRow;
            if ($encRow['encounter_cpt_codes']) {
                $allCptCodes2025[] = $encRow['encounter_cpt_codes'];
            }
        }
    }

    if (count($encounters) === 0) {
        continue; // No qualifying encounters after telehealth exclusion
    }

    // ── Check 90-day separation ───────────────────────────────────────────
    $has90DaySeparation = false;
    for ($i = 0; $i < count($encounters) - 1 && !$has90DaySeparation; $i++) {
        for ($j = $i + 1; $j < count($encounters); $j++) {
            $diff = abs(strtotime($encounters[$j]['encounter_date']) - strtotime($encounters[$i]['encounter_date'])) / 86400;
            if ($diff >= 90) {
                $has90DaySeparation = true;
                break;
            }
        }
    }

    // ── Determine eligibility ─────────────────────────────────────────────
    $eligibleEncounter = 'EXCLUDED';
    $exclusionReason   = '';
    if (!$has90DaySeparation) {
        $exclusionReason = 'Does not meet 90-day separation requirement';
    } elseif (count($perfEncounters) === 0) {
        $exclusionReason = 'No encounter in 2025 performance period';
    } else {
        $eligibleEncounter = 'INCLUDED';
    }

    // ── Shared patient fields ─────────────────────────────────────────────
    $ageAtEncounter = '';
    if (!empty($perfEncounters)) {
        // Use the first 2025 encounter date for age calculation
        $ageAtEncounter = (int)((strtotime($perfEncounters[0]['encounter_date']) - strtotime($dob)) / (365.25 * 86400));
    } elseif (!empty($encounters)) {
        $ageAtEncounter = (int)((strtotime($encounters[0]['encounter_date']) - strtotime($dob)) / (365.25 * 86400));
    }

    $encounterDatesStr  = implode('; ', array_unique($allEncDates));
    $raCodesStr         = implode('; ', array_unique($allRaCodes));
    $cptCodes2025Str    = implode('; ', array_unique($allCptCodes2025));

    // ── For each measure: query rule_patient_data and build output row ────
    foreach ($measures as $mNum => $m) {
        $ruleItem = $m['rule_item'];

        // Query assessments for 2025 performance period only (numerator)
        $asmtQuery2025 = "
        SELECT DATE(date) AS assessment_date, result
        FROM rule_patient_data
        WHERE pid  = ?
          AND item = ?
          AND date BETWEEN '$performancePeriodStart' AND '$performancePeriodEnd'
        ORDER BY date
        ";
        $asmtRes2025 = sqlStatement($asmtQuery2025, array($pid, $ruleItem));
        $asmtDetails2025 = array();
        while ($aRow = sqlFetchArray($asmtRes2025)) {
            $asmtDetails2025[] = $aRow['assessment_date'] . ' (Result: ' . ($aRow['result'] ?: 'N/A') . ')';
        }

        // Also fetch 2024 assessments for context
        $asmtQuery2024 = "
        SELECT DATE(date) AS assessment_date, result
        FROM rule_patient_data
        WHERE pid  = ?
          AND item = ?
          AND date BETWEEN '$priorPeriodStart' AND '$priorPeriodEnd'
        ORDER BY date
        ";
        $asmtRes2024 = sqlStatement($asmtQuery2024, array($pid, $ruleItem));
        $asmtDetails2024 = array();
        while ($aRow = sqlFetchArray($asmtRes2024)) {
            $asmtDetails2024[] = $aRow['assessment_date'] . ' (Result: ' . ($aRow['result'] ?: 'N/A') . ')';
        }

        $rulePatientData2025 = implode('; ', $asmtDetails2025); // 2025 rule_patient_data entries
        $rulePatientData2024 = implode('; ', $asmtDetails2024); // 2024 for reference

        // Performance met = INCLUDED in denominator AND has at least one 2025 assessment
        $performanceMet = 'N/A';
        $numerator      = 'N/A';

        if ($eligibleEncounter === 'INCLUDED') {
            $stats[$mNum]['denominator']++;
            if (count($asmtDetails2025) > 0) {
                $performanceMet = 'YES';
                $numerator      = '1';
                $stats[$mNum]['numerator']++;
            } else {
                $performanceMet = 'NO';
                $numerator      = '0';
            }
        } else {
            $stats[$mNum]['excluded']++;
        }

        $rows[] = array(
            'pid'                   => $pid,
            'last_name'             => $row['last_name'],
            'first_name'            => $row['first_name'],
            'dob'                   => $dob,
            'age_at_encounter'      => $ageAtEncounter,
            'sex'                   => $row['sex'],
            'encounter_dates'       => $encounterDatesStr,
            'eligible_encounter'    => $eligibleEncounter . ($exclusionReason ? ' (' . $exclusionReason . ')' : ''),
            'eligible_cpt'          => $cptCodes2025Str,
            'ra_icd10'              => $raCodesStr,
            'measure_number'        => $m['number'],
            'rule_item_2025'        => $ruleItem,
            'rule_assessment_2025'  => $rulePatientData2025 ?: 'None',
            'rule_assessment_2024'  => $rulePatientData2024 ?: 'None',
            'performance_met'       => $performanceMet,
            'numerator'             => $numerator,
        );
    } // end foreach measure
} // end while patient loop

// ─── CSV Export ───────────────────────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="MIPS_RA_Combined_177_178_180_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');

    fputcsv($out, array(
        'Patient ID (pid)',
        'Last Name',
        'First Name',
        'DOB',
        'Age at Encounter',
        'Patient Sex',
        'Encounter Dates (2024-2025)',
        '2025 Eligible Encounter',
        '2025 Eligible Encounter CPT',
        'RA ICD-10 Diagnosis Code',
        'Measure Number',
        '2025 Rule_patient_data',
        '2025 Rule Patient Assessment',
        '2024 Rule Patient Assessment (Reference)',
        'Measure Performance Met',
        'Measure Numerator',
    ));

    foreach ($rows as $r) {
        fputcsv($out, array(
            $r['pid'],
            $r['last_name'],
            $r['first_name'],
            $r['dob'],
            $r['age_at_encounter'],
            $r['sex'],
            $r['encounter_dates'],
            $r['eligible_encounter'],
            $r['eligible_cpt'],
            $r['ra_icd10'],
            $r['measure_number'],
            $r['rule_item_2025'],
            $r['rule_assessment_2025'],
            $r['rule_assessment_2024'],
            $r['performance_met'],
            $r['numerator'],
        ));
    }

    fclose($out);
    exit;
}

// ─── HTML Report ──────────────────────────────────────────────────────────────
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>MIPS RA Combined Report – Measures 177, 178, 180</title>
    <style>
        * { box-sizing: border-box; }
        body { font-family: Arial, sans-serif; margin: 20px; font-size: 13px; color: #222; }
        h1 { font-size: 18px; color: #1a237e; margin-bottom: 4px; }
        h2 { font-size: 14px; color: #333; margin: 0 0 6px; }

        .report-header {
            background: #e8eaf6; padding: 14px 18px; border-radius: 6px;
            margin-bottom: 16px; border-left: 5px solid #3949ab;
        }
        .report-header p { margin: 3px 0; }

        .export-btn {
            display: inline-block; padding: 8px 18px; background: #3949ab;
            color: #fff; text-decoration: none; border-radius: 4px; margin-top: 8px;
            font-size: 13px;
        }
        .export-btn:hover { background: #283593; }

        /* Summary cards */
        .summary-grid {
            display: flex; gap: 14px; flex-wrap: wrap; margin-bottom: 18px;
        }
        .summary-card {
            background: #f5f5f5; border: 1px solid #ddd; border-radius: 6px;
            padding: 12px 18px; min-width: 180px; flex: 1;
        }
        .summary-card .measure-title {
            font-weight: bold; color: #1a237e; margin-bottom: 6px; font-size: 13px;
        }
        .summary-card .stat { font-size: 13px; margin: 2px 0; }
        .summary-card .rate { font-size: 15px; font-weight: bold; color: #2e7d32; margin-top: 6px; }

        /* Main table */
        table {
            border-collapse: collapse; width: 100%; margin-top: 10px;
            font-size: 11px;
        }
        th {
            background: #3949ab; color: #fff; padding: 8px 6px;
            text-align: left; white-space: nowrap; position: sticky; top: 0;
        }
        td { border: 1px solid #ccc; padding: 5px 6px; vertical-align: top; }
        tr:nth-child(even) { background: #f5f5f5; }
        tr:hover { background: #e8eaf6; }

        /* Measure stripe colors */
        tr.m177 td:first-child { border-left: 4px solid #1565c0; }
        tr.m178 td:first-child { border-left: 4px solid #6a1b9a; }
        tr.m180 td:first-child { border-left: 4px solid #00695c; }

        .badge {
            display: inline-block; padding: 2px 7px; border-radius: 10px;
            font-size: 10px; font-weight: bold; white-space: nowrap;
        }
        .badge-included { background: #c8e6c9; color: #2e7d32; }
        .badge-excluded { background: #ffcdd2; color: #c62828; }
        .badge-yes      { background: #c8e6c9; color: #2e7d32; }
        .badge-no       { background: #ffcdd2; color: #c62828; }
        .badge-na       { background: #eeeeee; color: #757575; }

        .m177-tag { background: #bbdefb; color: #0d47a1; }
        .m178-tag { background: #e1bee7; color: #4a148c; }
        .m180-tag { background: #b2dfdb; color: #004d40; }

        .info-box {
            background: #e3f2fd; padding: 14px 18px; margin-top: 20px;
            border-left: 5px solid #1e88e5; border-radius: 4px; font-size: 12px;
        }
        .info-box h3 { margin: 0 0 8px; font-size: 13px; }
        .info-box ul { margin: 6px 0 12px 18px; }
        .info-box li { margin: 3px 0; }
    </style>
</head>
<body>

<div class="report-header">
    <h1>MIPS Rheumatoid Arthritis Combined Report – Measures 177 · 178 · 180</h1>
    <p><strong>Report Date:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
    <p><strong>Performance Period:</strong> <?php echo $performancePeriodStart; ?> to <?php echo $performancePeriodEnd; ?></p>
    <p><strong>Prior Period (90-day rule):</strong> <?php echo $priorPeriodStart; ?> to <?php echo $priorPeriodEnd; ?></p>
    <p><strong>Measures:</strong>
        177 – RA Disease Activity Assessment (act_cdai) &nbsp;|&nbsp;
        178 – RA Functional Status Assessment (act_rafunc) &nbsp;|&nbsp;
        180 – RA Glucocorticoid Management (act_glucocorticoid)
    </p>
    <a href="?export=csv" class="export-btn">⬇ Export to CSV</a>
</div>

<!-- ── Summary Cards ─────────────────────────────────────────────────────── -->
<div class="summary-grid">
    <?php foreach ($measures as $mNum => $m): ?>
    <div class="summary-card">
        <div class="measure-title">Measure <?php echo htmlspecialchars($mNum); ?> – <?php echo htmlspecialchars($m['name']); ?></div>
        <div class="stat">Denominator: <strong><?php echo $stats[$mNum]['denominator']; ?></strong></div>
        <div class="stat">Excluded: <strong><?php echo $stats[$mNum]['excluded']; ?></strong></div>
        <div class="stat">Numerator (Met): <strong><?php echo $stats[$mNum]['numerator']; ?></strong></div>
        <?php if ($stats[$mNum]['denominator'] > 0): ?>
        <div class="rate">
            Performance Rate:
            <?php echo number_format(($stats[$mNum]['numerator'] / $stats[$mNum]['denominator']) * 100, 1); ?>%
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <div class="summary-card">
        <div class="measure-title">All Measures – Unique Patients</div>
        <?php
            // Count unique PIDs
            $uniquePids = array_unique(array_column($rows, 'pid'));
        ?>
        <div class="stat">Unique Patients Evaluated: <strong><?php echo count($uniquePids); ?></strong></div>
        <div class="stat">Total Rows (Patient × Measure): <strong><?php echo count($rows); ?></strong></div>
    </div>
</div>

<!-- ── Data Table ────────────────────────────────────────────────────────── -->
<?php if (count($rows) > 0): ?>
<table>
    <thead>
        <tr>
            <th>Patient ID (pid)</th>
            <th>Last Name</th>
            <th>First Name</th>
            <th>DOB</th>
            <th>Age at Encounter</th>
            <th>Patient Sex</th>
            <th>Encounter Dates (2024–2025)</th>
            <th>2025 Eligible Encounter</th>
            <th>2025 Eligible Encounter CPT</th>
            <th>RA ICD-10 Diagnosis Code</th>
            <th>Measure Number</th>
            <th>2025 Rule_patient_data</th>
            <th>2025 Rule Patient Assessment</th>
            <th>2024 Rule Patient Assessment (Ref)</th>
            <th>Measure Performance Met</th>
            <th>Measure Numerator</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r):
        $mClass = 'm' . $r['measure_number'];
        $mTag   = 'm' . $r['measure_number'] . '-tag';
        $eligStatus = strpos($r['eligible_encounter'], 'INCLUDED') !== false ? 'INCLUDED' : 'EXCLUDED';
    ?>
    <tr class="<?php echo $mClass; ?>">
        <td><?php echo htmlspecialchars($r['pid']); ?></td>
        <td><?php echo htmlspecialchars($r['last_name']); ?></td>
        <td><?php echo htmlspecialchars($r['first_name']); ?></td>
        <td><?php echo htmlspecialchars($r['dob']); ?></td>
        <td><?php echo htmlspecialchars($r['age_at_encounter']); ?></td>
        <td><?php echo htmlspecialchars($r['sex']); ?></td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['encounter_dates']); ?></td>
        <td>
            <span class="badge <?php echo $eligStatus === 'INCLUDED' ? 'badge-included' : 'badge-excluded'; ?>">
                <?php echo htmlspecialchars($r['eligible_encounter']); ?>
            </span>
        </td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['eligible_cpt']); ?></td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['ra_icd10']); ?></td>
        <td>
            <span class="badge <?php echo $mTag; ?>">
                <?php echo htmlspecialchars($r['measure_number']); ?>
            </span>
        </td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['rule_item_2025']); ?></td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['rule_assessment_2025']); ?></td>
        <td style="font-size:10px;"><?php echo htmlspecialchars($r['rule_assessment_2024']); ?></td>
        <td>
            <?php if ($r['performance_met'] === 'YES'): ?>
                <span class="badge badge-yes">YES</span>
            <?php elseif ($r['performance_met'] === 'NO'): ?>
                <span class="badge badge-no">NO</span>
            <?php else: ?>
                <span class="badge badge-na">N/A</span>
            <?php endif; ?>
        </td>
        <td style="text-align:center; font-weight:bold;">
            <?php echo htmlspecialchars($r['numerator']); ?>
        </td>
    </tr>
    <?php endforeach; ?>
    </tbody>
</table>
<?php else: ?>
    <p>No patients found meeting the criteria for the specified performance period.</p>
<?php endif; ?>

<div class="info-box">
    <h3>Report Logic</h3>
    <ul>
        <li>The patient query runs <strong>once</strong>; each patient appears <strong>three times</strong> — once per measure.</li>
        <li>Denominator criteria are identical across all three measures (2 encounters ≥ 90 days apart, age ≥ 18, non-telehealth, RA ICD-10, qualifying CPT).</li>
        <li><strong>2025 Eligible Encounter</strong>: INCLUDED = meets all denominator criteria; EXCLUDED = reason shown.</li>
        <li><strong>2025 Rule_patient_data</strong>: The <code>item</code> value queried in <code>rule_patient_data</code> (act_cdai / act_rafunc / act_glucocorticoid).</li>
        <li><strong>2025 Rule Patient Assessment</strong>: Entries found in <code>rule_patient_data</code> within the 2025 performance period.</li>
        <li><strong>Measure Performance Met</strong>: YES if patient is INCLUDED in denominator <em>and</em> has at least one 2025 assessment on record.</li>
        <li><strong>Measure Numerator</strong>: 1 = performance met; 0 = not met; N/A = patient excluded from denominator.</li>
        <li>2024 assessments are shown for reference only and do <em>not</em> affect the 2025 numerator.</li>
    </ul>
    <h3>Measure → Rule Item Mapping</h3>
    <ul>
        <li><strong>177</strong> – RA Disease Activity Assessment → <code>act_cdai</code></li>
        <li><strong>178</strong> – RA Functional Status Assessment → <code>act_rafunc</code></li>
        <li><strong>180</strong> – RA Glucocorticoid Management → <code>act_glucocorticoid</code></li>
    </ul>
</div>

</body>
</html>
