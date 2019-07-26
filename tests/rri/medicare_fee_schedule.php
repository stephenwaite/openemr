<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 7/26/19
 * Time: 2:19 PM
 */

use League\Csv\Reader;

require_once("../../interface/globals.php");


//$handle = fopen("/tmp/PFVT19A.TXT", "r");

$dbh = new PDO('mysql:dbname=' . $GLOBALS['dbase'] . ';host=' . $GLOBALS['host'], $GLOBALS['login'], $GLOBALS['pass']);
$sth = $dbh->prepare(
    "INSERT INTO medicare_phys_fee_schedule (year, carrier, locality, hcpcs, mod, non_fac_fee, fac_fee, 
pctc_indicator, status_code, mult_surg_indicator, office_therapy_reduct_amt, fac_therapy_reduct_amt, 
opps_indicator, opps_non_fac_fee_amt, opps_fac_fee_amt) VALUES (:year, :carrier, :locality, :hcpcs, :mod, :non_fac_fee, :fac_fee, :pctc_indicator, :status_code, :mult_surg_indicator, :office_therapy_reduct_amt, :fac_therapy_reduct_amt, :opps_indicator, :opps_non_fac_fee_amt, :opps_fac_fee_amt)"
);

$csv = Reader::createFromPath('/tmp/PFVT19A.TXT');


foreach ($csv as $record) {
//Do not forget to validate your data before inserting it in your database
    $sth->bindValue(':year', $record['year'], PDO::PARAM_STR);
    $sth->bindValue(':carrier', $record['carrier'], PDO::PARAM_STR);
    $sth->bindValue(':locality', $record['locality'], PDO::PARAM_STR);
    $sth->bindValue(':hcpcs', $record['hcpcs'], PDO::PARAM_STR);
    $sth->bindValue(':mod', $record['mod'], PDO::PARAM_STR);
    $sth->bindValue(':non_fac_fee', $record['non_fac_fee'], PDO::PARAM_STR);
    $sth->bindValue(':fac_fee', $record['fac_fee'], PDO::PARAM_STR);
    $sth->bindValue(':pctc_indicator', $record['pctc_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':status_code', $record['status_code'], PDO::PARAM_STR);
    $sth->bindValue(':mult_surg_indicator', $record['mult_surg_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':office_therapy_reduct_amt', $record['office_therapy_reduct_amt'], PDO::PARAM_STR);
    $sth->bindValue(':fac_therapy_reduct_amt', $record['fac_therapy_reduct_amt'], PDO::PARAM_STR);
    $sth->bindValue(':opps_indicator', $record['opps_indicator'], PDO::PARAM_STR);
    $sth->bindValue(':opps_non_fac_fee_amt', $record['opps_non_fac_fee_amt'], PDO::PARAM_STR);
    $sth->bindValue(':opps_fac_fee_amt', $record['opps_fac_fee_amt'], PDO::PARAM_STR);
    $sth->execute();
}
