<?php
/**
 * MIPS Quality Measure 177 - Rheumatoid Arthritis (RA): Periodic Assessment of Disease Activity
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

$ra_dx_codes = "M05.00, M05.011, M05.012, M05.019, M05.021, M05.022, M05.029, M05.031, M05.032, M05.039, " .
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

$ra_codes_array = explode(", ", $ra_dx_codes);

$output_file = fopen("measure_177_export.csv", "w");

$csv_header = "pid,patient_id,date_of_birth,gender,date_of_service,cpt,icd10,numerator_code,reason\n";
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
        
        $bill_dx = false;
        $icd10_ra = '';
        
        $dres = sqlStatement("SELECT code FROM billing WHERE code_type = 'ICD10' AND pid = ?", array($pid));
        while ($drow = sqlFetchArray($dres)) {
            if (in_array($drow['code'], $ra_codes_array)) {
                $icd10_ra = $drow['code'];
                $bill_dx = true;
                break;
            }
        }
        
        if (!$bill_dx) {
            $ra_res = sqlStatement("SELECT title FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
                "title LIKE '%RHEUMATOID ARTHRITIS%' LIMIT 1", array($pid));
            $ra_row = sqlFetchArray($ra_res);
            if ($ra_row) {
                $icd10_ra = "M06.9";
                $bill_dx = true;
            }
        }
        
        if (!$bill_dx) {
            continue;
        }
        
        $cpt_arr = array('99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215',
            '99341', '99342', '99344', '99345', '99347', '99348', '99349', '99350',
            '99424', '99426', 'G0402', 'G0468');
        
        $bres = sqlStatement("SELECT code FROM billing WHERE code_type = 'CPT4' AND encounter = ?", 
            array($row['encounter']));
        $brow = sqlFetchArray($bres);
        
        if (!in_array($brow['code'], $cpt_arr)) {
            continue;
        }
        
        $cpt = $brow['code'];
        
        $enc_arr = getEncounters($pid, $form_from_date, $form_to_date);
        $enc_count = count($enc_arr);
        
        $cdai_count = 0;
        $reason = '';
        $rres = sqlStatement("SELECT * FROM rule_patient_data WHERE pid = ? AND item = 'act_cdai' " .
            "AND date >= ? AND date <= ?", 
            array($pid, $form_from_date, $form_to_date));
        
        while ($rrow = sqlFetchArray($rres)) {
            $cdai_count++;
            $reason = $rrow['reason'];
        }
        
        $numerator_code = 'M1006';
        if ($cdai_count > 0) {
            $pct = $cdai_count / $enc_count;
            if ($pct >= 0.5) {
                $numerator_code = 'M1007';
            } else {
                $numerator_code = 'M1008';
            }
        }
        
        $csv_line = $pid . "," . $row['pubpid'] . "," . $row['dob'] . "," . 
            $row['sex'] . "," . $enc_date . "," . $cpt . "," . $icd10_ra . "," . 
            $numerator_code . "," . '"' . str_replace('"', '""', $reason) . '"' . "\n";
        fwrite($output_file, $csv_line);
        
        $processed_pids[] = $pid;
    }
}

fclose($output_file);
echo "Measure 177 report generated: measure_177_export.csv\n";
