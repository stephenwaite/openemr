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
    die("Set OPENEMR_ENABLE_JSON_IMPORT=1 environment variable to enable this script \n");
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default /tmp/test.zip \n";
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
$filename = $argv[2];
$res = $zip->open($filename);
if ($res === true) {
     // As long as statIndex() does not return false keep iterating
    for ($idx = 0; $zipFile = $zip->statIndex($idx); $idx++) {
        $entry = $zip->getNameIndex($idx);
        $pathParts = pathinfo($entry);

         if ($pathParts['extension'] ?? '' && $pathParts['filename']) {
            var_dump($pathParts);
            
        }
        //exit;
        $isDir = (substr($entry, -1, 1) == '/');
            switch($entry) {
                case (stripos($entry, 'appointments') != false):
                    echo "this is appointments \n";
                    $section = 'appts';
                    break;
                case (stripos($entry, 'communications') != false):
                    echo "this is communications \n";
                    $section = 'comms';
                    break;
                case (stripos($entry, 'identification') != false):
                    echo "this is documents \n";
                    $section = 'ids';
                    break;
                case (stripos($entry, 'insurance') != false):
                    echo "this is documents \n";
                    $section = 'ins';
                    break;
                default:
                    //echo "default case \n";
                    $section = 'default';
            }

        }
    }
    $zip->close();


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

function loadAppointments($contents) {
    echo "going to load appts \n";
    var_dump(json_decode($contents));
}


/* echo "directory: " . $entry . "\n";
if (!$isDir) {
            echo $zipFile['name'] . "\n";
            $contents = $zip->getFromIndex($idx);
            if ($section == "appts") {
                $apptJson = $contents;

            } elseif ($section == "comms") {
                if (stripos($entry, 'ToDos.json') != false) {
                    $toDosJson = $contents;
                }
            } elseif ($section == "ids") {

            }
            // file contents
            //$contents = $zip->getFromIndex($idx);
        } else {
} else {
    echo 'failed, code: ' . $res . "\n";
}
 */
