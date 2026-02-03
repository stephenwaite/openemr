<?php
/**
 * MIPS Quality Measure 176 - Tuberculosis Screening Prior to First Course of 
 * Biologic and/or Immune Response Modifier Therapy
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

$output_file = fopen("measure_176_export.csv", "w");

$csv_header = "pid,patient_id,date_of_birth,gender,date_of_service,cpt,numerator_code,reason\n";
fwrite($output_file, $csv_header);

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
        
        if ($row['age'] < 18) {
            continue;
        }
        
        $cpt_arr = array('98000', '98001', '98002', '98003', '98004', '98005', '98006', '98007',
            '98008', '98009', '98010', '98011', '98012', '98013', '98014', '98015', '98016',
            '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
            '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
            '99424', '99426', 'G0402', 'G0468');
        
        $bres = sqlStatement("SELECT code FROM billing WHERE code_type = 'CPT4' AND encounter = ?", 
            array($row['encounter']));
        $brow = sqlFetchArray($bres);
        
        if (!in_array($brow['code'], $cpt_arr)) {
            continue;
        }
        
        $cpt = $brow['code'];
        
        $tb_res = sqlStatement("SELECT * FROM rule_patient_data " .
            "WHERE item = 'act_tb' AND pid = ? AND date > ?", 
            array($pid, date('Y-m-d', strtotime('-12 months', strtotime($enc_date)))));
        
        $numerator_code = 'M1005';
        $reason = '';
        
        while ($tb_row = sqlFetchArray($tb_res)) {
            $result = $tb_row['result'];
            $reason = $tb_row['reason'];
            if (stripos($result, "19") !== false || 
                stripos($result, "20") !== false) {
                $numerator_code = 'M1003';
                break;
            }
        }
        
        $csv_line = $pid . "," . $row['pubpid'] . "," . $row['dob'] . "," . 
            $row['sex'] . "," . $enc_date . "," . $cpt . "," . $numerator_code . "," . 
            '"' . str_replace('"', '""', $reason) . '"' . "\n";
        fwrite($output_file, $csv_line);
        
        $processed_pids[] = $pid;
    }
}

fclose($output_file);
echo "Measure 176 report generated: measure_176_export.csv\n";
