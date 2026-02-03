<?php
/**
 * MIPS Quality Measure 039 - Screening for Osteoporosis for Women Aged 65-85 Years
 * 
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

set_time_limit(0);

$form_from_date = '2025-01-01';
$form_to_date   = '2025-12-31';

$csv_data = "pid,patient_id,date_of_birth,gender,date_of_service,cpt,numerator_code,result\n";

$sqlBindArray = array();

$query = "SELECT " .
  "fe.encounter, fe.date, " .
  "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.dob, p.sex, " .
  "TIMESTAMPDIFF(YEAR, p.dob, fe.date) AS age " .
  "FROM form_encounter AS fe " .
  "LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid " .
  "WHERE fe.date >= ? AND fe.date <= ? ";

array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');

$query .= " ORDER BY p.pid ASC";
$res = sqlStatement($query, $sqlBindArray);

if ($res) {
    $processed_pids = array();
    
    while ($row = sqlFetchArray($res)) {
        $pid = $row['pid'];
        $enc_date = substr($row['date'], 0, 10);
        
        if (in_array($pid, $processed_pids)) {
            continue;
        }
        
        if ($row['sex'] != 'Female') {
            continue;
        }
        
        if ($row['age'] < 65 || $row['age'] > 85) {
            continue;
        }
        
        $cpt_arr = array('98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007',
            '98008', '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016',
            '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215');
        
        $bres = sqlStatement("SELECT code FROM billing WHERE code_type = 'CPT4' AND encounter = ?", 
            array($row['encounter']));
        $brow = sqlFetchArray($bres);
        
        if (!in_array($brow['code'], $cpt_arr)) {
            continue;
        }
        
        $cpt = $brow['code'];
        
        $osteo_res = sqlStatement(
    "SELECT result
     FROM rule_patient_data
     WHERE item = 'act_osteo'
       AND pid = ?
     ORDER BY rule_patient_data.date DESC",
    array($pid)
);

        
        $numerator_code = 'G8400';
$result_text = '';

$osteo_row = sqlFetchArray($osteo_res);
if ($osteo_row) {
    $result_text = trim($osteo_row['result']);

    if (
        stripos($result_text, 'done') !== false ||
        stripos($result_text, 'normal') !== false ||
        stripos($result_text, 'scheduled') !== false
    ) {
        $numerator_code = 'G8399';
    }
}

        
       $csv_line =
    $pid . "," .
    $row['pubpid'] . "," .
    $row['dob'] . "," .
    $row['sex'] . "," .
    $enc_date . "," .
    $cpt . "," .
    $numerator_code . "," .
    '"' . str_replace('"', '""', $result_text) . '"' . "\n";

    }
}

header('Content-Type: text/csv');
header('Content-Disposition: attachment; filename="measure_039_export_' . date('Y-m-d') . '.csv"');
header('Pragma: no-cache');
header('Expires: 0');

echo $csv_data;
