<?php
/**
 * MIPS Quality Measure 374 - Closing the Referral Loop: Receipt of Specialist Report
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
$form_to_date   = '2025-10-31';

$output_file = fopen("measure_374_export.csv", "w");

$csv_header = "pid,patient_id,date_of_birth,gender,date_of_service,cpt,referral_code,numerator_code,reason\n";
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
        
        $cpt_arr = array('92002', '92004', '92012', '92014', '99202', '99203', '99204', '99205',
            '99212', '99213', '99214', '99215', '90791', '90792', '90839', '96112', '96116',
            '96136', '96138', '96156', '98000', '98001', '98002', '98003', '98004', '98005',
            '98006', '98007', '98008', '98009', '98010', '98011', '98012', '98013', '98014',
            '98015', '98016', '99381', '99382', '99383', '99384', '99385', '99386', '99387',
            '99391', '99392', '99393', '99394', '99395', '99396', '99397');
        
        $bres = sqlStatement("SELECT code FROM billing WHERE code_type = 'CPT4' AND encounter = ?", 
            array($row['encounter']));
        $brow = sqlFetchArray($bres);
        
        if (!in_array($brow['code'], $cpt_arr)) {
            continue;
        }
        
        $cpt = $brow['code'];
        
        $ref_res = sqlStatement("SELECT * FROM rule_patient_data WHERE pid = ? AND item = 'act_ref_sent_sum' " .
            "AND date >= ? AND date <= ?", 
            array($pid, $form_from_date, $form_to_date));
        
        $referral_code = 'G9968';
        $numerator_code = 'G9970';
        $reason = '';
        
        if ($ref_row = sqlFetchArray($ref_res)) {
            $numerator_code = 'G9969';
            $reason = $ref_row['reason'];
        }
        
        $csv_line = $pid . "," . $row['pubpid'] . "," . $row['dob'] . "," . 
            $row['sex'] . "," . $enc_date . "," . $cpt . "," . $referral_code . "," . 
            $numerator_code . "," . '"' . str_replace('"', '""', $reason) . '"' . "\n";
        fwrite($output_file, $csv_line);
        
        $processed_pids[] = $pid;
    }
}

fclose($output_file);
echo "Measure 374 report generated: measure_374_export.csv\n";
