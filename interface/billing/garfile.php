<?php
/**
 * takes unload of garfile and imports into insurance_data table
 *
 * Created by PhpStorm.
 * User: stee
 * Date: 6/12/19
 * Time: 3:37 PM
 */

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../library/forms.inc");

sqlStatement("TRUNCATE insurance_data");

//header( 'Content-type: text/html; charset=utf-8' );
// w1 is unload of garfile
$handle = fopen("/tmp/w1", "r");
// w2 is unload of gapfile
// load up an array keyed by the medigap crossover code
$han = fopen("/tmp/w2", "r");
$gap = array();

while (($lin = fgets($han)) !== false) {
    // process the line read.
    $idx = substr($lin, 0, 7);
    $gap[$idx]['name'] = substr($lin, 7, 25);
    $gap[$idx]['street'] = substr($lin, 32, 22);
    $gap[$idx]['city'] = substr($lin, 54, 15);
    $gap[$idx]['state'] = substr($lin, 69, 2);
    $gap[$idx]['zip'] = substr($lin, 71, 5);
    $gap[$idx]['plus_four'] = substr($lin, 76, 4);

}

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $garno = substr($line, 0, 8);

        $row = sqlQuery("select pid from patient_data where pubpid = ?", $garno);

        if (!$row['pid']) {
            echo "<i>No match for $garno!</i></br>";
        } else if (!getEncounters($row['pid'])){
            echo "there aren't any encounters for $garno </br>";
        } else {
            // who is it
            $pid = $row['pid'];
            echo "pid is $pid </br>";
            echo "garno is $garno </br>";
            // $gar_name = trim(substr($line, 8, 24));
            // move gar street to emr street
            $gar_addr = trim(substr($line, 32, 22));
            $gar_suite = trim(substr($line, 54, 22));
            $emr_street = mysqli_real_escape_string($GLOBALS['dbh'], $gar_addr . " " . $gar_suite);
            //sleep(1);
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
            $gar_prname_lname = mysqli_real_escape_string($GLOBALS['dbh'], $gar_pr_name_parts[0]);
            $gar_prname_fname = $gar_pr_name_parts[1];
            $gar_prname_mname = $gar_pr_name_parts[2];

            $gar_pr_relate = substr($line, 191, 1);
            if ($gar_relate !== $gar_pr_relate) {
                $emr_p_dob = "1973-02-26";
                if ($gar_pr_relate == "2") {
                    $emr_p_subscr_sex = "Male";
                    if ($gar_sex == "F") {
                        if ($gar_relate == "K") {
                            $emr_p_relate = "spouse";
                        } else if ($gar_relate == "M" || $gar_relate == "4") {
                            $emr_p_relate = "child";
                        }
                    }
                } else if ($gar_pr_relate == "K") {
                    if ($gar_sex == "M") {
                        $emr_p_relate = "spouse";
                        $emr_p_subscr_sex = "Female";
                    }
                } else {
                    $emr_p_relate = "other";
                    $emr_p_subscr_sex = "Male";
                    if ($gar_pr_relate == "Q") {
                        $emr_p_subscr_sex = "Female";
                    }
                }
            } else {
                $emr_p_dob = $gar_dob;
                $emr_p_relate = "self";
                $emr_p_subscr_sex = "Male";
                if ($gar_sex == "F") {
                    $emr_p_subscr_sex = "Female";
                }

            }
            $gar_se_mplr = substr($line, 192, 4);
            $gar_seins = substr($line, 196, 3);
            if ($gar_seins == '062') {
                if ($gar_pr_group) {
                    $gar_seins = ltrim($gar_pr_group, '0');
                    $test = getInsuranceProvider($gar_seins);
                    if (!$test) {
                        $gar_pr_group = trim($gar_pr_group);
                        $gap_name = $gap[$gar_pr_group]['name'];
                        $gap_street = $gap[$gar_pr_group]['street'];
                        $gap_city = $gap[$gar_pr_group]['city'];;
                        $gap_state = $gap[$gar_pr_group]['state'];;
                        $gap_zip = $gap[$gar_pr_group]['zip'];;
                        $gap_plus_four = $gap[$gar_pr_group]['plus_four'];;
                        $query = "INSERT INTO insurance_companies SET id = ?, name = ?, cms_id = ?, ins_type_code = ?,
                                      x12_default_partner_id = ?";
                        $res = sqlStatement($query, array($gar_seins, $gap_name, '', "17", "46"));
                        $id = sqlQuery("SELECT MAX(id)+1 AS id FROM addresses");
                        $query = "INSERT INTO `addresses` SET `id` = ?, `line1` = ?, `city` = ?, `state` = ?, `zip` = ?, `plus_four` = ?, `foreign_id` = ?";
                        $res = sqlStatement($query, array($id['id'], $gap_street, $gap_city, $gap_state, $gap_zip, $gap_plus_four, $gar_seins));
                    } else {
                        echo "should have grabbed an ins maybe </br>";
                        var_dump($test);
                    }
                }
            } else {
                //$test = getInsuranceProvider($gar_seins);
                //var_dump($test);
            }
            $gar_se_assign = substr($line, 199, 1);
            $gar_trinsind = substr($line, 200, 1);
            $gar_trins = substr($line, 201, 3);
            $gar_se_group = substr($line, 204, 10);
            $gar_secpol = substr($line, 214, 16);
            $gar_sename = substr($line, 230, 24);
            $gar_se_name_parts = explode(";", $gar_sename);
            // for emr distinct fields
            $gar_se_name_lname = mysqli_real_escape_string($GLOBALS['dbh'], $gar_se_name_parts[0]);
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
            if ($gar_copay == 0) {
                $gar_copay = NULL;
            }
            $gar_lastbill = substr($line, 262, 8);
            $gar_assignm = substr($line, 270, 1);
            $gar_private = substr($line, 271, 1);
            $gar_billcycle = substr($line, 272, 1);
            $gar_delete = substr($line, 273, 1);
            $gar_filler = substr($line, 274, 3);

            //$pat->update($pid, $patient);
            //echo "replace patient info with this";
            //var_dump($patient);
            
            //$pri_ins = $ins->doesInsuranceTypeHaveEntry($pid, "primary");
            //var_dump($pri_ins);
            // echo "we're going to insert insurance";
            echo "<br><br>";
            for ($i = 1; $i <= 3; $i++) {
                switch($i) {
                    case 1:
                        $type = 'primary';
                        $cms_ins = $gar_prins;
                        $cms_pol = $gar_pripol;
                        if ($cms_ins == '003') {
                            $cms_grp = '';
                        } else {
                            $cms_grp = $gar_pr_group;
                        }
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
                VALUES ('$type', '$cms_ins', '$cms_pol', '$cms_grp',
                  '$gar_prname_lname', '$gar_prname_mname', '$gar_prname_fname', '$sub_rel',
                  '$gar_dob', '$emr_street', '$gar_zip',
                  '$gar_city', '$gar_state', 'USA',
                  '$gar_phone', '$gar_copay', '0000-00-00' , '$pid', '$sub_sex', 'TRUE')";
                } else {
                    break;
                }
                echo $q . "</br></br>";
                sqlQuery($q);
            }
        }        

    }

    fclose($handle);
} else {
    echo "couldn't open unload of garfile";
}