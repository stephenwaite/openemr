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

$handle = fopen($dirname . "reports/d1out", "r");

if ($handle) {
    while (($line = fgets($handle)) !== false) {
      $pubpid = substr($line, 0, 5);
      $garno = substr($line, 5, 8);

      echo "pubpid is $pubpid, garno is $garno \n";

      $findme   = "'";
      $pos = strpos($garno, $findme);

      if ($pos !== false) {
        echo "The string '$findme' was found in the string '$mystring'";
        echo " and exists at position $pos";
        $garno = str_replace("'", "\'", $garno);
   } 
   

      $statement1 = "SELECT p.fname, p.lname, p.pid, p.pubpid, p.DOB, p.sex " .
        "FROM patient_data AS p WHERE pubpid = '" . $pubpid . "'";

      $res1 = sqlquery($statement1);

      echo $res1['lname'] . ", " . $res1['fname'] . ", " .
      $res1['DOB'] . ", " . $res1['sex'] . ", " . $res1['pubpid'] . "\n";

      $statement2 = "UPDATE patient_data SET pubpid = ? WHERE pubpid = ?";
      $res2 = sqlQuery($statement2, [$garno, $pubpid]);

      $statement3 = "SELECT p.fname, p.lname, p.pid, p.pubpid, p.DOB, p.sex " .
        "FROM patient_data AS p WHERE pubpid = '" . $garno . "'";

      $res3 = sqlquery($statement3);

      echo $res3['lname'] . ", " . $res3['fname'] . ", " .
      $res3['DOB'] . ", " . $res3['sex'] . ", " . $res3['pubpid'] . "\n";

    //var_dump($res);

    //echo $res['fname'];

    
  }
}

