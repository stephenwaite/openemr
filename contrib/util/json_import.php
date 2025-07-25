<?php

/**
 * Create patients from a json file
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023-2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_JSON_IMPORT')) {
    die('Set OPENEMR_ENABLE_JSON_IMPORT=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default 2022-01-01 2022-12-31 primary MCDVT\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use OpenEMR\Services\{
    AppointmentService,
    InsuranceService,
    InsuranceCompanyService,
    EncounterService,
    PatientService
};

$zip = new ZipArchive();
$filename = "/tmp/test.zip";
$res = $zip->open($filename);
if ($res === true) {
    echo $zip->numFiles;
    print_r($zip);
    var_dump($zip);
    echo "numFiles: " . $zip->numFiles . "\n";
    echo "status: " . $zip->status  . "\n";
    echo "statusSys: " . $zip->statusSys . "\n";
    echo "filename: " . $zip->filename . "\n";
    echo "comment: " . $zip->comment . "\n";

/* for ($i=0; $i<$zip->numFiles;$i++) {
    echo "index: $i\n";
    print_r($zip->statIndex($i));
} */
    echo "numFile:" . $zip->numFiles . "\n";
} else {
    echo 'failed, code: ' . $res . "\n";
}

exit;
$file = file_get_contents($argv[2]);
//var_dump($file);
$ptJson = json_decode($file);
var_dump($ptJson);
$ptData = [
    'lname'    => $ptJson->PrimaryInformation->LastName,
    'fname'    => $ptJson->PrimaryInformation->FirstName,
    'mname'    => $ptJson->PrimaryInformation->Mi,
    'ss'       => $ptJson->PrimaryInformation->SSN,
    'pubpid'   => $ptJson->PrimaryInformation->ChartNumber,
    'DOB'      => $ptJson->IdentifyingInformation->DOB,
    'sex'      => $ptJson->IdentifyingInformation->Gender,
    'language' => $ptJson->IdentifyingInformation->Language,
    'phone_home' => $ptJson->ContactInformation->HomePhone,
    'phone_cell' => $ptJson->ContactInformation->MobilePhone,
    'email'      => $ptJson->ContactInformation->Email,
    'street'        => $ptJson->AddressInformation[0]->Street1,
    'street_line_2' => $ptJson->AddressInformation[0]->Street2,
    'city'          => $ptJson->AddressInformation[0]->City,
    'state'         => $ptJson->AddressInformation[0]->State,
    'postal_code'   => $ptJson->AddressInformation[0]->Zip,
    'contact_relationship' => $ptJson->EmergencyContactInformation[0]->Name . ' ' . $ptJson->EmergencyContactInformation[0]->RelationshipComment,
    'phone_contact' => $ptJson->EmergencyContactInformation[0]->Phone1,
];

var_dump($ptData);

$dbInsert = (new PatientService())->databaseInsert($ptData);
var_dump($dbInsert);
exit;

foreach ($json as $key => $item) {
    echo $key . "\n";
    var_dump($item);
}
