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

$ra_den = "M05.00, M05.011, M05.012, M05.019, M05.021," .
    "M05.022, M05.029, M05.031, M05.032, M05.039, M05.041, M05.042, M05.049, M05.051, M05.052," .
    "M05.059, M05.061, M05.062, M05.069, M05.071, M05.072, M05.079, M05.09, M05.111, M05.112," .
    "M05.119, M05.121, M05.122, M05.129, M05.131, M05.132, M05.139, M05.141, M05.142, M05.149," .
    "M05.151, M05.152, M05.159, M05.161, M05.162, M05.169, M05.171, M05.172, M05.179, M05.19, M05.20," .
    "M05.211, M05.212, M05.219, M05.221, M05.222, M05.229, M05.231, M05.232, M05.239, M05.241," .
    "M05.242, M05.249, M05.251, M05.252, M05.259, M05.261, M05.262, M05.269, M05.271, M05.272," .
    "M05.279, M05.29, M05.30, M05.311, M05.312, M05.319, M05.321, M05.322, M05.329, M05.331, M05.332," .
    "M05.339, M05.341, M05.342, M05.349, M05.351, M05.352, M05.359, M05.361, M05.362, M05.369," .
    "M05.371, M05.372, M05.379, M05.39, M05.40, M05.411, M05.412, M05.419, M05.421, M05.422, M05.429," .
    "M05.431, M05.432, M05.439, M05.441, M05.442, M05.449, M05.451, M05.452, M05.459, M05.461," .
    "M05.462, M05.469, M05.471, M05.472, M05.479, M05.49, M05.50, M05.511, M05.512, M05.519, M05.521," .
    "M05.522, M05.529, M05.531, M05.532, M05.539, M05.541, M05.542, M05.549, M05.551, M05.552," .
    "M05.559, M05.561, M05.562, M05.569, M05.571, M05.572, M05.579, M05.59, M05.60, M05.611, M05.612," .
    "M05.619, M05.621, M05.622, M05.629, M05.631, M05.632, M05.639, M05.641, M05.642, M05.649," .
    "M05.651, M05.652, M05.659, M05.661, M05.662, M05.669, M05.671, M05.672, M05.679, M05.69, M05.70," .
    "M05.711, M05.712, M05.719, M05.721, M05.722, M05.729, M05.731, M05.732, M05.739, M05.741," .
    "M05.742, M05.749, M05.751, M05.752, M05.759, M05.761, M05.762, M05.769, M05.771, M05.772," .
    "M05.779, M05.79, M05.80, M05.811, M05.812, M05.819, M05.821, M05.822, M05.829, M05.831, M05.832,".
    "M05.839, M05.841, M05.842, M05.849, M05.851, M05.852, M05.859, M05.861, M05.862, M05.869," .
    "M05.871, M05.872, M05.879, M05.89, M05.9, M06.00, M06.011, M06.012, M06.019, M06.021, M06.022," .
    "M06.029, M06.031, M06.032, M06.039, M06.041, M06.042, M06.049, M06.051, M06.052, M06.059," .
    "M06.061, M06.062, M06.069, M06.071, M06.072, M06.079, M06.08, M06.09, M06.1, M06.30, M06.311," .
    "M06.312, M06.319, M06.321, M06.322, M06.329, M06.331, M06.332, M06.339, M06.341, M06.342," .
    "M06.349, M06.351, M06.352, M06.359, M06.361, M06.362, M06.369, M06.371, M06.372, M06.379, " .
    "M06.38, M06.39, M06.80, M06.811, M06.812, M06.819, M06.821, M06.822, M06.829, M06.831, M06.832," .
    "M06.839, M06.841, M06.842, M06.849, M06.851, M06.852, M06.859, M06.861, M06.862, M06.869," .
    "M06.871, M06.872, M06.879, M06.88, M06.89, M06.9";

$pieces = explode(", ", $ra_den); //array of ra dx

set_time_limit(0);

$form_from_date = '2019-01-01';
    //(isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date   = '2019-12-31';
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

if ($res) {
    $prior_pt = '';
    $hmx = array();
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

        $prior_pt = $pid;

        // measures are for > 18 years old
        if ($row['age'] < 18) {
            //echo "there's a youngin" . $row['pubpid'];
            continue;
        }

        // get ra pts from lynda's billing
        $bill_dx  = false;
        $dres = sqlStatement("SELECT code from billing as b " .
            "WHERE b.code_type = 'ICD10' " .
            "AND b.pid = ?", array($pid));

        while ($drow = sqlFetchArray($dres)) {
            $dx = $drow['code'];
            if (!$bill_dx) {
                if (in_array($dx, $pieces)) {
                    $icd10_ra = $dx;
                    $bill_dx = true;
                    //echo $ra_dx;
                }
            }
        }

        // now get ra pts from emr
        $emr_dx = false;
        if (!$bill_dx) {
            //echo "this pid doesn't have RA per lynda's billing $pid </br>";
            $ra_res = sqlStatement("SELECT title, enddate FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
            "title LIKE 'RHEUMATOID ARTHRITIS' LIMIT 1", array($pid));
            $ra_row = sqlFetchArray($ra_res);
            // M06.9 will be used for the odd balls
            //var_dump($ra_row);
            //echo $ra_row['title'];
            if($ra_row) {
                //var_dump($ra_row);
                $icd10_ra = "M06.9";
                $emr_dx = true;
            }
        }

        if ($bill_dx || $emr_dx) {
            //echo $hmx[$pid]['dx'];
        } else {
            continue;
        }

        $cpt_arr = array('99201', '99202', '99203', '99204', '99205',
            '99212', '99213', '99214', '99215', '99341', '99342', '99343', '99344',
            '99345', '99347', '99348', '99349', '99350', 'G0402');

        $bres = sqlStatement("SELECT code, modifier from billing as b " .
            "WHERE b.code_type = 'CPT4' AND b.encounter = ?", array($row['encounter']));
        $brow = sqlFetchArray($bres);
        if (in_array($brow['code'], $cpt_arr)) {
            $cpt = $brow['code'];
        } else {
            continue;
        };


        $hmx[$pid] = array();
        $hmx[$pid]['garno'] = $row['pubpid'];
        $hmx[$pid]['dob']   = $row['dob'];
        $hmx[$pid]['sex']   = $row['sex'];
        $hmx[$pid]['dos']   = $mips_enc_date;
        $hmx[$pid]['cpt']   = $cpt;

        // 1 mg of prednisone = 1 mg of prednisolone; 5 mg of cortisone; 4 mg of hydrocortisone; 0.8 mg of
        // triamcinolone; 0.8 mg of methylprednisolone; 0.15 mg of dexamethasone; 0.15 mg of betamethasone.
        $glu_res = sqlStatement("SELECT title, enddate, pid FROM lists WHERE pid = ?
            AND `type` = 'medication' AND `title` LIKE '%SONE%' OR `title` LIKE '%LONE%'
            AND (`enddate` = '')", array($pid));

        while ($glu_row = sqlFetchArray($glu_res)) {
            if ($glu_row['pid'] == $pid) {
                //var_dump($glu_row);
                //echo "</br> for $pid and " . $glu_row['pid'] . " medication is " . $glu_row['title'] . "</br>";
                $glu = true;
                continue;
            } else {
                $glu = false;
            }
        }

        $enc_arr = getEncounters($pid, '2019-01-01', '2019-12-31');
        $enc_count = count($enc_arr);
        //echo "enc count for $pid is $enc_count";


        if(!$emr_dx) {
            $hmx[$pid]['dx'] = $icd10_ra;
        } else {
            $hmx[$pid]['dx'] = "M06.9";
        }
        // except dexa is for 65 to 85 yo women
        $dexa = '';
        if ($row['age'] >= 65 && $row['age'] <= 85 && $row['sex'] == 'Female') {
            $dexa = true;
            $hmx[$pid]['dexa'] = "dexa";
        } else {
            $dexa = false;
        }
        // Fetch all other forms for this encounter.
        $encnames = '';
        $encarr = getFormByEncounter(
            $pid,
            $row['encounter'],
            "formdir, user, form_name, form_id"
        );

        $vitals_id = '';
            if ($encarr != '') {
                foreach ($encarr as $enc) {
                    if ($enc['formdir'] == 'newpatient') {
                        continue;
                    }

                    if ($enc['formdir'] == 'vitals') {
                        $vitals_id = $enc['form_id'];
                        // error_log("vitals form id is " . $vitals_id . " for pt " . $pid);
                    }
                }
            }

            $cntr_177 = 0;
            $cntr_179 = 0;
            $hmx[$pid]['39']  = ''; // dexa
            $hmx[$pid]['176'] = ''; // tb
            $hmx[$pid]['177'] = '';
            $hmx[$pid]['178'] = '1170F,8P';
            $hmx[$pid]['179'] = '3475F,8P';
            $hmx[$pid]['180'] = ''; // glucocorticoid management
            $hmx[$pid]['374'] = 'G9968,G9970';
            $flag_179 = 0;
            $flag_374 = 0;

            if ($glu) {
                //echo "$pid has glucocorticoid";
                $hmx[$pid]['180'] = '4194,8P'; // glucocorticoid
            } else {
                //echo "$pid has no glucocorticoid";
                $hmx[$pid]['180'] = '4192F,'; // glucocorticoid
            }

            $rres = sqlStatement("SELECT * from rule_patient_data as rpd WHERE rpd.pid = ? " . $rpd_where .
                "AND rpd.date > '2018-12-31' AND rpd.date < '2020-01-01' ORDER BY item ASC", array($pid));

            while ($rrow = sqlFetchArray($rres)) {
                $item = $rrow['item'];
                $result = $rrow['result'];
                //error_log("rpd date is " . $rrow['date'] . " for pt id " .
                //    $row['pubpid'] . " item is " . $item);
                //if (!substr($rrow['date'], 0, 10) == $mips_enc_date) {
                //    error_log($rrow['pid'] . "has rpd on " . $rrow['date'] . " but not on date of encounter " . $mips_enc_date);
                //    continue;
                //}
                $dexa_res = sqlStatement("SELECT * FROM `rule_patient_data` " .
                    "WHERE `item` = 'act_osteo' and `pid` = ?", array($pid));
                if ($dexa) {
                    //$hmx[$pid]['39'] = 'G8399';
                    while ($dexa_row = sqlFetchArray($dexa_res)) {// quality id 39, nqf 0046
                        //$hmx[$pid]['39'] .= $result;
                        $pos1 = stripos($dexa_row['result'], "19"); // there's been a DXA
                        $pos2 = stripos($dexa_row['result'], "18"); // there's been a DXA
                        $pos3 = stripos($dexa_row['result'], "scheduled"); // there's been a DXA
                        if ($pos1 !== false || $pos2 !== false || $pos3 !== false) {
                        $hmx[$pid]['39'] = 'G8400';
                        continue;
                        } else {
                            $hmx[$pid]['39'] = 'G8399';
                        }
                    }
                }

                // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_tb' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                if ($item == 'act_tb') { // quality id 176
                    //$hmx[$pid]['176'] .= $result;
                    $pos1 = stripos("$result", "19");
                    if ($pos1 !== false) {
                        $hmx[$pid]['176'] = "M1003";
                    } else {
                        //$hmx[$pid]['176'] = $result;
                    }
                }

                // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_cdai' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                if ($item == 'act_cdai') { // quality id 177
                    $cntr_177++;
                    //$flag_177 = true;
                }

                // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_rafunc' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                if ($item == 'act_rafunc') { //quality id 178
                    $hmx[$pid]['178'] = "1170F,";
                }

                // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_disease_prog' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                if ($item == 'act_disease_prog' && !$flag_179) { //quality id 179
                    $cntr_179++;
                    //echo "for $pid cntr_179 is $cntr_179 enc count is $enc_count and result is $result </br>";
                    //$hmx[$pid]['179'] .= $result;
                    $pos1 = stripos("$result", "positive"); // poor prognosis
                    $pos15 = stripos("$result", "failure"); // poor prognosis
                    $pos2 = stripos("$result", "poor"); // poor prognosis
                    $pos25 = stripos("$result", "guarded"); // poor prognosis
                    $pos3 = stripos("$result", "negative"); // good prognosis
                    $pos35 = stripos("$result", "fair"); // good prognosis
                    $pos4 = stripos("$result", "good"); // good prognosis
                    $pos45 = stripos("$result", "excellent"); // good prognosis
                    $pos5 = stripos("$result", "minimal"); // good prognosis
                    $pos55 = stripos("$result", "remitted"); // good prognosis

                    if ($pos1 !== false || $pos15 !== false || $pos2 !== false || $pos25 !== false) {
                        $hmx[$pid]['179'] = '3475F,';
                        $flag_179 = 1;
                    } else if ($pos3 !== false || $pos35 !== false ||
                        $pos4 !== false || $pos45 !== false || $pos5 !== false || $pos55 !== false) {
                        $hmx[$pid]['179'] = '3476F,';
                        $flag_179 = 2;
                    } else {
                        //echo "$pid there's an act_ ". $result;
                        $hmx[$pid]['179'] = $result;
                        $flag_179 = 3;
                    }
                }

                // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_glucocorticoid' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                if ($item == 'act_glucocorticoid') { // quality id 180
                    //$hmx[$pid]['180'] .= $result;
                    echo "here's " . $hmx[$pid]['garno'] . " and $result</br>";
                    $pos1 = stripos("$result", "no"); //
                    $pos2 = stripos("$result", "low-dose"); // < 10 mg qd
                    $pos25= stripos("$result", "less than 10"); // < 10 mg qd
                    $pos3 = stripos("$result", "tapering"); //
                    //$pos4 = stripos("$result", "prednisone");
                    $pos5 = stripos("$result", "off");
                    $pos6 = stripos("$result", "rare");
                    $pos7 = stripos("$result", "chronic"); // improvement or no change in disease activity?

                    if ($pos1 !== false || $pos5 !== false) {
                        $hmx[$pid]['180'] = "4192F,";
                    } else {
                        $hmx[$pid]['180'] = "4193F,";
                    }
                }
                if ($item == 'act_ref_sent_sum') {
                    // echo "for $pid flag_374 is $flag_374 </br>";
                    if (!$flag_374) {
                        $hmx[$pid]['374'] = "G9968,G9969";
                        $flag_374++;
                    }
                }
            }

            if ($cntr_177) {
                $pct_177 = $cntr_177/$enc_count;
                // echo "for $pid cntr_177 is $cntr_177 enc count is $enc_count and pct is $pct_177 </br>";
                if ($pct_177 >= .5) {
                    $hmx[$pid]['177'] = 'M1007';
                } else {
                    $hmx[$pid]['177'] = 'M1008';
                }
            } else {
                $hmx[$pid]['177'] = 'M1006';
            }

            // quality id 236 NQF 0018
            $htn_res = sqlStatement("SELECT title, enddate FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
                "title LIKE '%HYPERTENSION%' LIMIT 1 ", array($pid));
            //error_log("med problem is " . $htn_row['title']);
            $htn_row = sqlFetchArray($htn_res);

            if ($htn_row ) {
                $hmx[$pid]['htn'] = "I10";
                //echo "</br> pid $pid has an entry ";
                //var_dump($htn_row);
                $bps_pt = '';
                $bpd_pt = '';
                $bps_mips = 140;
                $bpd_mips = 90;
                $query_bp = "SELECT bps, bpd FROM form_vitals WHERE id = ?";

                if (empty($vitals_id)) {
                    //echo "looks like pid $pid doesn't have a vitals id $vitals_id for this dos </br>";
                    $q_bp = "SELECT * FROM form_vitals WHERE pid = ? order by date desc";
                    $bp_result = sqlStatement($q_bp, $pid);
                    $bp_row = sqlFetchArray($bp_result);
                    //var_dump($bp_r);
                    $bps_pt = $bp_row['bps'];
                    $bps_test = ($bps_pt < $bps_mips);
                    $bpd_test = ($bpd_pt < $bpd_mips);
                    $bpd_pt = $bp_row['bpd'];
                    $vitals_flag = 1;
                }

                if (!$vitals_flag) {
                    $bp_res = sqlStatement($query_bp, array($vitals_id));
                    $bp_row = sqlFetchArray($bp_res);
                }
                $bps_pt = $bp_row['bps'];
                $bps_test = ($bps_pt < $bps_mips);
                $bpd_test = ($bpd_pt < $bpd_mips);
                //? "true </br>" : "false </br>";
                $bpd_pt = $bp_row['bpd'];

                //echo "$pid systolic " . $bps_pt . " diastolic " . $bpd_pt . "</br>";
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


    }


    echo "<pre>";
    echo "pid\tgarno\t\tdob\t\tsex\tdos\t\tcpt\tdx\t" .
         "39\t176\t177\t178,mod\t179,mod\t180,mod\tI10\t236\t236\t374";
    $ste_head = "pid,patientid,date of birth,gender,date of service,cpt,icd10," .
        "numerator,numerator,numerator,numerator,modifier,numerator,modifier,numerator,modifier,icd10,numerator,numerator,numerator,numerator";
    fwrite($handle_ste, $ste_head . "\n");

    foreach ($hmx as $key=>$value) {
        //var_dump($ite);
        echo "\n", $key, "\t", $value['garno'], "\t", $value['dob'], "\t", $value['sex'], "\t",
            $value['dos'], "\t", $value['cpt'], "\t", $value['dx'], "\t",
            $value['39'], "\t",
            $value['176'], "\t",
            $value['177'], "\t",
            str_replace(',', '', $value['178']), "\t",
            str_replace(',', '', $value['179']), "\t",
            str_replace(',', '', $value['180']), "\t",
            $value['htn'], "\t", $value['236'], "\t",
            $value['374'];
        $ste_body = "$key," . $value['garno'] . "," . $value['dob'] . "," . $value['sex'] . ", " .
            $value['dos'] . "," . $value['cpt'] . "," . $value['dx'] . "," .
            $value['39'] . "," .
            $value['176'] . "," .
            $value['177'] . "," .
            $value['178'] . "," .
            $value['179'] . "," .
            $value['180'] . "," .
            $value['htn'] . "," . $value['236'] . "," .
            $value['374'];
        fwrite($handle_ste, $ste_body . "\n");
    }
}
fclose($handle_ste);
