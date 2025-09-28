<?php

/**
 * fix appt from csv
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_FIX_DOB')) {
    die('Set OPENEMR_ENABLE_FIX_DOB=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use League\Csv\Reader;

// setup a csv file with a header
$filename = 'SUN002-NextechPatientDemographics-20250922071517.csv';
$filepath = $GLOBALS['temporary_files_dir'];
$reader = Reader::createFromPath($filepath . DIRECTORY_SEPARATOR . $filename);
$reader->setDelimiter(",");

$start_record = $argv[2];
$reader->setHeaderOffset($start_record);
$header = $reader->getHeader();

$records = $reader->getRecords($header);

foreach ($records as $record) {
    //var_dump($record);
    //exit;
    if (substr($record['Birthdate'], -4, 2) == '19') {
        $query = sqlQuery("SELECT `pid`, `fname`, `lname`, `DOB` FROM `patient_data` WHERE `pubpid` = ?", [$record['AccountNumber']]);
        //echo $query['pid'] . " " . $query['fname'] . " " . $query['lname'] . " " . $query['DOB'] . "\n";
        //echo $record['FirstName'] . " " . $record['LastName'] . " " . $record['Birthdate'] . "\n";
        $formattedDob = date('Y-m-d',strtotime($record['Birthdate']));
        if ($query['DOB'] != $formattedDob) {
            echo "have to set dob to " . $formattedDob . "\n";
            $updateDOB = sqlStatement("UPDATE `patient_data` SET `DOB` = ? WHERE `pubpid` = ?", [$formattedDob, $record['AccountNumber']]);
        }
    }
    /* $updateAppt = sqlStatement(
        "UPDATE `openemr_postcalendar_events` SET `pc_catid` = 22 WHERE `pc_pid` = ? AND `pc_catid` = 5 AND `pc_eventDate` = ?",
        [$record['pc_pid'], $record['pc_eventDate']]
    ); */
    //var_dump($updateAppt);
    //exit;
}
