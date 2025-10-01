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

function batchConvertJpgsToPdf($sourceDir, $outputDir)
{
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }

    $files = glob($sourceDir . '/*.{jpg,jpeg,JPG,JPEG}', GLOB_BRACE);
    $converted = 0;

    foreach ($files as $jpgFile) {
        $filename = pathinfo($jpgFile, PATHINFO_FILENAME);
        $pdfFile = $outputDir . '/' . $filename . '.pdf';

        try {
            list($width, $height) = getimagesize($jpgFile);
            $width_mm = $width * 0.264583;
            $height_mm = $height * 0.264583;

            $pdf = new FPDF('P', 'mm', array($width_mm, $height_mm));
            $pdf->AddPage();
            $pdf->Image($jpgFile, 0, 0, $width_mm, $height_mm);
            $pdf->Output('F', $pdfFile);

            $converted++;
        } catch (Exception $e) {
            echo "Failed to convert $jpgFile: " . $e->getMessage() . "\n";
        }
    }

    return $converted;
}

//$base_url = getenv('BASE_OEMR_URL');
$base_url = "https://172.17.0.1:9300";
//$site_id = getenv('SUNPED_SITE_ID');
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
        //'client_id' => getenv('SUNPED_CLIENT_ID'),
        'client_id' => '6zhcRUNBs51RHvAYyprky75bPF2L2w4o-z1wOqMUCWQ',
        'redirect_uri' => 'https://localhost:9300',
        'scope' => "openid api:oemr user/appointment.read user/document.read user/document.write user/encounter.read user/encounter.write user/patient.read user/patient.write",
        'user_role' => 'users',
        //'username' => getenv('OEMR_RRI_USERNAME'),
        'username' => 's.waite',
        //'password' => getenv('OEMR_RRI_PASSWORD')
        'password' => '123456Sw.'
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
        (($fileSize < 30000) && ($filePath['extension'] == 'pdf'))
        || ($filePath['extension'] != 'jpg')
        || (stripos($pathName, 'patient photo') !== false)
    ) {
        continue;
    }
    $files[] = $pathName;
}

//echo count($files);

usort($files, function ($a, $b) {
    return strnatcmp($a, $b);
});

//echo count($files);
//var_dump($files);
//exit;

$outputDir = '/tmp/jpg';
exec('rm -r ' . $outputDir);
mkdir($outputDir);
foreach ($files as $key => $file) {
    $parts = explode('/', $file);
    var_dump($parts);
    $chartName = $parts[4];
    $length = strlen($chartName);
    $dashPos = strrpos($chartName, '-');
    $pos = ($length - $dashPos - 1) * -1;
    $pubpid = trim(substr($chartName, $pos));
    echo $pubpid . "\n";
    $folder = $parts[7];
    array_pop($parts);
    $newPath = implode('/', $parts);
    if ($key == 0) {
        $oldPath = $newPath;
    }
    echo $newPath . "\n";
    echo $oldPath . "\n";
    if ($newPath == $oldPath) {
        // add to array and continue
        $jpgArray[] = $file;
    } else {
        // write pdf;
         list($width, $height) = getimagesize($oldFile);
        $width_mm = $width * 0.264583;
        $height_mm = $height * 0.264583;
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

            $pdfFile = $outputDir . '/' . $fileName . '.pdf';

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
        if (!empty($jpgArray)) {
            $pdf->Output('F', $pdfFile);
            if (stripos($oldFolder, 'Practice Fusion') !== false) {
                $category = "SPECSCHART";
            } else {
                $category = "SPECSEXAMS";
            }
            apiDocumentPost($client, $base_url, $site_id, $headers, $oldPubpid, $category, $pdfFile);
            //var_dump($jpgArray);
            // add doc to jpg array after emptying
            $jpgArray = [];
        } else {
            $jpgArray[] = $file;
        }
    }

    $oldPath = $newPath;
    $oldFolder = $folder;
    $oldPubpid = $pubpid;
    $oldFile = $file;
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






//$puuidBinary = sqlQuery("SELECT `uuid` FROM `patient_data` WHERE pid = ?", [$pid])['uuid'];
//$puuid = UuidRegistry::uuidToString($puuidBinary);
//echo $puuid . "\n";

//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $puuid, $headers);
//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $pid . '/appointment', $headers);
