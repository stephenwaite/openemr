<?php
require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../library/forms.inc");

//sqlStatement("TRUNCATE insurance_data");

$ignoreAuth = true;

// w4 is unload of procfile
$handle = fopen("/tmp/w4.csv", "r");
while (($lin = fgets($handle)) !== false) {
    $codes = explode(',', $lin);
    // process the line read.

    //var_dump($codes);
    $cdm = $codes[0];
    $cpt = $codes[1];
    $mod = $codes[2];
    $typ = $codes[3];
    $des = $codes[4];
    $fee = $codes[5] / 100;

    if ($fee != 0) {
        //echo $fee;
        sqlStatement("INSERT INTO codes set code = ?, code_type = ?, code_text = ?,
          modifier = ?, fee = ?", array($cpt, "1", $des, $mod, $fee));
    }

    
}
