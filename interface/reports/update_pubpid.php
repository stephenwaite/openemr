<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 2/13/20
 * Time: 10:39 AM
 */
require_once("../globals.php");

$fh = fopen('qstee.csv','r');

while ($line = fgets($fh)) {
    // <... Do your work with the line ...>
    $pubpid = substr($line, 0, 5);
    $garno = substr($line,5,8);

    $query = "select id, pid, pubpid from patient_data where pubpid = $pubpid";
    $res = sqlStatement($query);
    //}
    if(sqlFetchArray($res)){
        var_dump($res);
        exit();
        echo "we will update $pubpid to $garno" . "</br>";
        $q = "update patient_data set pubpid = '$garno' where pubpid = '$pubpid'";
        sqlStatement($q);
    } else {
        echo "this isn't matching, did we already update $pubpid?" . "</br>";
    }


}