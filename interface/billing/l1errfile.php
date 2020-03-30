<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 3/30/20
 * Time: 12:01 PM
 */


$handle_ste = fopen("/tmp/l1errfile.csv", "r");
$handle_sid = fopen("/tmp/wste", "w");

if (!$handle_ste) {
    echo "wassup here?";
}
$ste = array();

//$handle_ste = fopen("wste", "a");
//$sid_match = false;
//$dan['dob'] = array();
echo "here we go </br>";
//for ($i=0, $line_dan = fgets($handle_dan)) !== false, $i++) {
while (($line_dat = fgets($handle_ste)) !== false) {
    $ste[] = explode(',', $line_dat);
}

foreach ($ste as $key => $value) {
    // var_dump($value);
    if ($value[5] == $prior) {
        continue;
    }else {
        $output = $value[1] . "," . $value[2].  "," . $value[3] . "," . $value[4] . "," . $value[5] . "</br>";
        echo $output;
        fwrite($handle_sid, $output);
    }
    $prior = $value[5];
}
