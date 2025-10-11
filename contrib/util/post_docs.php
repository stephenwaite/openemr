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
if (!getenv('OPENEMR_ENABLE_POST_DOCS_API')) {
    die('Set OPENEMR_ENABLE_POST_DOCS_API=1 environment variable to enable this script');
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
        ($fileSize < 30000) || !($filePath['extension'] == 'pdf' || ($filePath['extension'] == 'xml'))
    ) {
        continue;
    }
    $files[] = $pathName;
}

/* foreach ($files as $key => $file) {
    $parts = explode('/', $file);
    $partsArr[] = $parts;
}

$uniquePart = getUniqueFieldValues($partsArr, '6');
print_r($uniquePart);
exit; */

//echo count($files);

usort($files, function ($a, $b) {
    return strnatcmp($a, $b);
});

/* echo count($files) . " count files \n";
var_dump($files);

exit; */

foreach ($files as $file) {
    $parts = explode('/', $file);
    //var_dump($parts);
    $chartName = $parts[4];
    $length = strlen($chartName);
    $dashPos = strrpos($chartName, '-');
    $pos = ($length - $dashPos - 1) * -1;
    $pubpid = trim(substr($chartName, $pos));
    //echo $pubpid . "\n";
    //var_dump($pubpid);
    $pid = sqlQuery("SELECT `pid` FROM `patient_data` WHERE `pubpid` = ?", [$pubpid])['pid'];
    //var_dump($pid);
    //echo $pid . "\n";
    //$pathInfo = pathinfo($file);
    //var_dump($pathInfo);
    //$ext = $pathInfo['extension'];
    if (str_contains($file, 'ChartNote') !== false) {
        $category = "SPECSPAPEREXAMS";
    } elseif (str_contains($file, 'CCDA') !== false) {
        $category = "zCCDA";
    } elseif (str_contains($file, 'Images') !== false) {
        $category = "OCT-EYE";
    } elseif (str_contains($file, "Consents")) {
        $category = "4.OfficeDocuments";
    } elseif (str_contains($file, "_Letter_")) {
        $category = "Communication-Eye";
    } else {
        $category = "4.OfficeDocuments";
    }

    foreach ($parts as $part) {
        if (
            $part == 'tmp' ||
            $part == 'SUNPED' ||
            $part == 'Patients'
        ) {
            continue;
        }

        if (empty($fileName)) {
            $fileName = $part;
        } else {
            echo $file . "\n";
            echo $part . "\n";
            if (str_contains($part, 'Letter Consultation')) {
                $part = str_replace('Letter Consultation', 'Consult', $part);
            } elseif (str_contains($part, 'Letter Update')) {
                $part = str_replace('Letter Update', 'Update', $part);
            } elseif (str_contains($part, "General Consent for Medical and Surgical Procedure")) {
                $part = str_replace("General Consent for Medical and Surgical Procedure", "General Consent", $part);
            } elseif (str_contains($part, "Informed Consent for Strabismus Surgery")) {
                $part = str_replace("Informed Consent for Strabismus Surgery", "Informed Consent", $part);
            } elseif (str_contains($part, "Consent for Probing and Irrigation of Tear Duct")) {
                $part = str_replace("Consent for Probing and Irrigation of Tear Duct", "Tear Duct Consent", $part);
            }
            //echo $part . "\n";
            $fileName .= '_' . $part;
        }
    }
    //echo $fileName . "\n";
    $fileName = trim($fileName);
    $fileName = preg_replace('/___/', '_', $fileName);
    $fileName = '/tmp/' . $fileName;
    copy($file, $fileName);
    //echo $fileName . "\n";
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
    $fileName = '';
}


/* function getUniqueFieldValues($array, $field)
{
    return array_values(array_reduce($array, function ($carry, $item) use ($field) {
        $value = $item[$field] ?? null;
        if ($value !== null && !in_array($value, $carry)) {
            $carry[] = $value;
        }
        return $carry;
    }, []));
}
 */
//$puuidBinary = sqlQuery("SELECT `uuid` FROM `patient_data` WHERE pid = ?", [$pid])['uuid'];
//$puuid = UuidRegistry::uuidToString($puuidBinary);
//echo $puuid . "\n";

//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $puuid, $headers);
//$request = new Request('GET', $base_url . '/apis/' . $site_id . '/api/patient/' . $pid . '/appointment', $headers);

/* Array
(
    [0] => ChartNote.pdf
    [1] => 1_Letter_Update.pdf
    [2] => Unsigned - ChartNote.pdf
    [3] => 1_Letter_Report.pdf
    [4] => 1_Letter_Consultation.pdf
    [5] => Informed Consent for Strabismus Surgery (OS)_49.pdf
    [6] => Informed Consent for Strabismus Surgery_71.pdf
    [7] => Informed Consent for Strabismus Surgery_123.pdf
    [8] => Informed Consent for Strabismus Surgery (OU)_75.pdf
    [9] => 1_Letter_Referral.pdf
    [10] =>  Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System) (Right Eye) _3.pdf
    [11] => Informed Consent for Strabismus Surgery (OU)_99.pdf
    [12] => Informed Consent for Strabismus Surgery_143.pdf
    [13] => Informed Consent for Strabismus Surgery_142.pdf
    [14] => Informed Consent for Strabismus Surgery (OU)_90.pdf
    [15] => 2_Letter_Report.pdf
    [16] => Informed Consent for Strabismus Surgery (OU)_5.pdf
    [17] => 1_Letter_Second Opinion.pdf
    [18] => 1_Letter_No Show.pdf
    [19] => Informed Consent for Strabismus Surgery_82.pdf
    [20] => Informed Consent for Strabismus Surgery (OS)_60.pdf
    [21] => Informed Consent for Strabismus Surgery_23.pdf
    [22] => 2_Letter_Consultation.pdf
    [23] =>    General Consent for Medical and Surgical Procedure_108.pdf
    [24] =>    General Consent for Medical and Surgical Procedure (OS)_78.pdf
    [25] => Informed Consent for Strabismus Surgery_7.pdf
    [26] => Informed Consent for Strabismus Surgery_46.pdf
    [27] => Informed Consent for Strabismus Surgery (OU)_144.pdf
    [28] => Informed Consent for Strabismus Surgery_13.pdf
    [29] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_130.pdf
    [30] => 2_Letter_Update.pdf
    [31] => 2_Letter_Referral.pdf
    [32] =>    General Consent for Medical and Surgical Procedure (OD)_104.pdf
    [33] => Informed Consent for Strabismus Surgery (OD)_62.pdf
    [34] => Informed Consent for Strabismus Surgery (OD)_91.pdf
    [35] => Informed Consent for Strabismus Surgery (OU)_77.pdf
    [36] => Informed Consent for Strabismus Surgery (OD)_76.pdf
    [37] => Informed Consent for Strabismus Surgery_103.pdf
    [38] => Informed Consent for Strabismus Surgery_145.pdf
    [39] => Informed Consent for Strabismus Surgery_131.pdf
    [40] => Informed Consent for Strabismus Surgery_55.pdf
    [41] =>    General Consent for Medical and Surgical Procedure (OS)_102.pdf
    [42] => Informed Consent for Strabismus Surgery (OS)_93.pdf
    [43] => Informed Consent for Strabismus Surgery_86.pdf
    [44] => Informed Consent for Strabismus Surgery (OS)_54.pdf
    [45] => Informed Consent for Strabismus Surgery_111.pdf
    [46] => Informed Consent for Strabismus Surgery_11.pdf
    [47] => Informed Consent for Strabismus Surgery_26.pdf
    [48] => Informed Consent for Strabismus Surgery (OU)_73.pdf
    [49] => Informed Consent for Strabismus Surgery (OU)_138.pdf
    [50] => Informed Consent for Strabismus Surgery (OS)_124.pdf
    [51] => Informed Consent for Strabismus Surgery_56.pdf
    [52] => Informed Consent for Strabismus Surgery (OS)_44.pdf
    [53] => Informed Consent for Strabismus Surgery_96.pdf
    [54] =>    General Consent for Medical and Surgical Procedure_53.pdf
    [55] => Informed Consent for Strabismus Surgery (OU)_114.pdf
    [56] =>    General Consent for Medical and Surgical Procedure (OD)_136.pdf
    [57] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_40.pdf
    [58] => Informed Consent for Strabismus Surgery_39.pdf
    [59] => Informed Consent for Strabismus Surgery_37.pdf
    [60] => Informed Consent for Strabismus Surgery (OU)_134.pdf
    [61] =>    General Consent for Medical and Surgical Procedure_22.pdf
    [62] =>    General Consent for Medical and Surgical Procedure (OD)_95.pdf
    [63] => Informed Consent for Strabismus Surgery_64.pdf
    [64] => Informed Consent for Strabismus Surgery (OU)_117.pdf
    [65] => Informed Consent for Strabismus Surgery_122.pdf
    [66] => Informed Consent for Strabismus Surgery_47.pdf
    [67] => Informed Consent for Strabismus Surgery_87.pdf
    [68] => Informed Consent for Strabismus Surgery_100.pdf
    [69] => Informed Consent for Strabismus Surgery_141.pdf
    [70] => Informed Consent for Strabismus Surgery_119.pdf
    [71] => Informed Consent for Strabismus Surgery_21.pdf
    [72] => Informed Consent for Strabismus Surgery (OS)_74.pdf
    [73] => Informed Consent for Strabismus Surgery_20.pdf
    [74] => Informed Consent for Strabismus Surgery_41.pdf
    [75] => Informed Consent for Strabismus Surgery (OU)_110.pdf
    [76] => Informed Consent for Strabismus Surgery (OS)_133.pdf
    [77] => Informed Consent for Strabismus Surgery_61.pdf
    [78] => Informed Consent for Strabismus Surgery_29.pdf
    [79] => Informed Consent for Strabismus Surgery_83.pdf
    [80] => Informed Consent for Strabismus Surgery_72.pdf
    [81] => Informed Consent for Strabismus Surgery (OU)_85.pdf
    [82] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_92.pdf
    [83] => Informed Consent for Strabismus Surgery_65.pdf
    [84] => Informed Consent for Strabismus Surgery_89.pdf
    [85] => Informed Consent for Strabismus Surgery_94.pdf
    [86] => Informed Consent for Strabismus Surgery_27.pdf
    [87] => Informed Consent for Strabismus Surgery_48.pdf
    [88] => Informed Consent for Strabismus Surgery (OS)_97.pdf
    [89] => Informed Consent for Strabismus Surgery_15.pdf
    [90] => Informed Consent for Strabismus Surgery_79.pdf
    [91] => Informed Consent for Strabismus Surgery_25.pdf
    [92] => Informed Consent for Strabismus Surgery_116.pdf
    [93] => Informed Consent for Strabismus Surgery_10.pdf
    [94] => Informed Consent for Strabismus Surgery (OU)_126.pdf
    [95] => Informed Consent for Strabismus Surgery_8.pdf
    [96] => Informed Consent for Strabismus Surgery_42.pdf
    [97] => Informed Consent for Strabismus Surgery_128.pdf
    [98] =>    General Consent for Medical and Surgical Procedure_84.pdf
    [99] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_18.pdf
    [100] => Informed Consent for Strabismus Surgery (OS)_70.pdf
    [101] => Informed Consent for Strabismus Surgery (OU)_88.pdf
    [102] => Informed Consent for Strabismus Surgery_28.pdf
    [103] => Informed Consent for Strabismus Surgery (OD)_132.pdf
    [104] => Informed Consent for Strabismus Surgery_69.pdf
    [105] => Informed Consent for Strabismus Surgery (OU)_139.pdf
    [106] => Informed Consent for Strabismus Surgery_98.pdf
    [107] => Informed Consent for Strabismus Surgery_67.pdf
    [108] => Informed Consent for Strabismus Surgery_121.pdf
    [109] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_19.pdf
    [110] => Informed Consent for Strabismus Surgery_9.pdf
    [111] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_32.pdf
    [112] => Informed Consent for Strabismus Surgery_66.pdf
    [113] =>    General Consent for Medical and Surgical Procedure (OU)_127.pdf
    [114] => Informed Consent for Strabismus Surgery_80.pdf
    [115] => Informed Consent for Strabismus Surgery_115.pdf
    [116] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System) (OS)_120.pdf
    [117] => Informed Consent for Strabismus Surgery_52.pdf
    [118] => Informed Consent for Strabismus Surgery_36.pdf
    [119] => Informed Consent for Strabismus Surgery (OU)_105.pdf
    [120] => Informed Consent for Strabismus Surgery_106.pdf
    [121] => Informed Consent for Strabismus Surgery_6.pdf
    [122] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_14.pdf
    [123] => Informed Consent for Strabismus Surgery (OS)_33.pdf
    [124] => Informed Consent for Strabismus Surgery (OU)_58.pdf
    [125] => Informed Consent for Strabismus Surgery (OU)_118.pdf
    [126] => Informed Consent for Strabismus Surgery_12.pdf
    [127] => Consent for Use of Botox_101.pdf
    [128] => Informed Consent for Strabismus Surgery_38.pdf
    [129] => Informed Consent for Strabismus Surgery (OS)_59.pdf
    [130] => Informed Consent for Strabismus Surgery_45.pdf
    [131] => Informed Consent for Strabismus Surgery (OU)_81.pdf
    [132] =>    General Consent for Medical and Surgical Procedure (OU)_137.pdf
    [133] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System)_135.pdf
    [134] => Informed Consent for Strabismus Surgery_125.pdf
    [135] => Informed Consent for Strabismus Surgery_43.pdf
    [136] => Informed Consent for Strabismus Surgery (OD)_107.pdf
    [137] => Informed Consent for Strabismus Surgery_24.pdf
    [138] =>    General Consent for Medical and Surgical Procedure (OS)_68.pdf
    [139] => Informed Consent for Strabismus Surgery_57.pdf
    [140] => Consent for Probing and Irrigation of Tear Duct (Nasolacrimal System) (OU)_146.pdf
    [141] => Informed Consent for Strabismus Surgery (OD)_113.pdf
    [142] => Informed Consent for Strabismus Surgery_30.pdf
    [143] => Informed Consent for Strabismus Surgery_50.pdf
    [144] => Informed Consent for Strabismus Surgery_35.pdf
    [145] =>    General Consent for Medical and Surgical Procedure_112.pdf
    [146] => Informed Consent for Strabismus Surgery_109.pdf
    [147] => Informed Consent for Strabismus Surgery_17.pdf
    [148] => Informed Consent for Strabismus Surgery (OS)_34.pdf
    [149] => Informed Consent for Strabismus Surgery_129.pdf
    [150] => Informed Consent for Strabismus Surgery (OD)_63.pdf
    [151] => Informed Consent for Strabismus Surgery_31.pdf
    [152] => Informed Consent for Strabismus Surgery_140.pdf
) */
