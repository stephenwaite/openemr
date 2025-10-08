<?php

/**
 * post documents from file system to openemr with standard api
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_POST_PDF_API')) {
    die('Set OPENEMR_ENABLE_POST_PDF_API=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Request;
use League\Csv\Reader;
use OpenEMR\Common\Uuid\UuidRegistry;

$base_url = getenv('BASE_OEMR_URL');
$site_id = $argv[1];
$base_uri = $base_url . '/oauth2/' . $site_id . '/token';
//echo $base_uri . "\n";

$guzzle = new Client(
    ['verify' => false],
    ['debug' => true],
);
$response = $guzzle->post($base_uri, [
    'form_params' => [
        'grant_type' => 'password',
        'client_id' => getenv('SUNPED_CLIENT_ID'),
        'redirect_uri' => getenv("SUNPED_REDIRECT_URI"),
        'scope' => "openid api:oemr user/appointment.read user/document.read user/document.write user/encounter.read user/encounter.write user/patient.read user/patient.write",
        'user_role' => 'users',
        'username' => getenv('SUNPED_USERNAME'),
        'password' => getenv('SUNPED_PASSWORD')
    ],
]);
$bearer = json_decode((string) $response->getBody(), true)['access_token'];
//echo $bearer . "\n";
//exit;
$client = new Client(
    ['verify' => false],
    ['debug' => true]
);
$headers = [
  'Authorization' => 'Bearer ' . $bearer,
  'Accept' => 'application/json',
];

// setup a csv file with a header consiting of type, code and modifier
// at the specified location
$filename = 'SUN002-NextechDocumentsToPdf-20250922071517.csv';
//$filename = 'test.csv';
$tmpPath = $GLOBALS['temporary_files_dir'];
$reader = Reader::createFromPath($tmpPath . DIRECTORY_SEPARATOR . $filename);
$reader->setDelimiter(",");

//$start_record = $argv[2];
//$reader->setHeaderOffset($start_record);
//$header = $reader->getHeader();

$records = $reader->getRecords();

foreach ($records as $record) {
    //var_dump($record);
    //exit;
    $chartNumber = $record[1];
    $chartName = $record[3] . ", " . $record[2];

    $filePath = $record[5];
    $filePath = str_replace("\\", "/", substr(trim($filePath), 2));
    $filePath = rtrim($filePath, '.');
    //$filePath = str_replace([". ",], ".\ ", $filePath);
    //$filePath = str_replace(["$",], "\\$", $filePath);
    if (stripos($filePath, 'demo') !== false) {
        $category = "4.officedocuments";
    } elseif (stripos($filePath, 'medical history') !== false) {
        $category = "specspaperchart";
    } else {
        $category = "6.billingdocuments";
    }

    $photoDate = date('Y-m-d', strtotime($record[6]));
    $photoType = $record[7];
    /* $pattern = '/\$/';
    if (preg_match($pattern, $filePath)) {

        echo $filePath . "\n";
        $safe = preg_replace('/[^a-zA-Z0-9._-]/', '', $filePath);
        copy($filePath, $safe);
        echo $safe . "\n";
    } */
    $fileRoot = $tmpPath . DIRECTORY_SEPARATOR . "SUNPED" . DIRECTORY_SEPARATOR . "SUN002" . DIRECTORY_SEPARATOR;
    $origFileName =  $fileRoot . $filePath;
    //$parts = explode('/', $fullPath);
    //var_dump($parts);
    $newFileName = $fileRoot . str_replace('PatientFile', $photoType, $filePath) . "_" . $photoDate;
    echo $origFileName . "\n";
    echo $newFileName . "\n";
    //exit;
    copy($origFileName, $newFileName);
    //exit;
    //$shortPath =
    //$test = file_get_contents($fullPath);


    //echo "chart number " . $chartNumber . " category: " . $category . " photoType " . $photoType . "\n";
    apiDocumentPost($client, $base_url, $site_id, $headers, $chartNumber, $category, $newFileName);
    //exit;
}

function apiDocumentPost($client, $base_url, $site_id, $headers, $pubpid, $category, $fileName)
{
    $pid = sqlQuery("SELECT `pid` FROM `patient_data` WHERE `pubpid` = ?", [$pubpid])['pid'];

    $options = [
        'multipart' => [
            [
                'name' => 'document',
                'contents' => Utils::tryFopen($fileName, 'r'),
            ],
        ]
    ];

    $url = $base_url . '/apis/' . $site_id . '/api/patient/' . $pid . '/document?path=' . $category;
    $request = new Request('POST', $url, $headers);
    $res = $client->sendAsync($request, $options)->wait();

    //$ptObj = json_decode($res->getBody(), true);
    //var_dump($ptObj);
    //unlink($fileName);
    //$fileName = '';
}
