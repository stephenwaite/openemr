<?php

$ignoreAuth = true;
$_GET['site'] = 'default';
//$argv = $_GET['argv'];
require_once(dirname(__FILE__) . "/../interface/globals.php");

use League\Csv\Reader;

$reader = Reader::createFromPath('/tmp/test.csv', 'r');
$reader->setHeaderOffset(0);

$records = $reader->getRecords();
foreach ($records as $offset => $record) {
    //$offset : represents the record offset
    //var_export($record); // returns something like
    //var_dump($record);
    //if (in_array($record['Date'], $record)) {
    //echo $record['Date'] . "\n";
    //echo implode('', array_reverse(explode('/', $record['Date']))) . "\n";
    if ($record['Type'] == 'Website Payment' && $record['Status'] ==  "Completed") {
        $status = "PAY";

        $newDate = preg_replace("/(\d+)\D+(\d+)\D+(\d+)/", "$3$1$2", $record['Date']);

        $name = $record['Name'];
        $garno = strtoupper($record['Subject']);
        $gross = $record['Gross'];
        $fee   = -1 * $record['Fee'];
        $net   = $record['Net'];
        $email = $record['From Email Address'];

        echo $newDate . ",\"" . $name . "\"," . $garno . "," . $gross . "," . $status . ",T,0," . $fee . "," . $net . "\n";

    } else {
        //var_dump($record);
    }
    //}
    // array(
    //  'john',
    //  'doe',
    //  'john.doe@example.com'
    // );
    //
}


