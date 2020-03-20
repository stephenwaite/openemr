<?php
/**
 *  Encounters report.
 *
 *  This report shows past encounters with filtering and sorting,
 *  Added filtering to show encounters not e-signed, encounters e-signed and forms e-signed.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Terry Hill <terry@lilysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2007-2016 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2015 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

$handle_ste = fopen("wste", "w");
//var_dump($handle_ste);



set_time_limit(0);

$form_from_date = '2019-01-01';
//(isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date   = '2020-01-01';
//(isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');

$sqlBindArray = array();

$query = "SELECT " .
    "fe.encounter, fe.date, fe.reason, " .
    "f.formdir, f.form_name, " .
    "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.dob, p.sex, " .
    "TIMESTAMPDIFF(YEAR, p.dob, fe.date) AS age, " .
    "u.lname AS ulname, u.fname AS ufname, u.mname AS umname " .
    "$esign_fields" .
    "FROM ( form_encounter AS fe, forms AS f ) " .
    "LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid " .
    "LEFT JOIN users AS u ON u.id = fe.provider_id " .
    "$esign_joins" .
    "WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient' ";

echo "form from date is $form_from_date ";
echo "form to date is $form_to_date </br>";

if ($form_to_date) {
    $query .= "AND fe.date >= ? AND fe.date <= ? ";
    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');
}


$order_by = " ORDER BY p.pid ASC";
$query = $query . $order_by;
$res = sqlStatement($query, $sqlBindArray);
$pieces[] = "I10.";

    $prior_pt = '';
    //$hmx = array();
    $i = 0;
    while ($row = sqlFetchArray($res)) {
        $i++;
        $pid = $row['pid'];
        $enc = $row['enc'];
        //print_r($hmx);
        $mips_enc_date = substr($row['date'], 0, 10);
        //error_log("mips_enc_date is " . $mips_enc_date . " for pt id " . $row['pubpid']);

        if ($pid == $prior_pt) {
            //echo "we're skipping prior pt $pid";
            continue;
        }

        // measures are for > 18 years old
        if ($row['age'] < 18) {
            //echo "there's a youngin" . $row['pubpid'];
            continue;
        }

        // get i10 pts from lynda's billing
        $dres = sqlStatement("SELECT code from billing as b " .
            "WHERE b.code_type = 'ICD10' AND b.code = 'I10.' " .
            "AND b.pid = ? LIMIT 1", array($pid));

        while ($drow = sqlFetchArray($dres)) {
            $hmx[$pid]['i10'] = true;

        }

        if (!$hmx[$pid]['i10']) {
            echo "$pid is NOT hypertensive </br>";
            continue;
        } else {
            echo "$pid is hypertensive </br>";
            $hmx[$pid] = array();
            $hmx[$pid]['garno'] = $row['pubpid'];
            $hmx[$pid]['dob']   = $row['dob'];
            $hmx[$pid]['sex']   = $row['sex'];
            $hmx[$pid]['dos']   = $mips_enc_date;
            $hmx[$pid]['htn']   = "I10";
            $hmx[$pid]['cpt']   = "99213";
        }

        $cpt_arr = array('99201', '99202', '99203', '99204', '99205',
            '99212', '99213', '99214', '99215', '99341', '99342', '99343', '99344',
            '99345', '99347', '99348', '99349', '99350', 'G0402', 'G0438', 'G0439');

        $bres = sqlStatement("SELECT code, modifier from billing as b " .
            "WHERE b.code_type = 'CPT4' AND b.encounter = ?", array($row['encounter']));
        $brow = sqlFetchArray($bres);

        if (in_array($brow['code'], $cpt_arr)) {
            $hmx[$pid]['cpt'] = $brow['code'];
            continue;
        }



        $bps_pt = '';
        $bpd_pt = '';
        $bps_mips = 140;
        $bpd_mips = 90;

        $q_bp = "SELECT * FROM form_vitals WHERE pid = ?";
        $bp_result = sqlStatement($q_bp, $pid);
        //var_dump(sqlFetchArray($bp_result));
        echo "</br></br>";
        while ($bp_row = sqlFetchArray($bp_result)) {
                //var_dump($bp_row);
            $bps_pt = $bp_row['bps'];
            $bps_test = ($bps_pt < $bps_mips);
            $bpd_pt = $bp_row['bpd'];
            $bpd_test = ($bpd_pt < $bpd_mips);
            $vitals_id = $bp_row['id'];
            echo "vitals id is $vitals_id </br>";

                echo "$pid systolic " . $bps_pt . " diastolic " . $bpd_pt . "</br>";
                if ($bps_pt != '') {
                    if ($bps_test) {
                        //echo "$pid is $bps_pt less than $bps_mips";
                        $hmx[$pid]['236'] = "G8752";
                    } else {
                        $hmx[$pid]['236'] = "G8753";
                    }
                } else {
                    $hmx[$pid]['236'] .= "bp ?";
                }

                if ($bpd_pt != '') {
                    if ($bpd_test) {
                        $hmx[$pid]['236'] .= ",G8754";
                    } else {
                        $hmx[$pid]['236'] .= ",G8755";
                    }
                } else {
                    $hmx[$pid]['236'] .= "bp ?";
                }
            }
        $prior_pt = $pid;
    }


    echo "<pre>";
    echo "pid\tgarno\t\tdob\t\tsex\tdos\t\tcpt\tdx\t" .
        "236\t236";
    $ste_head = "pid,patientid,date of birth,gender,date of service,cpt,icd10," .
        "numerator,numerator";
    fwrite($handle_ste, $ste_head . "\n");

    foreach ($hmx as $key=>$value) {
        //var_dump($ite);
        echo "\n", $key, "\t", $value['garno'], "\t", $value['dob'], "\t", $value['sex'], "\t",
        $value['dos'], "\t", $value['cpt'], "\t",
        $value['htn'], "\t", $value['236'], "\t",
        $value['374'];
        $ste_body = "$key," . $value['garno'] . "," . $value['dob'] . "," . $value['sex'] . ", " .
            $value['dos'] . "," . $value['cpt'] . "," .
            $value['htn'] . "," . $value['236'] . ",";
        fwrite($handle_ste, $ste_body . "\n");
    }

fclose($handle_ste);
