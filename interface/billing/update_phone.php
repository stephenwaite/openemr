<?php

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../library/forms.inc");

use OpenEMR\Services\PatientService;

$pat = new PatientService();

// w1 is unload of garfile
$handle1 = fopen("/tmp/w1", "r");

$garfile = [];
if ($handle1) {
    while (($line = fgets($handle1)) !== false) {
        $key = substr($line, 0, 8);
        $garfile[$key] = $line;
    }
}

foreach ($garfile as $key => $line) {
    $garno =  str_replace("'", "\'", substr($line, 0, 8));
    $gar_name = substr($line, 8, 24);
    $pieces = explode(';', $gar_name);
    $fname = $pieces[1];
    $lname = str_replace("'", "\'", $pieces[0]);
    $dob = substr($line, 119, 8);
    $emr_dob = substr($dob, 0, 4) . "-" . substr($dob, 4, 2) . "-" . substr($dob, 6, 2);
    $phone = substr($line, 106, 10);
    if (strlen($phone) != '') {
      $phone = substr($phone, 0, 3) . "-" . substr($phone, 3, 3) . "-" . substr($phone, 6, 4);
    }
    $res = sqlStatement(
        "select * from patient_data where fname = ? AND lname = ? AND DOB = ?",
        [$fname, $lname, $emr_dob]
    );

    if (sqlNumRows($res) > 1) {
        echo "$garno has more than 1 match" . "</br>";
    } elseif (sqlNumRows($res) == 1) {
        $sql_query = '';
        $row_res = sqlFetchArray($res);
        if (trim($row_res['pubpid']) == '') {
            $sql_query =  "update patient_data set pubpid = '$garno', fname = '" .
            trim($fname) . "', lname = '" . trim($lname) . "'";
            if (
                trim($row_res['phone_home']) == '' &&
                trim($row_res['phone_biz']) == '' &&
                trim($row_res['phone_contact']) == '' &&
                trim($row_res['phone_cell']) == ''
            ) {
                if (trim($phone) !== '') {
                    $sql_query .= ", phone_home = '$phone' ";
                } else {
                  //echo " but don't update with sid's empty phone $phone </br>";
                }
            } else {
                //echo " we won't overwrite emr phone </br>";
            }
            $sql_query .= " where pid = " . $row_res['pid'];
          //echo $sql_query . "</br>";
            sqlStatement($sql_query);
        }
    }
}
