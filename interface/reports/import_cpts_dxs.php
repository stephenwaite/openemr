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

use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Core\Header;

set_time_limit(0);


if (!empty($_POST)) {
    if (!verifyCsrfToken($_POST["csrf_token_form"])) {
        csrfNotVerified();
    }
}

$cms = array();

$fh = fopen('/tmp/q2','r');
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

   //print_r($cms);

    $query = "INSERT INTO billing (`date`, code_type, code, pid, provider_id, encounter, modifier) VALUES (NOW(), ?, ?, ?, ?, ?, ?)";

    if ($cms['mod']  !== '') {
        sqlStatement($query, array('CPT4', $cms['code'], $cms['pt_id'], '2', $cms['enc_id'], $cms['mod']));
    } else {
        sqlStatement($query, array('CPT4', $cms['code'], $cms['pt_id'], '2', $cms['enc_id'], ''));
    }

    sqlStatement($query, array('ICD10', $cms['dx1'], $cms['pt_id'], '2', $cms['enc_id'], ''));

    $dx_tmp2 = substr($line, 34, 3) . "." . trim(substr($line, 37, 4));
    if ($dx_tmp2 !== '000.0000') {
        sqlStatement($query, array('ICD10', $cms['dx_tmp2'], $cms['pt_id'], '2', $cms['enc_id'], ''));
    } else {
        continue;
    }

    $dx_tmp3 = substr($line, 42, 3) . "." . trim(substr($line, 45, 4));
    if ($dx_tmp3 !== '000.0000') {
        sqlStatement($query, array('ICD10', $cms[dx_tmp3], $cms[pt_id], '2', $cms[enc_id]));

    } else {
         continue;
    }

    $dx_tmp4 = substr($line, 50, 3) . "." . trim(substr($line, 53, 4));
    if ($dx_tmp4 !== '000.0000') {
        sqlStatement($query, array('ICD10', $cms[dx_tmp4], $cms[pt_id], '2', $cms[enc_id]));
    } else {
        continue;
    }


}

fclose($fh);


