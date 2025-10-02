<?php

/**
 * Load appt from csv
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_LOAD_APPT')) {
    die('Set OPENEMR_ENABLE_LOAD_APPT=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";
use OpenEMR\Services\{
    AppointmentService,
};
use League\Csv\Reader;

// setup a csv file with a header consiting of type, code and modifier
// at the specified location
//$filename = 'SUN002-NextechAppointments-20250922071517.csv';
$filename = 'SUN002-NextechAppointments-20250922071517.csv';
$filepath = $GLOBALS['temporary_files_dir'];
$reader = Reader::createFromPath($filepath . DIRECTORY_SEPARATOR . $filename);
$reader->setDelimiter(",");

$start_record = $argv[2];
$reader->setHeaderOffset($start_record);
$header = $reader->getHeader();

$records = $reader->getRecords($header);

foreach ($records as $record) {
    $pid = sqlQuery("SELECT `pid` FROM `patient_data` WHERE `pubpid` = ?", [$record['AccountNumber']])['pid'];
    //echo $pid . "\n";
    //var_dump($record);
    //exit;
    $apptData['pc_pid'] = $pid;

    $type = $record['Type'];
    //echo $type . "\n";
    switch ($type) {
        case (stripos($type, 'office visit') !== false):
            $apptData['pc_catid'] = 5;
            break;
        case (stripos($type, 'new patient') !== false):
            $apptData['pc_catid'] = 10;
            break;
        case (stripos($type, 'est complete') !== false):
            $apptData['pc_catid'] = 9;
            break;
        case (stripos($type, 'rop outpatient') !== false):
            $apptData['pc_catid'] = 17;
            break;
        case (stripos($type, 'rop inpatient') !== false):
            $apptData['pc_catid'] = 16;
            break;
        case (stripos($type, 'contact lenses') !== false):
            $apptData['pc_catid'] = 25;
            break;
        case (stripos($type, 'adult double vision') !== false):
            $apptData['pc_catid'] = 13;
            break;
        case (stripos($type, 'adult follow up') !== false):
            $apptData['pc_catid'] = 21;
            break;
        case (stripos($type, 'post-op') !== false):
            $apptData['pc_catid'] = 22;
            break;
        case (stripos($type, 'procedure') !== false):
            $apptData['pc_catid'] = 12;
            break;
        case (stripos($type, 'surgery') !== false):
            $apptData['pc_catid'] = 15;
            break;
        case (stripos($type, 'telehealth') !== false):
            $apptData['pc_catid'] = 20;
            break;
        case (stripos($type, 'testing') !== false):
            $apptData['pc_catid'] = 14;
            break;
        case (stripos($type, 'urgent') !== false):
            $apptData['pc_catid'] = 24;
            break;
        default:
            $apptData['pc_catid'] = 5;
    }
    //echo $apptData['pc_catid'] . "\n";
    $title = sqlQuery("SELECT `pc_catname` FROM `openemr_postcalendar_categories` WHERE `pc_catid` = ?", [$apptData['pc_catid']])['pc_catname'];
    $apptData['pc_title'] = $title;
    $apptData['pc_duration'] = intval($record['Duration']) * 60;
    $apptData['pc_hometext'] = $record['Purpose'] . ' ' . $record['Notes'];
    $apptData['pc_eventDate'] = date('Y-m-d', strtotime($record['AppointmentDate']));
    $isCanceled = (stripos($record['IsCancelled'], 'TRUE') !== false);
    $isNoShow = (stripos($record['IsNoShow'], 'TRUE') !== false);
    $isUnconfirmed = (stripos($record['Status'], 'Unconfirmed') !== false);
    $isCompleted = (stripos($record['Status'], 'completed') !== false) || (stripos($record['Status'], 'performed') !== false);
    $isRescheduled = (stripos($record['Status'], 'rescheduled') !== false);

    $apptData['pc_apptstatus'] = "AVM";
    if ($isCanceled) {
        $apptData['pc_apptstatus'] = "x";
    }

    if ($isNoShow) {
        $apptData['pc_apptstatus'] = "?";
    }

    if ($isUnconfirmed) {
        $apptData['pc_apptstatus'] = "-";
    }

    if ($isCompleted) {
        $apptData['pc_apptstatus'] = ">";
    }

    if ($isRescheduled) {
        $apptData['pc_apptstatus'] = "r";
    }

    $apptData['pc_startTime'] = date('H:i:s', strtotime($record['StartTime']));
    $apptData['pc_endTime'] = date('H:i:s', strtotime($record['EndTime']));

    // lawrence = 3
    // topeka = 4
    // manhattan = 5
    // lsc = 6
    // lmh = 7
    // stv = 8
    $facility = $record['Location'];
    switch ($facility) {
        case (stripos($facility, 'lawrence') !== false):
            $apptData['pc_facility'] = 3;
            break;
        case (stripos($facility, 'topeka') !== false):
            $apptData['pc_facility'] = 4;
            break;
        case (stripos($facility, 'manhattan') !== false):
            $apptData['pc_facility'] = 5;
            break;
        case (stripos($facility, 'lsc') !== false):
            $apptData['pc_facility'] = 6;
            break;
        case (stripos($facility, 'lmh') !== false):
            $apptData['pc_facility'] = 7;
            break;
        case (stripos($facility, 'stv') !== false):
            $apptData['pc_facility'] = 8;
            break;
        default:
            $apptData['pc_facility'] = 3;
    }


    $apptData['pc_billing_location'] = 3;

    // Marie is 5, Scriven is 9
    $apptData['pc_aid'] = 5;
    if (stripos($record['Resource'], 'Scriven') !== false) {
        $apptData['pc_aid'] = 9;
    }
    $dbInsert = (new AppointmentService())->insert($pid, $apptData);
    //var_dump($apptData);
}
