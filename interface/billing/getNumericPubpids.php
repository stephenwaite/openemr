<?php

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

$fh = fopen('/tmp/pubpidout','w');

$query = "select DATE_FORMAT(date,'%m/%d/%Y') as datef, lname, fname, dob, sex, pubpid, concat('',pubpid * 1) from patient_data where concat('',pubpid * 1) <> 0";
$res = sqlStatement($query);

while ($row = sqlFetchArray($res)) {
  $out = $row['datef'] . "," . strtoupper($row['lname']) . "," . strtoupper($row['fname']) . "," . $row['dob'] . "," . $row['sex'] . "," . $row['pubpid'] . "\n";
  echo $out;
  fwrite($fh, $out);
}