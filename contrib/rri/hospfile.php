<?php

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

$handle1 = fopen("/tmp/w2", "r");

while (($lin = fgets($handle1)) !== false) {
    // process the line read.
    $hosp_key = substr($lin, 0, 5);
    $h_ins_key = substr($lin, 5, 3);
    $h_ins_name = substr($lin, 8, 18);
    $proc_res = sqlStatement("insert into hospfile set hosp_key =?, h_ins_key =?, h_ins_name=?", array($hosp_key, $h_ins_key, $h_ins_name));
}