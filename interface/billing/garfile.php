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

use OpenEMR\Services\PatientService;

$pat = new PatientService();

sqlStatement("TRUNCATE insurance_data");

// w1 is unload of garfile
$handle1 = fopen("/tmp/w1", "r");

// create an array of garnos
$garfile = [];
if ($handle1) {
    while (($line = fgets($handle1)) !== false) {
        $key = substr($line, 0, 8);
        $garfile[$key] = $line;
    }    
}

fclose($handle1);

//var_dump($garfile);
//exit;
// w11 is file of garnos with charges from last 3 years

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

fclose($han);

$handle2 = fopen("/tmp/w11", "r");

if ($handle2) {
    while (($line = fgets($handle2)) !== false) {
        $garno = substr($line, 0, 8);
        $ref_name = substr($line, 8, 24);
        $ref_npi  = substr($line, 32, 10);

        if (trim($garno) != '') {
            $row = sqlQuery("select pid from patient_data where pubpid = ?", $garno);            
        } else {
            if (trim($ref_name) != '') {
                $ref_provID = getRefUserID();
                updateRefProvID(); 
            }
            continue;
        }

        if (trim($garno) != '') {
            $hold_garno = $garno;
        }    

        if (!$row['pid']) {
            echo "No match for $garno! this is a new person \n";
            $newPersonFlag = 1;
        } else {
            $newPersonFlag = 0;
            // person is in the db, who is it?
            echo "found $garno! should update this person \n";
            $pid = $row['pid'];
            if (trim($ref_name) != '') {
                $ref_provID = getRefUserID();
                echo "ref name is $ref_name prov id is $ref_provID \n";
            }
        }

        $data = $garfile[$garno];
        $gardeets = [];
        $gardeets['title'] = '';
        $gardeets['country_code'] = '';
        $gardeets['phone_contact'] = '';
        $gardeets['ref_providerID'] = $ref_provID;
        $gardeets['race'] = '';
        $gardeets['ethnicity'] = '';
        $gardeets['pubpid'] = $garno;
        var_dump($gardeets);

        //echo "pid is $pid </br>";
        //echo "garno is $garno </br>";
        $gar_name = substr($garfile[$garno], 8, 24);
        $pieces = explode(';', $gar_name);
        $garno = str_replace("'", "\'",$garno);

        $gardeets['fname'] = $pieces[1];
        $gardeets['lname'] = str_replace("'", "\'", $pieces[0]);
        $gardeets['mname'] = $pieces[2] ?? '';
        // move gar street to emr street
        $gar_addr = trim(substr($data, 32, 22));
        $gar_suite = trim(substr($data, 54, 22));
        $emr_street = mysqli_real_escape_string($GLOBALS['dbh'], $gar_addr . " " . $gar_suite);
        //sleep(1);
        $gardeets['street'] = $emr_street;
        $gar_city = trim(substr($data, 76, 18));
        $gardeets['city'] = $gar_city;
        $gar_state = substr($data, 94, 2);
        $gardeets['state'] = $gar_state;
        $gar_zip = trim(substr($data, 96, 9));
        $gardeets['postal_code'] = $gar_zip;
        //$gar_collt = substr($data, 105, 1);
        $gar_phone = substr($data, 106, 10);
        $gardeets['phone_cell'] = $gar_phone;

        $gar_sex = substr($data, 116, 1);

        $emr_sex = "Female";
        if ($gar_sex == "M") {
            $emr_sex = "Male";
        }

        $gardeets['sex'] = $emr_sex;

        $gar_relate = substr($data, 117, 1);
        $gar_mstat = substr($data, 118, 1);
        $gar_dob = substr($data, 119, 8);
        $gardeets['dob'] = $gar_dob;

        if (!$newPersonFlag) {

            echo "about to use patient service to update this person since flag is  $newPersonFlag \n";
            //var_dump($gardeets);
            $pat->update($pid, $gardeets);
        } else {
            echo "about to use patient service to insert new pt \n";
            var_dump($gardeets);
            $freshpid = $pat->insert($gardeets);
            var_dump($freshpid);
            if ($freshpid) {
                $pid = $freshpid;
            }
        }  
        
        $gar_dunning = substr($data, 127, 1);
        $gar_acctstat = substr($data, 128, 1);
        $gar_pr_mplr = substr($data, 129, 4);
        $gar_prins = substr($data, 133, 3);
        $gar_pr_assign = substr($data, 136, 1);
        $gar_pr_office = substr($data, 137, 4);
        $gar_pr_group = substr($data, 141, 10);
        $gar_pripol = substr($data, 151, 16);
        $gar_prname = substr($data, 167, 24);
        $gar_pr_name_parts = explode(";", $gar_prname);
        // for emr distinct fields
        $gar_prname_lname = mysqli_real_escape_string($GLOBALS['dbh'], $gar_pr_name_parts[0]);
        $gar_prname_fname = $gar_pr_name_parts[1];
        $gar_prname_mname = $gar_pr_name_parts[2];

        $gar_pr_relate = substr($data, 191, 1);
        if ($gar_relate !== $gar_pr_relate) {
            $emr_p_dob = "";
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
        $gar_se_mplr = substr($data, 192, 4);
        $gar_seins = substr($data, 196, 3);

        if ($gar_seins == '062') {
            if ($gar_pr_group) {
                $gap_key = substr($gar_pr_group, 0, 7);
                $gap_ins = ltrim($gar_pr_group, '0');
                $gap_ins = trim($gap_ins);

                if (strlen($gap_ins) <= 3) {
                    $gap_ins = str_pad($gap_ins, 3, "0", STR_PAD_LEFT);
                    $gap_ins = "88" . $gap_ins;
                }

                $test = getInsuranceProvider($gap_ins);
                if (!$test) {
                    $gap_name = $gap[$gap_key]['name'];
                    $gap_street = $gap[$gap_key]['street'];
                    $gap_city = $gap[$gap_key]['city'];;
                    $gap_state = $gap[$gap_key]['state'];;
                    $gap_zip = $gap[$gap_key]['zip'];;
                    $gap_plus_four = $gap[$gap_key]['plus_four'];
                    $query = "INSERT INTO insurance_companies SET id = ?, `name` = ?, cms_id = ?, ins_type_code = ?,
                                    x12_default_partner_id = ?";
                    $res = sqlStatement($query, array($gap_ins, $gap_name, '99999', "17", "46"));
                    $id = sqlQuery("SELECT MAX(id)+1 AS id FROM addresses");                
                    $query = "INSERT INTO `addresses` SET `id` = ?, `line1` = ?, `city` = ?, `state` = ?, `zip` = ?, `plus_four` = ?, `foreign_id` = ?";
                    $res = sqlStatement($query, array($id['id'], $gap_street, $gap_city, $gap_state, $gap_zip, $gap_plus_four, $gap_ins));
                    $gar_seins = $gap_ins;
                } else {
                    $gar_seins = $gap_ins;
                }
            }
        }

        $gar_se_assign = substr($data, 199, 1);
        $gar_trinsind = substr($data, 200, 1);
        $gar_trins = substr($data, 201, 3);
        $gar_se_group = substr($data, 204, 10);
        $gar_secpol = substr($data, 214, 16);
        $gar_sename = substr($data, 230, 24);
        $gar_se_name_parts = explode(";", $gar_sename);
        // for emr distinct fields
        $gar_se_name_lname = mysqli_real_escape_string($GLOBALS['dbh'], $gar_se_name_parts[0]);
        $gar_se_name_fname = $gar_se_name_parts[1];
        $gar_se_name_mname = $gar_se_name_parts[2];

        $gar_se_relate = substr($data, 254, 1);
        if ($gar_relate !== $gar_se_relate) {
            $emr_s_dob = "";
            if ($gar_se_relate == "2") {
                $emr_s_subscr_sex = "Male";
                if ($gar_sex == "F") {
                    if ($gar_relate == "K") {
                        $emr_s_relate = "spouse";
                    } else if ($gar_relate == "M" || $gar_relate == "4") {
                        $emr_s_relate = "child";
                    }
                }
            } else if ($gar_se_relate == "K") {
                if ($gar_sex == "M") {
                    $emr_s_relate = "spouse";
                    $emr_s_subscr_sex = "Female";
                }
            } else {
                $emr_s_relate = "other";
                $emr_s_subscr_sex = "Male";
                if ($gar_se_relate == "Q") {
                    $emr_s_subscr_sex = "Female";
                }
            }
        } else {
            $emr_s_dob = $gar_dob;
            $emr_s_relate = "self";
            $emr_s_subscr_sex = "Male";
            if ($gar_sex == "F") {
                $emr_s_subscr_sex = "Female";
            }

        }

        $gar_copay = NULL;

        $gar_lastbill = substr($data, 262, 8);
        $gar_assignm = substr($data, 270, 1);
        $gar_private = substr($data, 271, 1);
        $gar_billcycle = substr($data, 272, 1);
        $gar_delete = substr($data, 273, 1);
        $gar_filler = substr($data, 274, 3);

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
            //echo $q . "</br></br>";
            sqlQuery($q);
        }
    }
    fclose($handle2);
} else {
    echo "couldn't open unload of garfile";
}

function getRefUserID() {
    global $ref_npi;
    global $ref_name;
    $ref_row = sqlQuery("select id from users where npi = ?", $ref_npi);
    if (!$ref_row['id']) {
        $pieces = explode(';', $ref_name);
        $fname = trim($pieces[1]);
        sqlStatement("insert into users set `authorized` = ?, `fname` = ?, `lname` = ?, `npi` = ?, active = ?", array("1", $fname, $pieces[0], $ref_npi, "1"));
        $ref_row = sqlQuery("select id from users where npi = ?", $ref_npi);
    } 
    return $ref_row['id'];


}

function updateRefProvID() {
    global $hold_garno;
    global $ref_provID;
    echo "going to update $hold_garno with $ref_provID \n";
    sqlStatement("update patient_data set ref_providerID = ? where pubpid = ?", array($ref_provID, $hold_garno));
}