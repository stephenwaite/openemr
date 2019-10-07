<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 6/21/19
 * Time: 12:16 PM
 */

require_once(dirname(__FILE__) . "/../../interface/globals.php");
//require_once (dirname(__FILE__) . "/../../library/patient.inc")

echo "<b>pt service call:</b><br>";
//$pat = new PatientService();

//tmp/wsteve is unload of charcur
$handle = fopen("/tmp/wsteve", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        //$charcur_key = substr($line, 0, 11);
        $cc_key8 = substr($line, 0, 8);
        if ($cc_key8 == $cc_key8_save && $import_flag) {
            continue;
        }
        $cc_key3 = substr($line, 8, 3);
        //echo "CHARCUR-KEY is $charcur_key ";
        //echo "CC-KEY8 is $cc_key8 ";

        //02 CC-PATID PIC X(8).
        //02 CC-CLAIM PIC X(6).
        //02 CC-SERVICE PIC X.
//02 CC-DIAG PIC X(7).
//02 CC-PROC PIC X(7).
//02 CC-MOD2 PIC XX.
//02 CC-MOD3 PIC XX.
//02 CC-MOD4 PIC XX.
//02 CC-AMOUNT PIC S9(4)V99.
//02 CC-DOCR PIC X(3).
//02 CC-DOCP PIC X(2).
//02 CC-PAYCODE PIC XXX.
//02 CC-STUD PIC X.
//02 CC-WORK PIC XX.
//02 CC-DAT1 PIC X(8).
//02 CC-RESULT PIC X.
//02 CC-ACT PIC X.
//02 CC-SORCREF PIC X.
//02 CC-COLLT PIC X.
//02 CC-AUTH PIC X.
//02 CC-PAPER PIC X.
//02 CC-PLACE PIC X.
//02 CC-EPSDT PIC X.

        //02 CC-DATE-T PIC X(8).
        $cc_date_t = substr($line, 79, 8);
        //echo "CC-DATE-T is $cc_date_t ";
        if ($cc_date_t > 20141001) {
            $import_flag = 1;
            //echo "importing $cc_key8" . "<br>";
            //echo sprintf("%s %03d %08d", $cc_key8, $cc_key3, $cc_date_t) . "<br>";
            $garfile_handle = fopen("/tmp/w1", "r");
            if ($garfile_handle) {
                while (($gar_line = fgets($garfile_handle)) !== false) {
                    // process the line read.
                    $gar_no = substr($gar_line, 0, 8);
                    //echo "in garfile gar_no is $gar_no" . "<br>";
                    if ($gar_no > $cc_key8) {
                        continue;
                    } else if ($gar_no == $cc_key8) {
                        //$sql = "select pid from patient_data where pubpid=?";
                        //$res = sqlStatement($sql, $gar_no);
                        //$pat_array = sqlFetchArray($res);
                        //if (!$pat_array['pid']) {
                        //    echo "<br><i>No match for $gar_no!</i><br><br>";
                        //} else {
                        // who is it
                        //echo "<br> pid is " . $pat_array['pid'] . "<br>";
                        //$pid = $pat_array['pid'];
                        //$pat->setPid("$pid");
                        //$patient = $pat->getOne();
                        //var_dump($patient);
                        $patient = array();

                        $gar_name = trim(substr($gar_line, 8, 24));
                        $patient['name'] = $gar_name;
                        // move gar street to emr street
                        $gar_addr = trim(substr($gar_line, 32, 22));
                        $gar_suite = trim(substr($gar_line, 54, 22));
                        $patient['street'] = $gar_addr . " " . $gar_suite;

                        $gar_city = trim(substr($gar_line, 76, 18));
                        $patient['city'] = $gar_city;

                        $gar_state = substr($gar_line, 94, 2);
                        $patient['state'] = $gar_state;

                        $gar_zip = trim(substr($gar_line, 96, 9));
                        $patient['postal_code'] = $gar_zip;
                        $patient['country_code'] = "US";

                        //$gar_collt = substr($gar_line, 105, 1);
                        $gar_phone = substr($gar_line, 106, 10);
                        $patient['phone_contact'] = $gar_phone;

                        $gar_sex = substr($gar_line, 116, 1);
                        $patient['sex'] = $gar_sex;

                        $gar_relate = substr($gar_line, 117, 1);
                        $gar_mstat = substr($gar_line, 118, 1);
                        $gar_dob = substr($gar_line, 119, 8);
                        $gar_dunning = substr($gar_line, 127, 1);
                        $gar_acctstat = substr($gar_line, 128, 1);
                        $gar_pr_mplr = substr($gar_line, 129, 4);
                        $gar_prins = substr($gar_line, 133, 3);
                        $gar_pr_assign = substr($gar_line, 136, 1);
                        $gar_pr_office = substr($gar_line, 137, 4);
                        $gar_pr_group = substr($gar_line, 141, 10);
                        $gar_pripol = substr($gar_line, 151, 16);
                        $gar_prname = substr($gar_line, 167, 24);
                        $gar_pr_relate = substr($gar_line, 191, 1);
                        $gar_se_mplr = substr($gar_line, 192, 4);
                        $gar_seins = substr($gar_line, 196, 3);
                        $gar_se_assign = substr($gar_line, 199, 1);
                        $gar_trinsind = substr($gar_line, 200, 1);
                        $gar_trins = substr($gar_line, 201, 3);
                        $gar_se_group = substr($gar_line, 204, 10);
                        $gar_secpol = substr($gar_line, 214, 16);
                        $gar_sename = substr($gar_line, 230, 24);
                        $gar_se_relate = substr($gar_line, 254, 1);
                        $gar_inspend = substr($gar_line, 255, 7);
                        $gar_lastbill = substr($gar_line, 262, 8);
                        $gar_assignm = substr($gar_line, 270, 1);
                        $gar_private = substr($gar_line, 271, 1);
                        $gar_billcycle = substr($gar_line, 272, 1);
                        $gar_delete = substr($gar_line, 273, 1);
                        $gar_filler = substr($gar_line, 274, 3);

                        //$pat->update($pid, $patient);
                        //echo "<br><br>";
                        //echo "replace patient info with this";
                        //echo "<br><br>";
                        var_dump($patient);
                        echo "<br><br>";

                        //$pri_ins = $ins->doesInsuranceTypeHaveEntry($pid, "primary");
                        //var_dump($pri_ins);
                        //if ($pri_ins) {
                        //  $insdata = $ins->getOne($pid, "primary");
                        // echo "for $pid we're going to update insurance";
                        //echo "<br><br>";
                        //var_dump($insdata);
                        //$ins->update($pid, "primary", $pri_ins);
                        //} else {
                        //    echo "we're going to insert insurance";
                        //    echo "<br><br>";
                        //};
                    }


//        $gar_name = trim(substr($gar_line, 8, 24));
//        echo "gar_name is $gar_name";
//        echo "<br>";
//        $gar_addr = trim(substr($gar_line, 32, 22));
//        echo "gar_addr is $gar_addr";
//        echo "<br>";
//        $gar_suite = trim(substr($gar_line, 54, 22));
//        echo "gar_suite is $gar_suite";
//        echo "<br>";
//        $gar_city = trim(substr($gar_line, 76, 18));
//        echo "gar_city is $gar_city";
//        echo "<br>";
//        $gar_state = substr($gar_line, 94, 2);
//        echo "gar_state is $gar_state";
//        echo "<br>";
//        $gar_zip = substr($gar_line, 96, 9);
//        echo "gar_zip is $gar_zip";
//        echo "<br>";
//        $gar_collt = substr($gar_line, 105, 1);
//        echo "gar_collt is $gar_collt";
//        echo "<br>";
//        $gar_phone = substr($gar_line, 106, 10);
//        echo "gar_phone is $gar_phone";
//        echo "<br>";
//        $gar_sex = substr($gar_line, 116, 1);
//        echo "gar_sex is $gar_sex";
//        echo "<br>";
//        $gar_relate = substr($gar_line, 117, 1);
//        echo "gar_relate is $gar_relate";
//        echo "<br>";
//        $gar_mstat = substr($gar_line, 118, 1);
//        echo "gar_mstat is $gar_mstat";
//        echo "<br>";
//        $gar_dob = substr($gar_line, 119, 8);
//        echo "gar_dob is $gar_dob";
//        echo "<br>";
//        $gar_dunning = substr($gar_line, 127, 1);
//        echo "gar_dunning is $gar_dunning";
//        echo "<br>";
//        $gar_acctstat = substr($gar_line, 128, 1);
//        echo "gar_acctstat is $gar_acctstat";
//        echo "<br>";
//        $gar_pr_mplr = substr($gar_line, 129, 4);
//        echo "gar_pr_mplr is $gar_pr_mplr";
//        echo "<br>";
//        $gar_prins = substr($gar_line, 133, 3);
//        echo "gar_prins is $gar_prins";
//        echo "<br>";
//        $gar_pr_assign = substr($gar_line, 136, 1);
//        echo "gar_pr_assign is $gar_pr_assign";
//        echo "<br>";
//        $gar_pr_office = substr($gar_line, 137, 4);
//        echo "gar_pr_office is $gar_pr_office";
//        echo "<br>";
//        $gar_pr_group = substr($gar_line, 141, 10);
//        echo "gar_pr_group is $gar_pr_group";
//        echo "<br>";
//        $gar_pripol = substr($gar_line, 151, 16);
//        echo "gar_pripol is $gar_pripol";
//        echo "<br>";
//        $gar_prname = substr($gar_line, 167, 24);
//        echo "gar_prname is $gar_prname";
//        echo "<br>";
//        $gar_pr_relate = substr($gar_line, 191, 1);
//        echo "gar_pr_relate is $gar_pr_relate";
//        echo "<br>";
//        $gar_se_mplr = substr($gar_line, 192, 4);
//        echo "gar_se_mplr is $gar_se_mplr";
//        echo "<br>";
//        $gar_seins = substr($gar_line, 196, 3);
//        echo "gar_seins is $gar_seins";
//        echo "<br>";
//        $gar_se_assign = substr($gar_line, 199, 1);
//        echo "gar_se_assign is $gar_se_assign";
//        echo "<br>";
//        $gar_trinsind = substr($gar_line, 200, 1);
//        echo "gar_trinsind is $gar_trinsind";
//        echo "<br>";
//        $gar_trins = substr($gar_line, 201, 3);
//        echo "gar_trins is $gar_trins";
//        echo "<br>";
//        $gar_se_group = substr($gar_line, 204, 10);
//        echo "gar_se_group is $gar_se_group";
//        echo "<br>";
//        $gar_secpol = substr($gar_line, 214, 16);
//        echo "gar_secpol is $gar_secpol";
//        echo "<br>";
//        $gar_sename = substr($gar_line, 230, 24);
//        echo "gar_sename is $gar_sename";
//        echo "<br>";
//        $gar_se_relate = substr($gar_line, 254, 1);
//        echo "gar_se_relate is $gar_se_relate";
//        echo "<br>";
//        $gar_inspend = substr($gar_line, 255, 7);
//        echo "gar_inspend is $gar_inspend";
//        echo "<br>";
//        $gar_lastbill = substr($gar_line, 262, 8);
//        echo "gar_lastbill is $gar_lastbill";
//        echo "<br>";
//        $gar_assignm = substr($gar_line, 270, 1);
//        echo "gar_lastbill is $gar_lastbill";
//        echo "<br>";
//        $gar_private = substr($gar_line, 271, 1);
//        echo "gar_private is $gar_private";
//        echo "<br>";
//        $gar_billcycle = substr($gar_line, 272, 1);
//        echo "gar_billcycle is $gar_billcycle";
//        echo "<br>";
//        $gar_delete = substr($gar_line, 273, 1);
//        echo "gar_delete is $gar_delete";
//        echo "<br>";
//        $gar_filler = substr($gar_line, 274, 3);
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

                fclose($garfile_handle);
            } else {
// error opening the file.
                echo "couldn't open file";
            }
            $count++;
        } else {
            $import_flag = 0;
        }
        $cc_key8_save = $cc_key8;


//02 CC-DATE-A PIC X(8).
//02 CC-DATE-P PIC X(8).
//02 CC-REC-STAT PIC X.
//02 CC-DX2 PIC X(7).
//02 CC-DX3 PIC X(7).
//02 CC-ACC-TYPE PIC X.
//02 CC-DATE-M PIC X(8).
//02 CC-ASSIGN PIC X.
//02 CC-NEIC-ASSIGN PIC X.
//02 CC-DX4 PIC X(7).
//02 CC-DX5 PIC X(7).
//02 CC-DX6 PIC X(7).
//02 CC-FUTURE PIC X(6).


        // find people who don't have garnos in pubpid
        //
        //$sql = "select pid from patient_data where pubpid=?";
        //$res = sqlStatement($sql, $cc_key8);
//        $pat_array = sqlFetchArray($res);
//        if ($pat_array['pid']) {
//            echo "<br> pid is " . $pat_array['pid'] . "<br>";
//            $pid = $pat_array['pid'];
//            $pat->setPid("$pid");
//            $patient = $pat->getOne();
//            var_dump($patient);
//            echo "<br><br>";
//        } else {
//            echo "<br><i>No match for $gar_no!</i><br><br>";
//        }
    }
    echo "total # of people is $count";
    fclose($handle);
} else {
    echo "couldn't open wsteve, charcur file";
}


//
//        FD  CHARCUR
//*    BLOCK CONTAINS 3 RECORDS
//           DATA RECORD IS CHARCUR01.
//01  CHARCUR01.
//02 CHARCUR-KEY.
//03 CC-KEY8 PIC X(8).
//03 CC-KEY3 PIC XXX.
