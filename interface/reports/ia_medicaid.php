<?php
/**
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");
require_once("$srcdir/appointments.inc.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

$handle_ste = fopen("wste", "w");
//var_dump($handle_ste);

set_time_limit(0);

$form_from_date = '2019-10-01';
//(isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date   = '2019-12-31';
//(isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');

echo "form from date is $form_from_date ";
echo "form to date is $form_to_date </br>";

$appointments = fetchAppointments($form_from_date, $form_to_date);

foreach ($appointments as $appt) {
    $pid       = $appt['pid'];
    $pt_data   = getPatientData($pid, "DOB, sex");
    $appt_date = $appt['pc_eventDate'];
    //echo "appt date is $appt_date </br>";
    $insarr = getEffectiveInsurances($pid, $appt_date);

    //var_dump($insarr);
    //exit;
    foreach ($insarr as $ins) {

        if ($ins['provider'] == "13") {
            //echo "is ins medicaid for $pid? </br>";
            $hmx[$pid]['DOB']  = $pt_data['DOB'];
            $hmx[$pid]['sex']  = $pt_data['sex'];
            $hmx[$pid]['appt'] = $appt_date;
            $hmx[$pid]['type'] = $ins['type'];
            continue;
        }
    }





}


echo "<pre>";
echo "pid\tdob\tsex\tappt date\ttype\t";
$ste_head = "pid,patientid,date of birth,gender,date of service,cpt,icd10," .
    "numerator,numerator";
//fwrite($handle_ste, $ste_head . "\n");

foreach ($hmx as $key=>$value) {
    //var_dump($ite);
    echo "\n", $key, "\t", $value['DOB'], "\t", $value['sex'], "\t",
    $value['appt'], "\t", $value['type'], "\t",
    $value['htn'], "\t", $value['236'];
    $ste_body = "$key," . $value['DOB'] . "," . $value['sex'] . ", " .
        $value['appt'] . "," . $value['type'];
    fwrite($handle_ste, $ste_body . "\n");
}

//fclose($handle_ste);
