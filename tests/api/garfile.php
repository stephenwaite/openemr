<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 6/12/19
 * Time: 3:37 PM
 */
use OpenEMR\Services\PatientService;
use OpenEMR\Services\InsuranceService;

require_once(dirname(__FILE__) . "/../../interface/globals.php");
//require_once (dirname(__FILE__) . "/../../library/patient.inc")

//echo "<b>pt service call:</b><br>";
$pat = new PatientService();
$ins = new InsuranceService();

// w1 is unload of garfile
$handle = fopen("/tmp/w1", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $gar_no = substr($line, 0, 8);
        echo "gar_no is $gar_no";
        echo "<br>";

        $sql = "select pid from patient_data where pubpid=?";
        $res = sqlStatement($sql, $gar_no);
        $pat_array = sqlFetchArray($res);
        if (!$pat_array['pid']) {
            echo "<br><i>No match for $gar_no!</i><br><br>";
        } else {
            // who is it
            echo "<br> pid is " . $pat_array['pid'] . "<br>";
            $pid = $pat_array['pid'];
            $pat->setPid("$pid");
            $patient = $pat->getOne();
            //var_dump($patient);
            echo "<br><br>";

            $pri_ins = $ins->doesInsuranceTypeHaveEntry($pid, "primary");
            var_dump($pri_ins);
            if ($pri_ins) {
                $insdata = $ins->getOne($pid, "primary");
                echo "for $pid we're going to update insurance";
                echo "<br><br>";
                var_dump($insdata);
                //$ins->update($pid, "primary", $pri_ins);
            } else {
                echo "we're going to insert insurance";
                echo "<br><br>";
            };
        }


//        $gar_name = trim(substr($line, 8, 24));
//        echo "gar_name is $gar_name";
//        echo "<br>";
//        $gar_addr = trim(substr($line, 32, 22));
//        echo "gar_addr is $gar_addr";
//        echo "<br>";
//        $gar_suite = trim(substr($line, 54, 22));
//        echo "gar_suite is $gar_suite";
//        echo "<br>";
//        $gar_city = trim(substr($line, 76, 18));
//        echo "gar_city is $gar_city";
//        echo "<br>";
//        $gar_state = substr($line, 94, 2);
//        echo "gar_state is $gar_state";
//        echo "<br>";
//        $gar_zip = substr($line, 96, 9);
//        echo "gar_zip is $gar_zip";
//        echo "<br>";
//        $gar_collt = substr($line, 105, 1);
//        echo "gar_collt is $gar_collt";
//        echo "<br>";
//        $gar_phone = substr($line, 106, 10);
//        echo "gar_phone is $gar_phone";
//        echo "<br>";
//        $gar_sex = substr($line, 116, 1);
//        echo "gar_sex is $gar_sex";
//        echo "<br>";
//        $gar_relate = substr($line, 117, 1);
//        echo "gar_relate is $gar_relate";
//        echo "<br>";
//        $gar_mstat = substr($line, 118, 1);
//        echo "gar_mstat is $gar_mstat";
//        echo "<br>";
//        $gar_dob = substr($line, 119, 8);
//        echo "gar_dob is $gar_dob";
//        echo "<br>";
//        $gar_dunning = substr($line, 127, 1);
//        echo "gar_dunning is $gar_dunning";
//        echo "<br>";
//        $gar_acctstat = substr($line, 128, 1);
//        echo "gar_acctstat is $gar_acctstat";
//        echo "<br>";
//        $gar_pr_mplr = substr($line, 129, 4);
//        echo "gar_pr_mplr is $gar_pr_mplr";
//        echo "<br>";
//        $gar_prins = substr($line, 133, 3);
//        echo "gar_prins is $gar_prins";
//        echo "<br>";
//        $gar_pr_assign = substr($line, 136, 1);
//        echo "gar_pr_assign is $gar_pr_assign";
//        echo "<br>";
//        $gar_pr_office = substr($line, 137, 4);
//        echo "gar_pr_office is $gar_pr_office";
//        echo "<br>";
//        $gar_pr_group = substr($line, 141, 10);
//        echo "gar_pr_group is $gar_pr_group";
//        echo "<br>";
//        $gar_pripol = substr($line, 151, 16);
//        echo "gar_pripol is $gar_pripol";
//        echo "<br>";
//        $gar_prname = substr($line, 167, 24);
//        echo "gar_prname is $gar_prname";
//        echo "<br>";
//        $gar_pr_relate = substr($line, 191, 1);
//        echo "gar_pr_relate is $gar_pr_relate";
//        echo "<br>";
//        $gar_se_mplr = substr($line, 192, 4);
//        echo "gar_se_mplr is $gar_se_mplr";
//        echo "<br>";
//        $gar_seins = substr($line, 196, 3);
//        echo "gar_seins is $gar_seins";
//        echo "<br>";
//        $gar_se_assign = substr($line, 199, 1);
//        echo "gar_se_assign is $gar_se_assign";
//        echo "<br>";
//        $gar_trinsind = substr($line, 200, 1);
//        echo "gar_trinsind is $gar_trinsind";
//        echo "<br>";
//        $gar_trins = substr($line, 201, 3);
//        echo "gar_trins is $gar_trins";
//        echo "<br>";
//        $gar_se_group = substr($line, 204, 10);
//        echo "gar_se_group is $gar_se_group";
//        echo "<br>";
//        $gar_secpol = substr($line, 214, 16);
//        echo "gar_secpol is $gar_secpol";
//        echo "<br>";
//        $gar_sename = substr($line, 230, 24);
//        echo "gar_sename is $gar_sename";
//        echo "<br>";
//        $gar_se_relate = substr($line, 254, 1);
//        echo "gar_se_relate is $gar_se_relate";
//        echo "<br>";
//        $gar_inspend = substr($line, 255, 7);
//        echo "gar_inspend is $gar_inspend";
//        echo "<br>";
//        $gar_lastbill = substr($line, 262, 8);
//        echo "gar_lastbill is $gar_lastbill";
//        echo "<br>";
//        $gar_assignm = substr($line, 270, 1);
//        echo "gar_lastbill is $gar_lastbill";
//        echo "<br>";
//        $gar_private = substr($line, 271, 1);
//        echo "gar_private is $gar_private";
//        echo "<br>";
//        $gar_billcycle = substr($line, 272, 1);
//        echo "gar_billcycle is $gar_billcycle";
//        echo "<br>";
//        $gar_delete = substr($line, 273, 1);
//        echo "gar_delete is $gar_delete";
//        echo "<br>";
//        $gar_filler = substr($line, 274, 3);
//        echo "gar_filler is $gar_filler";
//        echo "<br>";

//        $query = "INSERT INTO insurance_companies SET id = ?, name = ?, cms_id = ?, ins_type_code = ?, x12_default_partner_id = ?";
//        $res = sqlStatement($query, array($ins_code, $ins_name, $ins_neic, "1", $ins_neic));

//        $addr_query = "INSERT INTO addresses SET id = ?, line1 = ?, city = ?, state = ?, zip = ?, plus_four = ?, foreign_id = ?";
//        $addr_res = sqlStatement($addr_query, array($ins_code, $ins_addr, $ins_city, $ins_state, $ins_zip, $ins_zip_four, $ins_code));
//        //var_dump($res);
        //$row = sqlFetchArray($res);
        //echo "insurance_companies entry " . var_dump($row) . "<br>";

    }

    fclose($handle);
} else {
// error opening the file.
    echo "couldn't open file";
}