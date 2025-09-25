<?php

/**
 * Load pt from csv
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_LOAD_PT')) {
    die('Set OPENEMR_ENABLE_LOAD_PT=1 environment variable to enable this script');
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
    PatientService
};
use League\Csv\Reader;

// setup a csv file with a header consiting of type, code and modifier
// at the specified location
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
    $ptData = [
    'lname'    => $record['LastName'],
    'fname'    => $record['FirstName'],
    'mname'    => $record['MiddleInitial'],
    'ss'       => $record['SocialSecurity'],
    'pubpid'   => $record['AccountNumber'],
    'DOB'      => date('Y-m-d',strtotime($record['Birthdate'])),
    'sex'      => $record['Gender'],
    'language' => $record['Language'],
    'phone_home' => $record['HomePhone'],
    'phone_cell' => $record['CellPhone'],
    'email'      => $record['Email'],
    'street'        => $record['Address1'],
    'street_line_2' => $record['Address2'],
    'city'          => $record['City'],
    'state'         => $record['State'],
    'postal_code'   => $record['Zip'],
    'contact_relationship' => $record['EmergencyContactFirstName'] . ' ' . $record['EmergencyContactLastName'] . ' ' .
        $record['EmergencyContactRelation'],
    'phone_contact' => $record['EmergencyContactHomePhone'],
    ];

    //var_dump($ptData);

    $dbInsert = (new PatientService())->databaseInsert($ptData);
    //var_dump($dbInsert);
}
