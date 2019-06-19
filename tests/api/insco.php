<?php
require_once(dirname(__FILE__) . "/../../interface/globals.php");

$handle = fopen("/tmp/wsteve", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $ins_code = substr($line, 0, 3);
        echo "ins_code is $ins_code";
        echo "<br>";
        $ins_name = trim(substr($line, 3, 22));
        echo "ins_name is $ins_name";
        echo "<br>";
        $ins_addr = trim(substr($line, 25, 24));
        echo "ins_addr is $ins_addr";
        echo "<br>";
        $ins_city = trim(substr($line, 49, 15));
        echo "ins_city is $ins_city";
        echo "<br>";
        $ins_state = substr($line, 64, 2);
        echo "ins_state is $ins_state";
        echo "<br>";
        $ins_zip = substr($line, 66, 5);
        echo "ins_zip is $ins_zip";
        echo "<br>";
        $ins_zip_four = substr($line, 71, 4);
        echo "ins_zip_four is $ins_zip_four";
        echo "<br>";
        $ins_neic = substr($line, 77, 5);
        echo "ins_neic is $ins_neic";
        echo "<br>";

        $query = "INSERT INTO insurance_companies SET id = ?, name = ?, cms_id = ?, ins_type_code = ?, x12_default_partner_id = ?";
        $res = sqlStatement($query, array($ins_code, $ins_name, $ins_neic, "1", $ins_neic));

        $addr_query = "INSERT INTO addresses SET id = ?, line1 = ?, city = ?, state = ?, zip = ?, plus_four = ?, foreign_id = ?";
        $addr_res = sqlStatement($addr_query, array($ins_code, $ins_addr, $ins_city, $ins_state, $ins_zip, $ins_zip_four, $ins_code));
//        //var_dump($res);
        //$row = sqlFetchArray($res);
        //echo "insurance_companies entry " . var_dump($row) . "<br>";

    }

    fclose($handle);
} else {
// error opening the file.
echo "couldn't open file";
}