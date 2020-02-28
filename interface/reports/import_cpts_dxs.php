<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 3/3/19
 * Time: 11:31 AM
 */
require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

set_time_limit(0);
// exit;

if (!empty($_POST)) {
    if (!verifyCsrfToken($_POST["csrf_token_form"])) {
        csrfNotVerified();
    }
}

$cms = array();

$fh = fopen('q5','r');
$line = fgets($fh);

while ($line = fgets($fh)) {
    // <... Do your work with the line ...>
    // echo($line);
   $cms['garno'] = substr($line,0,8);
   $cms['dos'] = substr($line, 9, 8);
   $cms['code'] = substr($line, 18, 5);
   $cms['mod'] = trim(substr($line, 23, 2));
   $dx_tmp = substr($line, 26, 3) . "." . trim(substr($line, 29, 4));
   $cms['dx1'] = $dx_tmp;
   $cms['enc_id'] = substr($line, 58, 5);
   $cms['pt_id'] = substr($line, 64, 5);
   $cms['ins_code'] = substr($line, 70, 3);

   //print_r($cms);
    //if ($cms['ins_code'] == '003') {
        $ins_query = "INSERT INTO insurance_data(type, provider, date, pid) VALUES ('primary', ?, ?, ?)";
        sqlStatement($ins_query, array($cms['ins_code'], $cms['dos'], $cms['pt_id']));
    //}

    $query = "INSERT INTO billing (`date`, code_type, code, pid, provider_id, encounter, modifier) VALUES (NOW(), ?, ?, ?, ?, ?, ?)";

    if ($cms['mod']  !== '') {
        sqlStatement($query, array('CPT4', $cms['code'], $cms['pt_id'], '2', $cms['enc_id'], $cms['mod']));
    } else {
        sqlStatement($query, array('CPT4', $cms['code'], $cms['pt_id'], '2', $cms['enc_id'], ''));
    }

    sqlStatement($query, array('ICD10', $cms['dx1'], $cms['pt_id'], '2', $cms['enc_id'], ''));

    $dx_tmp2 = substr($line, 34, 3) . "." . trim(substr($line, 37, 4));
    if ($dx_tmp2 !== '000.0000') {
        sqlStatement($query, array('ICD10', $dx_tmp2, $cms['pt_id'], '2', $cms['enc_id'], ''));
    } else {
        continue;
    }

    $dx_tmp3 = substr($line, 42, 3) . "." . trim(substr($line, 45, 4));
    if ($dx_tmp3 !== '000.0000') {
        error_log("prob in dx_tmp3 " . $dx_tmp3 . " is pt " . $cms['pt_id'] . " and enc " . $cms['enc_id']);
        sqlStatement($query, array('ICD10', $dx_tmp3, $cms[pt_id], '2', $cms[enc_id], ''));

    } else {
         continue;
    }

    $dx_tmp4 = substr($line, 50, 3) . "." . trim(substr($line, 53, 4));
    if ($dx_tmp4 !== '000.0000') {
        sqlStatement($query, array('ICD10', $dx_tmp4, $cms[pt_id], '2', $cms[enc_id], ''));
    } else {
        continue;
    }


}

fclose($fh);


