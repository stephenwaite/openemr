<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 7/29/19
 * Time: 2:06 PM
 */

use League\Csv\Writer;


require_once("../../interface/globals.php");

$fh = fopen("/tmp/w4", 'r');
$dbh = new PDO('mysql:dbname=' . $GLOBALS['dbase'] . ';host=' . $GLOBALS['host'], $GLOBALS['login'], $GLOBALS['pass']);
$sth = $dbh->prepare("SELECT hcpcs, modifier, fac_fee FROM medicare_phys_fee_schedule WHERE hcpcs = :hcpcs AND modifier = :modifier");

$writer = Writer::createFromPath('/tmp/file.csv', 'w+');

echo "<html><pre>";
if ($fh) {
    while (($line = fgets($fh)) !== false) {
        $procfile['cdm'] = substr($line, 0, 4);
        $procfile['hcpcs'] = substr($line, 4,5);
        $procfile['mod'] = substr($line, 9, 2);
        $procfile['type'] = substr($line, 11, 1);
        $procfile['description'] = substr($line, 12, 28);
        $procfile['fee'] = floatval(substr($line, 40, 6) / 100);
        /*echo $procfile['cdm'];
        echo $procfile['hcpcs'];
        echo $procfile['mod'];
        echo $procfile['type'];
        echo $procfile['description'];
        echo $procfile['fee'] . "<br />";*/

        $sth->execute(array(':hcpcs' => $procfile['hcpcs'], ':modifier' => $procfile['mod']));
        //var_dump($sth);

        $result = $sth->fetch(PDO::FETCH_LAZY);
        if (($procfile['hcpcs'] = $result['hcpcs']) && ($procfile['mod'] = $result['modifier']) && $procfile['fee'] != "0") {
            //print_r($result['fac_fee']);
            //print("\n");
            $med_multiply = round($procfile['fee'] / $result['fac_fee'] * 100, 2);
            $writer->insertOne(array($result['hcpcs'], $result['modifier'], $procfile['description'], $result['fac_fee'], $procfile['fee'], $med_multiply));

        } /*else {
            print_r($result['fac_fee']);
            echo $procfile['hcpcs'];
            echo $procfile['mod'];
            print("\n");
        }
        */



    }
}

