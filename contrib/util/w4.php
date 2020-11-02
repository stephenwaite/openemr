<?php
require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");
require_once(dirname(__FILE__) . "/../../library/forms.inc");

sqlStatement("TRUNCATE prices");
sqlStatement("TRUNCATE codes");

$ignoreAuth = true;

// w4 is unload of procfile
$handle = fopen("/tmp/w4.csv", "r");
while (($lin = fgets($handle)) !== false) {
    $codes = explode(',', $lin);
    // process the line read.

    var_dump($codes);
    $cdm = $codes[0];
    $cpt = $codes[1];
    $mod = $codes[2];
    $typ = $codes[3];
    $des = $codes[4];
    $fee = $codes[5] / 100;

    if ($fee != 0) {
        //echo $fee;
        $codes_id = sqlInsert("INSERT INTO codes set code = ?, code_type = ?, code_text = ?,
          code_text_short = ?, modifier = ?, fee = ?", array($cpt, "1", $des, $des, $mod, $fee));
        
        sqlStatement("INSERT INTO prices set pr_id = ?, pr_level = ?, pr_price = ?",
          array($codes_id, 'standard', $fee));
    }

}
