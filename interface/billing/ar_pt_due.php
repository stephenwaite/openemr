<?php

require_once("../globals.php");
require_once("$srcdir/patient.inc");

// run this for real!
$dry_run = false;

// grab those encounters that were affected on 3-7-20
$q = "SELECT * FROM `form_encounter` WHERE last_level_billed = last_level_closed and last_stmt_date = '2020-03-07'
      ORDER BY `date` DESC";

$r = sqlStatement($q);

// build a list of those that need fixing
$cntr = 0;
while ($f = sqlFetchArray($r)) {
    // get the important fields from the form_encounter table
    $eid = $f['id'];
    $pid = $f['pid'];
    $enc = $f['encounter'];
    $dos = $f['date'];
    $lb = $f['last_level_billed'];
    $lc = $f['last_level_closed'];
    $stc = $f['stmt_count'];

    // get the patient balance for the encounter
    $pt_bal = get_patient_balance($pid, false, $enc);

    // get the number of insurances
    $insarr = getEffectiveInsurances($pid, $dos);
    $inscount = count($insarr);

    // we need the statement count since if it's non-zero openemr think's it's pt due
    // this should probably be decoupled down the road
    $stmt_count = $f['stmt_count'];

    // we only care about non-zero balances and those that have less levels closed than insurances
    if ($pt_bal != '0.00' || $pt_bal != '-0.00') {
        if($lc < $inscount) {
            echo "for pid $pid UPDATE form_encounter SET stmt_count = 0 where enc = $enc</br>";
            if (!$dry_run) {
                if (!sqlStatement("UPDATE form_encounter SET stmt_count = 0 where encounter = $enc")) {
                    echo "Houston, we have a problem with pid $pid and enc $eid</br>";
                };
            }
        }
    }
}

echo "you are good to go";