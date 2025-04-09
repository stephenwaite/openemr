<?php

/**
 * ecg command line import
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use PhpOffice\PhpSpreadsheet\IOFactory;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;


if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default 83\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

$inputFileName = $argv[2];
$inputFileType = 'Xlsx';
/* $fp = fopen('/tmp/charges.csv', 'w');
$fpMissing = fopen('/tmp/missing_charges.csv', 'w'); */

/* $flat_garfile = $argv[3] ?? '';
loadGarfile($flat_garfile);
exit; */

/* $flat_charcur = $argv[3] ?? '';
loadCharcur($flat_charcur);
exit; */

$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$ptService = new PatientService();
$insService = new InsuranceService();
foreach ($worksheet->getRowIterator() as $row) {
    //var_dump($value);
    $cellIterator = $row->getCellIterator();
    foreach ($cellIterator as $key => $cell) {
        $cellValue = $cell->getFormattedValue();
        switch ($key) {
            case 'F':
                $name = explode(',', $cellValue);
                //var_dump($name);
                $lname = trim($name[0]);
                $fname = trim($name[1]);
                break;

            case 'G':
                $dob_raw = $cellValue;
                $dobDateTime = date_create_from_format('m/d/Y', $dob_raw);
                $dob = $dobDateTime->format('Y-m-d');
                //var_dump($dob);
                break;
            case 'I':
                $code = $cellValue;
                break;
            case 'J':
                $dx = $cellValue;
                break;
            case 'K':
                    $dos_raw = $cellValue;
                    $dosDateTime = date_create_from_format('m/d/Y', $dos_raw);
                    $dos = $dosDateTime->format('Y-m-d');
                    //var_dump($dob);
                break;
        }
    }

    $class_code = "IMP";
    $pos_code = 21;
    $pc_catid = 17;
    $facility_id = 5;
    $billing_facility = 3;
    $onset_date = $dos;
    $user = null;
    $groupname = 'Default';
    if (in_array($code, ['99203', '99204', '99205', '99213', '99214', '99215'])) {
        $class_code = "AMB";
        $pos_code = 22;
        $pc_catid = 18;
        $facility_id = 4;
        $onset_date = null;
    }


    if (!empty($code)) {
        $codes_sql = sqlQuery("SELECT `codes`.`code_text`, `prices`.`pr_price` as 'fee' FROM `codes` LEFT JOIN `prices` ON `prices`.`pr_id` = `codes`.`id` WHERE `code` = ?", [$code]);
        $fee = $codes_sql['fee'];
        $code_text = $codes_sql['code_text'];
    } else {
        echo $code . " dx code is empty \n";
    }


    if (!empty($dx)) {
        if (stripos($dx, '.') === false) {
            $dx = $dx . ".";
        }
        $dx_text_sql = sqlQuery("SELECT `short_desc` FROM `icd10_dx_order_code` WHERE `formatted_dx_code` = ?", [$dx]);
        $dx_text = $dx_text_sql['short_desc'];
    }

    $pidQuery = "SELECT * FROM `patient_data` WHERE `fname` LIKE ? AND `lname` LIKE ? AND `DOB` = ?";

    $fnamePiece = substr($fname, 0, 3);
    $lnamePiece = substr($lname, 0, 3);

    $pidExists = sqlQuery($pidQuery, ['%' . $fnamePiece . '%', '%' . $lnamePiece . '%', $dob]);
    $ptService = new PatientService();

    if (!empty($pidExists) && $pidExists['pid']) {
        // pt exists, check for encounter
        $encQuery = "SELECT `pid`, `encounter` FROM `form_encounter` WHERE `date` LIKE ? AND `pid` = ?";
        //echo $dos . " " . $pidExists['pid'] . "\n";
        $encExists = sqlQuery($encQuery, [$dos . "%", $pidExists['pid']]);
        if ($encExists) {
            //var_dump($encExists);
        } else {
            //echo $pidExists['uuid'] . " " . $dos . "\n";
            //$puuid = UuidRegistry::uuidToString($pidExists['uuid']);
            /* $enc = (new EncounterService)->insertEncounter(
                $puuid,
                [
                    'date' => $dos,
                    'onset_date' => $onset_date,
                    'class_code' => $class_code,
                    'pos_code' => $pos_code,
                    'pc_catid' => $pc_catid,
                    'provider_id' => 5000,
                    'referring_provider_id' => 6084,
                    'facility_id' => $facility_id,
                    'billing_facility' => $billing_facility,
                    'user' => $user,
                    'group' => $groupname
                ],
            );
            $encData = $enc->getData()[0];
            //var_dump($encData);
            echo "new encounter created for pid-enc " . $encData['pid'] . "-" . $encData['encounter'] . "\n";
            $addBilling = BillingUtilities::addBilling(
                $encData['eid'],
                'ICD10',
                $dx ?? '',
                $dx_text ?? '',
                $encData['pid'],
                1,
                5000,
            );

            $addBilling = BillingUtilities::addBilling(
                $encData['eid'],
                'CPT4',
                $code,
                $code_text,
                $encData['pid'],
                1,
                5000,
                '',
                '',
                $fee,
                '',
                'ICD10|' . $dx ?? ''
            ); */
            //exit;
        }
    } else {
        $cobolDob = str_replace('-', '', $dob);
        $missingPtsKey = $fnamePiece . $lnamePiece . $cobolDob;
        $missingPts[$missingPtsKey] = str_replace(['.'], '', $dob . $dos . $code . $dx);
        $garfileQuery = "SELECT * FROM `cmsvt_garfile` WHERE `garname` LIKE ? AND `dob` = ?";
        $garnameBind = $lnamePiece . '%';
        //echo "garnameBind $garnameBind dob $cobolDob \n";
        $garMatchRes = sqlStatement($garfileQuery, [$garnameBind, $cobolDob]);
        $matchFlag = false;
        while ($row = sqlFetchArray($garMatchRes)) {
            $garSearch = sqlQuery("SELECT * FROM `cmsvt_charcur` WHERE `garno` = ?", [$row['garno']]);
            if (!empty($garSearch)) {
                //echo "gotta match " . $row['garno']  . "\n";
                //var_dump($row);
                // add patient
                // add insurance
                // add encounter
                $matchFlag = true;
                $savedRow = $row;
                continue;
            } else {
                //echo "garno " . $row['garno'] . " not in charcur \n";
            }
        }
        if (empty($matchFlag)) {
            //echo "couldn't find a match for " . $missingPtsKey . "\n";
        } else {
            var_dump($savedRow);
            addPatient($savedRow, $ptService, $insService);
        }
    }
}

//var_dump($missingPts);
//echo count($missingPts) . "\n";



exit;
$savedGarno = '';
$garnosWithCharges2024 = [];
// read the charges file and then grab a record from garfile
if ($charcurFile = fopen($argv[1], "r")) {
    while (($charcurLine = fgets($charcurFile)) !== false) {
        $garno = substr($charcurLine, 0, 8);
        //$code = substr($line, 37, 5);
        //$ins = substr($line, 29, 3);
      //echo $garno . "\n";
      //echo $code . "\n";
        if (empty($garnosWithCharges2024[$garno])) {
            $garnosWithCharges2024[$garno] = $garno;
        }

        $savedGarno = $garno;
    }
}

//var_dump($garnosWithCharges2024);
// echo count($garnosWithCharges2024) . "\n";
exit;

foreach ($garnosWithCharges2024 as $key => $value) {
    if ($garfile = fopen($argv[2], "r")) {
        while (($garfileLine = fgets($garfile)) !== false) {
            $garno = substr($charcurLine, 0, 8);
            //$code = substr($line, 37, 5);
            //$ins = substr($line, 29, 3);
          //echo $garno . "\n";
          //echo $code . "\n";
            if (empty($garnosWithCharges2024[$garno])) {
                $garnosWithCharges2024[$garno] = $garno;
            }

            $savedGarno = $garno;
        }
    }
}

function addPatient($row, $ptService, $insService)
{
    $ptInsertRow = [];
    $ptFullName = $row['garname'];
    $nameArray = explode(';', $ptFullName);
    $ptInsertRow['lname'] = $nameArray[0];
    $fnameArray = explode(' ', $nameArray[1]);
    $fname = (strlen($fnameArray[1] ?? '') > 1) ? ($fnameArray[0] . " " . $fnameArray[1]) : $fnameArray[0];
    $ptInsertRow['fname'] = $fname;
    if (($nameArray[2] ?? '')) {
        $mname = $nameArray[2];
    } elseif (strlen($fnameArray[1] ?? '') > 1) {
        $mname = $fnameArray[1];
    }
    $ptInsertRow['mname'] = $mname ?? '';
    $ptInsertRow['street'] = $row['billadd'];
    $ptInsertRow['street_line_2'] = $row['street'];
    $ptInsertRow['city'] = $row['city'];
    $ptInsertRow['state'] = $row['state'];
    $ptInsertRow['postal_code'] = $row['zip'];
    $sex = ($row['sex'] == 'M') ? 'Male' : 'Female';
    $ptInsertRow['sex'] = $sex;
    $ptInsertRow['DOB'] = date("Y-m-d", strtotime($row['dob']));
    $ptInsertRow['pubpid'] = ltrim($row['acct'], '0');
    $newPt = $ptService->insert($ptInsertRow);
    // insurance add
    $ptData = $newPt->getData();
    //var_dump($ptData);
    $insData['pid'] = ($newPt->getData())[0]['pid'];
    $insData['subscriber_lname'] = $ptInsertRow['lname'];
    $insData['subscriber_fname'] = $fname;
    $insData['subscriber_mname'] = $mname ?? '';
    $insData['subscriber_relationship'] = 'self';
    $insData['subscriber_DOB'] = $ptInsertRow['DOB'];
    $insData['subscriber_street'] = $ptInsertRow['street'];
    $insData['subscriber_postal_code'] = $ptInsertRow['postal_code'];
    $insData['subscriber_city'] = $ptInsertRow['city'];
    $insData['subscriber_state'] = $ptInsertRow['state'];
    $insData['subscriber_country'] = 'USA';
    $insData['subscriber_street_line_2'] = $ptInsertRow['street_line_2'];
    $insData['date'] = '2024-01-01';
    $insData['date_end'] = null;
    $insData['subscriber_sex'] = $sex;
    $insData['accept_assignment'] = 'TRUE';
    $insData['provider'] = $row['prins'];
    if ($row['prins'] != '001') {
        $insData['type'] = 'primary';
        $insData['policy_number'] = $row['pripol'];
        $insData['group_number'] = $row['prgroup'] ?? '';
        var_dump($insService->insert($insData));
    }

    if ($row['seins'] != '001') {
        $insData['provider'] = $row['seins'];
        $insData['type'] = 'secondary';
        $insData['policy_number'] = $row['secpol'];
        $insData['group_number'] = $row['segroup'] ?? '';
        $insService->insert($insData);
    }

}


/* function loadGarfile($filename) {
    if ($file = fopen($filename, 'r')) {
        //$garquery = '';
        // Settings to drastically speed up import with InnoDB
        //sqlStatementNoLog("SET autocommit=0");
        //sqlStatementNoLog("START TRANSACTION");
        while (!feof($file)) {
            $line = fgets($file);
            //echo $line;
            $line = str_replace([',', '"', '\\'], ' ', $line);
            $garno = substr($line, 0, 8);
            $garname = trim(substr($line, 8, 24));
            $billadd = trim(substr($line, 32, 22));
            $street = trim(substr($line, 54, 22));
            $city = trim(substr($line, 76, 18));
            $state = substr($line, 94, 2);
            $zip = trim(substr($line, 96, 9));
            // collt 1 phone is 10
            $sex = substr($line, 116, 1);
            // relate 1 mstat 1
            $dob = substr($line, 119, 8);
            // dunning 1 acctstat 1 prmplr 4
            $prins = substr($line, 133, 3);
            // prassign 1 proffice 4
            $prgroup = trim(substr($line, 141, 10));
            $pripol = trim(substr($line, 151, 16));
            // prname 24 prrelate 1 addrcode 4
            $seins = substr($line, 196, 3);
            // seassign 1 trinisid 1 trins 3
            $secgroup = trim(substr($line, 204, 10));
            $secpol = trim(substr($line, 214, 16));
            // sename 35 serelate 1 inspend 7 lastbill 8, assignm 1
            // private 1 billcycle 1 delete 1 filler 3
            // 60
            $acct = trim(substr($line, 277, 8));

            $gar_array = [
                $garno,
                $garname,
                $billadd,
                $street,
                $city,
                $state,
                $zip,
                $sex,
                $dob,
                $prins,
                $prgroup,
                $pripol,
                $seins,
                $secgroup,
                $secpol,
                $acct
            ];

            $fields_arr = [
                "garno",
                "garname",
                "billadd",
                "street",
                "city",
                "state",
                "zip",
                "sex",
                "dob",
                "prins",
                "prgroup",
                "pripol",
                "seins",
                "segroup",
                "secpol",
                "acct"
            ];
            $fields = implode ('`,`', $fields_arr);
            $values = implode('","', $gar_array);
            $garquery = "INSERT INTO `cmsvt_garfile` " .
            " (`" . $fields . "`)" .
            " VALUES (\"" . $values . "\")";
            //echo $garquery . "\n";
            sqlStatementNoLog($garquery);
            //exit;
        }
        //sqlStatementNoLog("COMMIT");
        //sqlStatementNoLog("SET autocommit=1");
    }
} */

/* CREATE TABLE `cmsvt_charcur`
    (
        `garno` VARCHAR(8)
        , `charno` VARCHAR(3)
        ,`restofline` VARCHAR(149)
        , PRIMARY KEY (`garno`, `charno`)
        , KEY (`garno`)
    ) ENGINE=InnoDB COMMENT="charcur"; */

/* function loadCharcur($filename) {
    if ($file = fopen($filename, 'r')) {
        $charquery = '';
        // Settings to drastically speed up import with InnoDB
        sqlStatementNoLog("SET autocommit=0");
        sqlStatementNoLog("START TRANSACTION");
        while (!feof($file)) {
            $line = fgets($file);
            //echo $line;
            $garno = substr($line, 0, 8);
            $charno = substr($line, 8, 3);
            $restOfLine = substr($line, 11, 149);

            $charcur_array = [
                $garno,
                $charno,
                $restOfLine
            ];

            $fields_arr = [
                "garno",
                "charno",
                "restofline"
            ];
            $fields = implode ('`,`', $fields_arr);
            $values = implode('","', $charcur_array);
            $charquery = "INSERT INTO `cmsvt_charcur` " .
            " (`" . $fields . "`)" .
            " VALUES (\"" . $values . "\")";
            //echo $charquery . "\n";
            sqlStatementNoLog($charquery);
            //exit;
        }
        sqlStatementNoLog("COMMIT");
        sqlStatementNoLog("SET autocommit=1");
    } else {
        echo "file isn't open \n";
    }
} */
