<?php

$ignoreAuth = true;

$_GET['site'] = $argv[1];

$dirname = "/var/www/html/lkuperman.com/openemr/interface/";
require_once($dirname . "/globals.php");
//require_once("$srcdir/forms.inc");
//require_once("$srcdir/patient.inc");
//require_once "$srcdir/options.inc.php";


//use OpenEMR\Billing\BillingUtilities;
//use OpenEMR\Core\Header;

$handle = fopen($dirname . "reports/wstee.csv", "r");
$file = fopen("./d1.csv", "w");

if ($handle) {
    while (($line = fgets($handle)) !== false) {

    //echo $line;

    $statement = "SELECT p.fname, p.lname, p.pid, p.pubpid, p.DOB, p.sex " .
    "FROM patient_data AS p WHERE pubpid = '" . trim($line) . "'";

    $res = sqlquery($statement);

    //var_dump($res);

    //echo $res['fname'];

    $output = "2021-01-15," . strtoupper($res['lname']) . "," . strtoupper($res['fname']) . "," .
      $res['DOB'] . "," . $res['sex'] . "," . $res['pubpid'] . "\n";
    fwrite($file, $output);
  }
}

