<?php

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

sqlStatement("TRUNCATE insurance_companies");
sqlStatement("TRUNCATE addresses");

// wsteve is unload of insfile
$handle = fopen("/tmp/wsteve", "r");

// set run_flag to 0 to truncate and reload
$run_flag = 0;

if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $ins_code = substr($line, 0, 3);
        //echo "ins_code is $ins_code";
        //echo "<br>";
        $ins_name = trim(substr($line, 3, 22));
        //echo "ins_name is $ins_name";
        //echo "<br>";
        $ins_addr = preg_replace('/[\'".]/', '',trim(substr($line, 25, 24)));
        //echo "ins_addr is $ins_addr";
        //echo "<br>";
        $ins_city = trim(substr($line, 49, 15));
        //echo "ins_city is $ins_city";
        //echo "<br>";
        $ins_state = substr($line, 64, 2);
        //echo "ins_state is $ins_state";
        //echo "<br>";
        $ins_zip = substr($line, 66, 5);
        //echo "ins_zip is $ins_zip";
        //echo "<br>";
        $ins_zip_four = substr($line, 71, 4);
        //echo "ins_zip_four is $ins_zip_four";
        //echo "<br>";
        $ins_neic = substr($line, 77, 5);
        //echo "ins_neic is $ins_neic";
        //echo "<br>";

        // should fix x12_default_partner_id to change and then manually set up medicare, medicaid and bcbsvt
        if ($run_flag < 1) {
            sqlStatement("TRUNCATE x12_partners");
            $q = "INSERT INTO x12_partners SET id = ?, name = ?, id_number = ?, x12_sender_id = ?, x12_receiver_id = ?, processing_format = ?, x12_isa01 = ?, x12_isa02  = ?, x12_isa03 = ?, x12_isa04  = ?, x12_isa05 = ?, x12_isa07 = ?, x12_isa14 = ?, x12_isa15 = ?, x12_gs02  = ?, x12_per06 = ?, x12_gs03  = ?, x12_dtp03 = ?";
            $r = sqlStatement($q, array("6", "Cortex EDI", "14512", "N532", "14512", "standard", "00", '', "00", '', "ZZ", "ZZ", "0", "P", "N532", '', "14512", "A"));
            $r = sqlStatement($q, array("11", "DXC Technology", "822287119", "701100357", "14512", "standard", "00", '', "00", '', "ZZ", "ZZ", "0", "P", "701100357", '', "822287119", "A"));
            $r = sqlStatement($q, array("45", "BCBSVT", "BCBSVT", "7111", "BCBSVT", "standard", "00", '', "00", '', "ZZ", "ZZ", "0", "P", "7111", '', "BCBSVT", "A"));
            $r = sqlStatement($q, array("46", "Change Healthcare", "133052274", "030353360", "133052274", "standard", "00", '', "00", '', "ZZ", "ZZ", "0", "P", "030353360", '', "133052274", "A"));
            $r = sqlStatement($q, array("47", "Palmetto GBA", "00882", "RR6355", "00882", "standard", "00", '', "00", '', "ZZ", "ZZ", "0", "P", "RR6355", '', "00882", "A"));
            //INSERT INTO `x12_partners` VALUES (6,'Cortex EDI','14512','N532','14512','standard','00','          ','00','          ','ZZ','ZZ','0','P','N532','','14512','A'),(11,'DXC Technology','822287119','701100357','14512','standard','00','          ','00','          ','ZZ','ZZ','0','P','701100357','','822287119','A'),(45,'BCBSVT','BCBSVT','7111','BCBSVT','standard','00','          ','00','          ','ZZ','ZZ','0','P','7111','','BCBSVT','A'),(46,'Change Healthcare','133052274','030353360','133052274','standard','00','          ','00','          ','ZZ','ZZ','0','P','030353360','','133052274','A'),(47,'Palmetto GBA','00882','RR6355','00882','standard','00','          ','00','          ','ZZ','ZZ','0','P','RR6355','','00882','A');


        }

        if (trim($ins_addr) != "" ) {
            $query = "INSERT INTO insurance_companies SET id = ?, name = ?, cms_id = ?, ins_type_code = ?, x12_default_partner_id = ?";
            $res = sqlStatement($query, array($ins_code, $ins_name, $ins_neic, "17", "46"));

            $id = sqlQuery("SELECT MAX(id)+1 AS id FROM addresses");
            if (empty($id['id'])) {
                $id['id'] = 1;
            }
            $addr_query = "INSERT INTO addresses SET id = ?, line1 = ?, city = ?, state = ?, zip = ?, plus_four = ?, foreign_id = ?";
            $addr_res = sqlStatement($addr_query, array($id['id'], $ins_addr, $ins_city, $ins_state, $ins_zip, $ins_zip_four, $ins_code));
        }


        // each acct has it's own parm36
        if (in_array($ins_code, array("002", "006", "074", "214"))) {
            //echo "ins_code is $ins_code";
            $q = "UPDATE insurance_companies SET cms_id = 'BCBSVT', ins_type_code = '6', x12_default_partner_id = '45' where id = $ins_code";
            $r = sqlStatement($q);
        } elseif ($ins_code == "003") {
            $q = "UPDATE insurance_companies SET cms_id = '14512', ins_type_code = '2', x12_default_partner_id = '6' where id = $ins_code";
            $r = sqlStatement($q);
        } elseif ($ins_code == "004") {
            $q = "UPDATE insurance_companies SET cms_id = 'MCDVT', ins_type_code = '3', x12_default_partner_id = '11' where id = $ins_code";
            $r = sqlStatement($q);
        } elseif ($ins_code == "028") {
            $q = "UPDATE insurance_companies SET cms_id = '00882', ins_type_code = '2', x12_default_partner_id = '47' where id = $ins_code";
            $r = sqlStatement($q);
        } else {
            $q = "UPDATE insurance_companies SET ins_type_code = '1', x12_default_partner_id = '46' where id = $ins_code";
            $r = sqlStatement($q);
        }    

        $run_flag++;

    }

    fclose($handle);
} else {
// error opening the file.
    echo "couldn't open file";
}