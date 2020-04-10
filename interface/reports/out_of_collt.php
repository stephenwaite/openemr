<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 4/9/20
 * Time: 2:08 PM
 */

// Checks if the server's PHP version is compatible with OpenEMR:
require_once(dirname(__FILE__) . "/../../src/Common/Compatibility/Checker.php");
$response = OpenEMR\Common\Compatibility\Checker::checkPhpVersion();
if ($response !== true) {
    die(htmlspecialchars($response));
}

$ignoreAuth = true;
$_GET['site'] = 'default';

require_once('../globals.php');

//var_dump($GLOBALS);

$query = "SELECT * FROM `patient_data` WHERE `billing_note` like '%in collections%'";
$stmt = sqlStatement($query);
$needle = "IN COLLECTIONS 2020-04-09";
//$needle = "steve";
while ($res = sqlFetchArray($stmt)) {
    $str = $res['billing_note'];
    $pid = $res['pid'];
    $pos = stripos($str, $needle);
    //echo "pos is $pos \n";
    if ($pos !== false) {
        //var_dump($pos);
        $str2 = substr($str, $pos + 25);
        echo $str2 . "\n";
        $query2 = "UPDATE `patient_data` SET `billing_note` = ? WHERE `pid` = ?";
        //echo $query2;
        sqlStatement($query2, array($str2, $pid));
    } else {
        //echo "no match\n";
    }
}