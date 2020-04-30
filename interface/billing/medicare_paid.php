<?php

require_once(dirname(__FILE__) . "/../globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");

$total = 0;
$date_from = $_GET['from_date'];
$date_to   = $_GET['to_date'];

$counter = 0;
$query = "SELECT * FROM `ar_activity` WHERE `post_time` > '" . $date_from . " 00:00:00'" .
    "and `post_time` < '" . $date_to . " 23:59:59'";
//echo $query . "</br>";
//exit();
$res = sqlStatement($query);
while ($row = sqlFetchArray($res)) {
    $ins = getInsuranceData($row['pid']);
    if ($ins['provider'] == '4' || $ins['provider'] == '8') {
        if ($row['pay_amount'] > 0) {
            $counter++;
            //var_dump($ins);
            echo "we've got " . $row['pay_amount'] .
                " for a " . $ins['provider_name'] . " for patient " . $row['pid'] . "</br>";
            $total = $total + $row['pay_amount'];
            echo "running total $total" . "</br>";
        } /*elseif ($row['pay_amount'] == 0) {
            $match1 = stripos($row['memo'], "dedbl");
            $match2 = stripos($row['memo'], "Ins1 coins");
            $match3 = stripos($row['memo'], "Adjust code 253");
            $pos    = stripos($row['memo'], ":") + 1;
            if ($match1 !== false) {
                echo "medicare dedble for patient " . $row['pid'] . substr($row['memo'], $pos) . "</br>";
                $total = $total + intval(substr($row['memo'], $pos));
                echo "running total $total" . "</br>";
            } elseif ($match2 !== false) {
                echo "medicare coins for patient " . $row['pid'] . substr($row['memo'], $pos) . "</br>";
                $total = $total + intval(substr($row['memo'], $pos));
                echo "running total $total" . "</br>";
            } *///elseif ($match3 !== false) {
              //  echo "medicare sequest for patient " . $row['adj_amount'] . "</br>";
              //  $total = $total + $row['adj_amount'];
               // echo "running total $total" . "</br>";
           // }
        //}
    }
}

//and (`pay_amount` > 0 || ('pay_amount' = 0 and )
//Ins1 coins: 22.97

echo "counter is $counter";

