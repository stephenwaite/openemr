<?php

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

$fh1 = fopen('/tmp/w11', 'r');
$fh2 = fopen('/tmp/w22', 'w');

while ($line = fgets($fh1)) {

  $garno = substr($line,0,8);

  if ($garno != '        ') {
    $query = "select pid, lname, fname, dob, sex, pubpid from patient_data where pubpid = ?";
    $row = sqlQuery($query, array($garno));
    if ($row) {
      //$out = $row['pid'] . $row['lname'] . "," . $row['fname'] . "," . $row['dob'] . "," . $row['sex'] . "," . $row['pubpid'] . "\n";
      //echo "$out already exists";
      continue;
    } else {
      $out = $garno . "\n";
      fwrite($fh2, $out);
    }
  }
}  