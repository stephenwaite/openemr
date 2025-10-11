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
if (!getenv('OPENEMR_ENABLE_POST_JPG_TO_PDF_API')) {
    die('Set OPENEMR_ENABLE_POST_JPG_TO_PDF=1 environment variable to enable this script');
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
use OpenEMR\Common\Uuid\UuidRegistry;

$base_url = getenv('BASE_OEMR_URL');
$site_id = $argv[1];
$base_uri = $base_url . '/oauth2/' . $site_id . '/token';
//echo $base_uri . "\n";

$guzzle = new Client(
    ['verify' => false],
    ['debug' => true],
);
try {
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
} catch (Exception $e) {
    echo "error msg " . $e->getMessage();
    exit;
}

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

$path = '/tmp/SUNPED/Patients';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
$files = array();

/** @var SplFileInfo $file */
foreach ($rii as $file) {
    if ($file->isDir()) {
        continue;
    }
    $pathName = $file->getPathname();
    $fileSize = filesize($pathName);
    $filePath = pathinfo($pathName);
    if (
        ($filePath['extension'] != 'jpg')
        || (str_contains($pathName, 'Patient Photo'))
    ) {
        continue;
    }
    //echo $pathName . "\n";
    $files[] = $pathName;
}

//echo count($files);

usort($files, function ($a, $b) {
    return strnatcmp($a, $b);
});

$countFiles = count($files);
//var_dump($files);
//exit;

$outputDir = '/tmp/jpg';
exec('rm -r ' . $outputDir);
mkdir($outputDir);
foreach ($files as $key => $file) {
    $cntr++;
    $parts = explode('/', $file);
    //var_dump($parts);
    $pubpid = getPubpid($parts[4]);
    //echo $pubpid . " pubpid \n";
    $folder = $parts[7];
    array_pop($parts);
    $newPath = implode('/', $parts);
    //echo $newPath . " newPath \n";
    //echo $oldPath . " oldPath\n";
    if (
        ($newPath != ($oldPath ?? ''))
        && $key != 0
        || $key == $countFiles - 1
    ) {
        //echo $oldPath . " oldPath \n";
        $jpg2Pdf = true;
        if (str_contains($oldPath, '.Spec Practice Fusion')) {
            $category = "SPECSPAPEREXAMS";
        } elseif (str_contains($oldPath, "Unknown_FileType")) {
            $category = "Procedures";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, ".Spec Paper Chart")) {
            $category = "SPECSPAPERCHART";
        } elseif (str_contains($oldPath, ".Spec Paper Exam")) {
            $category = "SPECSPAPEREXAMS";
        } elseif (
            str_contains($oldPath, "Medical Record")
                || str_contains($oldPath, ".NP Needs Appt")
                || str_contains($oldPath, ".New Patient Referral")
        ) {
                $category = "OutsideRecords";
        } elseif (str_contains($oldPath, ".OCT")) {
            $category = "OCT-EYE";
            $jpg2Pdf = false;
        } elseif (
            str_contains($oldPath, ".OP Notes")
            || str_contains($oldPath, ".Surgical")
        ) {
            $category = "Procedures";
        } elseif (str_contains($oldPath, ".External Photos")) {
            $category = "ExternalPhotos-Eye";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Fundus Photos")) {
            $category = "FUNDUS-Eye";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Consents")) {
            $category = "4.OfficeDocuments";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Authorizations")) {
            $category = "6.BillingDocuments";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, ".Patient Communication")) {
            $category = "Communication-Eye";
        } elseif (str_contains($oldPath, ".Visual Fields")) {
            $category = "VF-Eye";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Anterior SegSL photos")) {
            $category = "AntSegPhotos-Eye";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Insurance Card")) {
            $category = "InsuranceIDcard";
            $jpg2Pdf = false;
        } elseif (str_contains($oldPath, "Imported Document")) {
            $category = "OutsideRecords";
        } elseif (str_contains($oldPath, "Lab Results")) {
            $category = "LabReport";
        } else {
            $category = "SPECSPAPEREXAMS";
        }
        echo $category . " category \n";

        if ($jpg2Pdf) {
            $newPdf = writePdf($oldFile, $jpgArray, $oldFolder, $outputDir);
            apiDocumentPost($client, $base_url, $site_id, $headers, $oldPubpid, $category, $newPdf);
        } else {
            foreach ($jpgArray as $jpgFile) {
                $newJpgFileName = getNewJpgFileName($jpgFile, $outputDir);
                apiDocumentPost($client, $base_url, $site_id, $headers, $oldPubpid, $category, $newJpgFileName);
            }
        }
        $jpgArray = [];
    }

    echo $file . " file \n";
    $jpgArray[] = $file;

    $oldPath = $newPath;
    $oldFolder = $folder;
    $oldPubpid = $pubpid;
    $oldFile = $file;
}

function getPubpid($string): string
{
    $length = strlen($string);
    $dashPos = strrpos($string, '-');
    $pos = ($length - $dashPos - 1) * -1;
    $pubpid = trim(substr($string, $pos));
    return $pubpid;
}

function getNewJpgFileName($pathToFile, $tmpDir)
{
    $parts = explode('/', $pathToFile);
    $newName = $tmpDir . DIRECTORY_SEPARATOR . $parts[7] . "_" . $parts[8];
    copy($pathToFile, $newName);
    return $newName;
}

function writePdf($oldFile, $jpgArray, $oldFolder, $outputDir): string
{
// write pdf;
        list($width, $height) = getimagesize($oldFile);
        $width_mm = round($width * 0.264583);
        $height_mm = round($height * 0.264583);
        $pdf = new FPDF('P', 'mm', [$width_mm, $height_mm]);
    foreach ($jpgArray as $jpgFile) {
        //echo $jpgFile . "\n";
        $fileName = $oldFolder;
        $mimeType = mime_content_type($jpgFile);
        if ($mimeType == 'image/png') {
            // post to docs as png
            echo "will post to docs as png \n";
            copy($jpgFile, $jpgFile . ".png");
            $jpgFile = $jpgFile . ".png";
        }

        $pdfFile = $outputDir . DIRECTORY_SEPARATOR . $fileName . '.pdf';

        try {
            //list($width, $height) = getimagesize($jpgFile);
            //$width_mm = $width * 0.264583;
            //$height_mm = $height * 0.264583;
            $pdf->AddPage();
            $pdf->Image($jpgFile, 0, 0, $width_mm, $height_mm);
        } catch (Exception $e) {
            echo "Failed to convert $jpgFile: " . $e->getMessage() . "\n";
        }
    }
        echo "writing image \n";
        //var_dump($jpgArray);
        //if (!empty($jpgArray)) {
            $pdf->Output('F', $pdfFile);
            return $pdfFile;

            //var_dump($jpgArray);
            // add doc to jpg array after emptying
        //    $jpgArray = [];
        //}
}
function apiDocumentPost($client, $base_url, $site_id, $headers, $pubpid, $category, $fileName)
{
    $pid = sqlQuery("SELECT `pid` FROM `patient_data` WHERE `pubpid` = ?", [$pubpid])['pid'];
    echo $pid . " pid \n";

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
    unlink($fileName);
    //$fileName = '';
}






//$puuidBinary = sqlQuery("SELECT `uuid` FROM `patient_data` WHERE pid = ?", [$pid])['uuid'];
//$puuid = UuidRegistry::uuidToString($puuidBinary);
//echo $puuid . "\n";

//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $puuid, $headers);
//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $pid . '/appointment', $headers);

/* foreach ($files as $key => $file) {
    $parts = explode('/', $file);
    $partsArr[] = $parts;
}

$uniquePart = getUniqueFieldValues($partsArr, '6');
print_r($uniquePart);
exit; */

/* Array
(
    [0] => .Spec Practice Fusion
    [1] => Unknown_FileType
    [2] => .NP Needs Appt
    [3] => .Spec Paper Chart
    [4] => .Spec Paper Exam
    [5] => .New Patient Referral
    [6] => .Medical Record
    [7] => .OP Notes
    [8] => .Surgical
    [9] => .External Photos
    [10] => .OCT
    [11] => Fundus Photos
    [12] => Authorizations
    [13] => Consents
    [14] => .Patient Communication
    [15] => .Visual Fields
    [16] => Anterior SegSL photos
    [17] => Insurance Card 1
    [18] => Imported Document
    [19] => Lab Results
) */

/* function getUniqueFieldValues($array, $field)
{
    return array_values(array_reduce($array, function ($carry, $item) use ($field) {
        $value = $item[$field] ?? null;
        if ($value !== null && !in_array($value, $carry)) {
            $carry[] = $value;
        }
        return $carry;
    }, []));
} */
