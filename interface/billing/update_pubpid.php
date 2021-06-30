<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 2/13/20
 * Time: 10:39 AM
 */

$ignoreAuth = true;
$_GET['site'] = $argv[1];

require_once(dirname(__FILE__) . "/../../interface/globals.php");

// l1out is output of x-l under dier after feeding it qstee.csv from encounters.php
$fh = fopen('/tmp/d1out','r');

while ($line = fgets($fh)) {
    // <... Do your work with the line ...>
    $pubpid = substr($line, 0, 4);
    $garno = substr($line,5,8);

    if (is_numeric($pubpid)) {
        echo "$pubpid is numeric \n ";
        //continue;
        $garno = str_replace("'", "\'",$garno);
        $query = "select id, pid, pubpid from patient_data where pubpid = $pubpid";
        $res = sqlStatement($query);
        //}
        if (sqlFetchArray($res)) {
            echo "we will update $pubpid to $garno \n";
            $q = "update patient_data set pubpid = '$garno' where pubpid = '$pubpid'";
            sqlStatement($q);
            echo $q . "</br>";
        } else {
            echo "this isn't matching, did we already update $pubpid? \n";
        }
    } else {
        echo "$pubpid is not numeric \n";
    }


}