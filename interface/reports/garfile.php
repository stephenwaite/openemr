<?php
/**
 * takes unload of garfile and imports into insurance_data table
 *
 * Created by PhpStorm.
 * User: stee
 * Date: 6/12/19
 * Time: 3:37 PM
 */

require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");

sqlStatement("TRUNCATE insurance_data");


// w1 is unload of garfile
$handle = fopen("w1", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $gar_no = substr($line, 0, 8);
        echo "gar_no is $gar_no";
        echo "<br>";

        $row = sqlQuery("select pid from patient_data where pubpid = ?", $gar_no);
        if (!$row['pid']) {
            echo "<br><i>No match for $gar_no!</i><br><br>";
        } else {
            // who is it
            echo "<br> pid is " . $row['pid'] . "<br>";
            $pid = $row['pid'];
            // $gar_name = trim(substr($line, 8, 24));
            // move gar street to emr street
            $gar_addr = trim(substr($line, 32, 22));
            $gar_suite = trim(substr($line, 54, 22));
            $emr_street = $gar_addr . " " . $gar_suite;
            $gar_city = trim(substr($line, 76, 18));
            $gar_state = substr($line, 94, 2);
            $gar_zip = trim(substr($line, 96, 9));
            //$gar_collt = substr($line, 105, 1);
            $gar_phone = substr($line, 106, 10);

            $gar_sex = substr($line, 116, 1);
            $emr_sex = "Female";
            if ($gar_sex == "M") {
                $emr_sex = "Male";
            }

            $gar_relate = substr($line, 117, 1);
            $gar_mstat = substr($line, 118, 1);
            $gar_dob = substr($line, 119, 8);
            $gar_dunning = substr($line, 127, 1);
            $gar_acctstat = substr($line, 128, 1);
            $gar_pr_mplr = substr($line, 129, 4);
            $gar_prins = substr($line, 133, 3);
            $gar_pr_assign = substr($line, 136, 1);
            $gar_pr_office = substr($line, 137, 4);
            $gar_pr_group = substr($line, 141, 10);
            $gar_pripol = substr($line, 151, 16);
            $gar_prname = substr($line, 167, 24);
                $gar_pr_name_parts = explode(";", $gar_prname);
                // for emr distinct fields
                $gar_prname_lname = $gar_pr_name_parts[0];
                $gar_prname_fname = $gar_pr_name_parts[1];
                $gar_prname_mname = $gar_pr_name_parts[2];

            $gar_pr_relate = substr($line, 191, 1);
            $emr_p_relate = "self";
            $emr_p_subscr_sex = "Male";
            if ($gar_pr_relate == "2") {
                if ($gar_sex == "F") {
                    $emr_p_relate = "spouse";
                }
            } else if ($gar_pr_relate == "K") {
                if ($gar_sex == "M") {
                   $emr_p_relate = "spouse";
                   $emr_p_subscr_sex = "Female";
                }
            }

            $gar_se_mplr = substr($line, 192, 4);
            $gar_seins = substr($line, 196, 3);
            $gar_se_assign = substr($line, 199, 1);
            $gar_trinsind = substr($line, 200, 1);
            $gar_trins = substr($line, 201, 3);
            $gar_se_group = substr($line, 204, 10);
            $gar_secpol = substr($line, 214, 16);
            $gar_sename = substr($line, 230, 24);
            $gar_se_name_parts = explode(";", $gar_sename);
            // for emr distinct fields
                $gar_se_name_lname = $gar_se_name_parts[0];
                $gar_se_name_fname = $gar_se_name_parts[1];
                $gar_se_name_mname = $gar_se_name_parts[2];

            $gar_se_relate = substr($line, 254, 1);
            $emr_s_relate = "self";
            $emr_s_subscr_sex = "Male";
            if ($gar_pr_relate == "2") {
                if ($gar_sex == "F") {
                    $emr_s_relate = "spouse";
                }
            } else if ($gar_pr_relate == "K") {
                if ($gar_sex == "M") {
                    $emr_s_relate = "spouse";
                    $emr_s_subscr_sex = "Female";
                }
            }
            $gar_copay = substr($line, 255, 7);
            $gar_lastbill = substr($line, 262, 8);
            $gar_assignm = substr($line, 270, 1);
            $gar_private = substr($line, 271, 1);
            $gar_billcycle = substr($line, 272, 1);
            $gar_delete = substr($line, 273, 1);
            $gar_filler = substr($line, 274, 3);

            //$pat->update($pid, $patient);
            /*echo "<br><br>";
            echo "replace patient info with this";
            echo "<br><br>";
            var_dump($patient);
            echo "<br><br>";*/

            //$pri_ins = $ins->doesInsuranceTypeHaveEntry($pid, "primary");
            //var_dump($pri_ins);
            echo "we're going to insert insurance";
            echo "<br><br>";
            for ($i = 1; $i <= 3; $i++) {
                switch($i) {
                    case 1:
                        $type = 'primary';
                        $cms_ins = $gar_prins;
                        $cms_pol = $gar_pripol;
                        $cms_grp = $gar_pr_group;
                        $sub_rel = $emr_p_relate;
                        $sub_sex = $emr_p_subscr_sex;
                        break;
                    case 2:
                        $type = 'secondary';
                        $cms_ins = $gar_seins;
                        $cms_pol = $gar_secpol;
                        $cms_grp = $gar_se_group;
                        $sub_rel = $emr_s_relate;
                        $sub_sex = $emr_s_subscr_sex;
                        break;
                    case 3:
                        $type = 'tertiary';
                        $cms_ins = $gar_trins;
                        $cms_pol = '';
                        $cms_grp = '';
                        $sub_rel = '';
                        $sub_sex = '';
                        break;
                }

                if (!in_array($cms_ins, array("001", "012", "018"))) {
                    $q = "INSERT INTO `insurance_data`(`type`, `provider`, `policy_number`, `group_number`,
                  `subscriber_lname`, `subscriber_mname`, `subscriber_fname`, `subscriber_relationship`,
                  `subscriber_DOB`, `subscriber_street`, `subscriber_postal_code`,
                  `subscriber_city`, `subscriber_state`, `subscriber_country`,
                  `subscriber_phone`, `copay`, `date`, `pid`, `subscriber_sex`, `accept_assignment`)
                VALUES ($type, $cms_ins, $cms_pol, $cms_group,
                  $gar_prname_lname, $gar_prname_mname, $gar_prname_fname, $emr_relate,
                  $gar_dob, $emr_street, $gar_zip,
                  $emr_city, $emr_state, $emr_country,
                  $gar_phone, $gar_copay, '2020-01-01', $pid, $emr_subscr_sex, 'TRUE')";
                } else {
                    break;
                }
                echo $q . "</br></br>";
            }
        }

/*        FD  GARFILE
        *    BLOCK CONTAINS 3 RECORDS
           DATA RECORD IS G-MASTER.
        01  G-MASTER.
        02 G-GARNO PIC X(8).
        02 G-GARNAME PIC X(24).
        02 G-BILLADD PIC X(22).
        02 G-STREET PIC X(22).
        02 G-CITY PIC X(18).
        02 G-STATE PIC X(2).
        02 G-ZIP PIC X(9).
        02 G-COLLT PIC X.
        02 G-PHONE PIC X(10).
        02 G-SEX PIC X.
        02 G-RELATE PIC X.
        02 G-MSTAT PIC X.
        02 G-DOB PIC X(8).
        02 G-DUNNING PIC X.
        02 G-ACCTSTAT PIC X.
        02 G-PR-MPLR PIC X(4).
        02 G-PRINS PIC XXX.
        02 G-PR-ASSIGN PIC X.
        02 G-PR-OFFICE PIC X(4).
        02 G-PR-GROUP PIC X(10).
        02 G-PRIPOL PIC X(16).
        02 G-PRNAME PIC X(24).
        02 G-PR-RELATE PIC X.
        02 G-SE-MPLR PIC X(4).
        02 G-SEINS PIC XXX.
        02 G-SE-ASSIGN PIC X.
        02 G-TRINSIND PIC X.
        02 G-TRINS PIC XXX.
        02 G-SE-GROUP PIC X(10).
        02 G-SECPOL PIC X(16).
        02 G-SENAME PIC X(24).
        02 G-SE-RELATE PIC X.
        02 G-COPAY PIC S9(5)V99.
        02 G-LASTBILL PIC X(8).
        02 G-ASSIGNM PIC X.
        02 G-PRIVATE PIC X.
        02 G-BILLCYCLE PIC X.
        02 G-DELETE PIC X.
        02 G-FILLER PIC XXX.*/


    }

    fclose($handle);
} else {
// error opening the file.
    echo "couldn't open file";
}