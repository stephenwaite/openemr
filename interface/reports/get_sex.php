<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 3/16/20
 * Time: 11:09 AM
 */

require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");
$cpts = array();
$cpts = ['99201', '99202', '99203', '99204', '99205', '99212', '99213', '99214', '99215', '99241', '99242', '99243', '99244', '99245'];

$handle_dat = fopen("2019_melanoma_1.csv", "r");
$handle_ste = fopen("wste", "w");
$handle_cad = fopen("wcady", "w");

$value = "patientidentifier,DOB,Sex,DOS, Codes, Codes, Codes";
fwrite($handle_ste, $value . "\n");
fwrite($handle_cad, $value . "\n");

$data = array();

while (($line_dat = fgets($handle_dat)) !== false) {
    $data = explode(',', $line_dat);

    //var_dump($data);
    //exit;
    //echo "encounter id is " . $data[3] . "right? </br>";

    $pid = $data[2];
    $enc = $data[3];
    $dx  = trim($data[1]);
    $fill = getPatientData($data[2], "DOB, sex");

    //echo "dob is " . $fill['DOB'] . "right? </br>";
    $dob = $fill['DOB'];
    $sex = $fill['sex'];
    $res = sqlQuery("SELECT `date` from form_encounter where encounter = $enc");
    $dos = substr($res['date'], 0, 10);
    $r   = sqlQuery("SELECT code from billing where code_type = 'CPT4' and encounter = $enc");
    $cpt = $r['code'];
    if (in_array($cpt, $cpts)) {
        echo $pid, ",", $dob, ",", $sex, ",", $dos, ",", $cpt, ",", $dx, ",", "7010F" . "</br>";
        $value = $pid . "," . $dob . "," . $sex . "," . $dos .  "," .  $cpt . "," . $dx . "," . "7010F";
        fwrite($handle_ste, $value . "\n");
    } else {
        echo "we're going to put these in a separate file";
        echo $pid, ",", $dob, ",", $sex, ",", $dos, ",", $cpt, ",", $dx, ",", "5050F", "</br>";
        $value = $pid . "," . $dob . "," . $sex . "," . $dos .  "," .  $cpt . "," . $dx . "," . "5050F";
        fwrite($handle_cad, $value . "\n");

    }


}




