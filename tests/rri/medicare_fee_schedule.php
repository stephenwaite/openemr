<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 7/26/19
 * Time: 2:19 PM
 */

use League\Csv\Reader;
//use League\Csv\Statement;

require_once("../../interface/globals.php");

$dbh = new PDO('mysql:dbname=' . $GLOBALS['dbase'] . ';host=' . $GLOBALS['host'], $GLOBALS['login'], $GLOBALS['pass']);
//error_log("debug pdo with globals host " . $GLOBALS['host']);
//var_dump($dbh);
/*$stmt = $dbh->query('SELECT username FROM users');
while ($row = $stmt->fetch())
{
    echo $row['username'] . "<\br>";
}*/

$csv = Reader::createFromPath('/tmp/PFVT19A.TXT', 'r');
$csv->setHeaderOffset(0); //set the CSV header offset
$header = $csv->getHeader();

$sth = $dbh->prepare(
    "INSERT INTO medicare_phys_fee_schedule (year, carrier, locality, hcpcs, modifier, non_fac_fee, 
fac_fee, filler, pctc_indicator, status_code, mult_surg_indicator, office_therapy_reduct_amt, fac_therapy_reduct_amt,
opps_indicator, opps_non_fac_fee_amt, opps_fac_fee_amt) 
VALUES (:year, :carrier, :locality, :hcpcs, :modifier, :non_fac_fee, :fac_fee, :filler, :pctc_indicator, 
:status_code, :mult_surg_indicator, :office_therapy_reduct_amt, :fac_therapy_reduct_amt, 
:opps_indicator, :opps_non_fac_fee_amt, :opps_fac_fee_amt)"
);

/*//get 25 records starting from the 11th row
$stmt = (new Statement())
    ->offset(10)
    ->limit(25)
;

$records = $stmt->process($csv);
foreach ($records as $record) {
    //do something here
    echo $record['hcpcs'];
}*/


/*$csv = Reader::createFromPath('/tmp/pfvtsnip', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']

echo "header offset is $header_offset";
echo "header is ". print_r($header);*/

//echo "<html>";
foreach ($csv as $record) {
//Do not forget to validate your data before inserting it in your database
    $sth->bindValue(':year', $record['year']);
    $sth->bindValue(':carrier', $record['carrier'], PDO::PARAM_STR);
    $sth->bindValue(':locality', $record['locality'], PDO::PARAM_STR);
    $sth->bindValue(':hcpcs', $record['hcpcs'], PDO::PARAM_STR);
    $sth->bindValue(':modifier', $record['modifier'], PDO::PARAM_STR);
    $sth->bindValue(':non_fac_fee', $record['non_fac_fee'], PDO::PARAM_STR);
    $sth->bindValue(':fac_fee', $record['fac_fee'], PDO::PARAM_STR);
    $sth->bindValue(':filler', $record['filler'], PDO::PARAM_STR);
    $sth->bindValue(':pctc_indicator', $record['pctc_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':status_code', $record['status_code'], PDO::PARAM_STR);
    $sth->bindValue(':mult_surg_indicator', $record['mult_surg_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':office_therapy_reduct_amt', $record['office_therapy_reduct_amt'], PDO::PARAM_STR);
    $sth->bindValue(':fac_therapy_reduct_amt', $record['fac_therapy_reduct_amt'], PDO::PARAM_STR);
    $sth->bindValue(':opps_indicator', $record['opps_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':opps_non_fac_fee_amt', $record['opps_non_fac_fee_amt'], PDO::PARAM_STR);
    $sth->bindValue(':opps_fac_fee_amt', $record['opps_fac_fee_amt'], PDO::PARAM_STR);

    //var_dump($sth);
    //var_dump($dbh->errorInfo());
    //echo "<br />";

    $sth->execute();

    /*echo("year is " . $record['year']) . "<br />";
    echo("carrier is " . $record['carrier']) . "<br />";
    echo("locality is " . $record['locality']) . "<br />";
    echo("hcpcs is " . $record['hcpcs']) . "<br />";
    echo("modifier is " . $record['modifier']) . "<br />";
    echo("non_fac_fee is " . $record['non_fac_fee']) . "<br />";
    echo("fac_fee is " . $record['fac_fee']) . "<br />";
    echo("filler is " . $record['filler']) . "<br />";
    echo("pctc_indicator is " . $record['pctc_indicator']) . "<br />";
    echo("status_code is " . $record['status_code']) . "<br />";
    echo("mult_surg_indicator is " . $record['mult_surg_indicator']) . "<br />";
    echo("office_therapy_reduct_amt is " . $record['office_therapy_reduct_amt']) . "<br />";
    echo("fac_therapy_reduct_amt is " . $record['fac_therapy_reduct_amt']) . "<br />";
    echo("opps_indicator is " . $record['opps_indicator']) . "<br />";
    echo("opps_non_fac_fee_amt is " . $record['opps_non_fac_fee_amt']) . "<br />";
    echo("opps_fac_fee_amt is " . $record['opps_fac_fee_amt']) . "<br />";

    echo "<br />";*/
}

//var_dump($header);
