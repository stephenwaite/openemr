<?php

/**
 * grab guardian info from emer for pts under 18
 * and update other missing demos from orig import
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_FIX_GUARD')) {
    die('Set OPENEMR_ENABLE_FIX_GUARD=1 environment variable to enable this script');
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
$refProvReader = Reader::createFromPath('/tmp/specs-refprov.csv');
$refProvReader->setDelimiter(",");
$refProvReader->SetHeaderOffset(0);
$refProvHeader = $refProvReader->getHeader();
$refProvRecords = $refProvReader->getRecords($refProvHeader);

$refProvArray = [];
foreach ($refProvRecords as $refProvRecord) {
    $key = $refProvRecord['Id'];
    $refProvArray[$key] = $refProvRecord;
}

//print_r($refProvArray);
//exit;

// setup a csv file with a header
$filename = 'SUN002-NextechPatientDemographics-20250922071517.csv';
$filepath = $GLOBALS['temporary_files_dir'];
$fullPathToFile = $filepath . DIRECTORY_SEPARATOR . $filename;
$reader = Reader::createFromPath($fullPathToFile);
$reader->setDelimiter(",");

$start_record = $argv[2];
$reader->setHeaderOffset($start_record);
$header = $reader->getHeader();

$records = $reader->getRecords($header);

foreach ($records as $record) {
    //var_dump($record);
    //exit;
    $pubpid = trim($record['AccountNumber']);

    $eighteenYo = strtotime('2007-10-01');
    $recordYo = strtotime(trim($record['Birthdate']));
    if ($recordYo > $eighteenYo) {
        $query = sqlQuery("SELECT * FROM `patient_data` WHERE `pubpid` = ?", [$pubpid]);
        $guardiansName = trim($record['EmergencyContactFirstName'] . " " . $record['EmergencyContactLastName']);
        $guardiansPhone = trim($record['EmergencyContactHomePhone']);
        if (
            (!empty($guardiansName) || !empty($guardiansPhone))
            && empty($query['guardiansname'])
        ) {
            echo "have to move emer contact to guardian for pt " . $query['fname'] . " " . $query['lname'] . " " . $query['DOB'] . "\n";
            echo $query['contact_relationship'] . " " . $query['phone_contact'] . "\n";
            echo "address " . trim($record['Address1'] . " " . $record['Address2']) . " city " . $record['City'] . " state " . $record['State'] . " " . $record['Zip'] . "\n";
            $updateGuardian = sqlStatement(
                "UPDATE `patient_data` SET `guardiansname` = ?,
                `guardianrelationship` = ?,
                `guardianaddress` = ?,
                `guardiancity` = ?,
                `guardianstate` = ?,
                `guardianpostalcode` = ?,
                `guardianphone` = ?,
                `guardianemail` = ?
                WHERE `pubpid` = ?",
                [
                    $guardiansName,
                    'mother',
                    $query['street'] . " " . $query['street_line_2'],
                    $query['city'],
                    $query['state'],
                    $query['postal_code'],
                    $guardiansPhone,
                    $query['email'],
                    $pubpid
                ]
            );
        }
    }

    $csvRecords[] = $record;
    // marital status
    $maritalStatus = trim($record['MaritalStatus']);
    if (!empty($maritalStatus)) {
        $maritalText = match ($maritalStatus) {
            'Single' => 'single',
            'Married' => 'married',
            default => '',
        };
        echo "updating marital status for " . $pubpid . " to " . $maritalText . "\n";
        $updateMaritalStatus = sqlStatement("UPDATE `patient_data` SET `status` = ? WHERE `pubpid` = ?", [$maritalText, $pubpid]);
    }


    // race
    // SELECT `race` FROM `patient_data` WHERE `pubpid` = '10120'
    $race = getRace(trim($record['Race']));
    if (!empty($race)) {
        echo "updating race for " . $pubpid . " to " . $race . "\n";
        $updateRace = sqlStatement("UPDATE `patient_data` SET `race` = ? WHERE `pubpid` = ?", [$race, $pubpid]);
    }

    // ethnicity code
    $ethnicityCode = substr(trim($record['EthnicityCd']), 0, 2);
    if (!empty($ethnicityCode)) {
        $ethnicityText = match ($ethnicityCode) {
            'NH' => 'not_hisp_or_latin',
            'HS' => 'hispanic',
            'DE' => 'decline_to_specify',
            default => ''
        };
        echo "updating ethnicity for " . $pubpid . " to " . $ethnicityText . "\n";
        $updateEthnicity = sqlStatement("UPDATE `patient_data` SET `ethnicity` = ? WHERE `pubpid` = ?", [$ethnicityText, $pubpid]);
    }

    // referral source
    // Referral source, ['Current Patient', 'Referring provider', 'Internet search', 'friend', 'Other', 'Walk-In']
    //$uniqueReferralSource = getUniqueFieldValues($csvRecords, 'ReferralSource');
    //print_r($uniqueReferralSource);
    $referralSource = trim($record['ReferralSource']);
    if (!empty($referralSource)) {
        $referralSourceText = match ($referralSource) {
            'Hospital/Urgent Care' => 'Hospital',
            'Insurance' => 'Insurance',
            'Internet Search/Website' => 'Internet search',
            'Previous Patient' => 'Current Patient',
            'Referring Physician' => 'Referring provider',
            'Social Media' => 'Social Media',
            'Word of Mouth' => 'Word of Mouth',
            default => '',
        };
        echo "updating referral source for " . $pubpid . " to " . $referralSourceText . "\n";
        $updateReferralSource = sqlStatement("UPDATE `patient_data` SET `referral_source` = ? WHERE `pubpid` = ?", [$referralSourceText, $pubpid]);
    }

    $refProvCode = trim($record['ReferringPhysicianId']);
    if (!empty($refProvCode)) {
        if ($refProvCode == '1') {
            echo "updating refprov to Marie \n";
            $updateRefProv = sqlStatement("UPDATE `patient_data` SET `ref_providerID` = ? WHERE `pubpid` = ?", ['5', $pubpid]);
        }
        $refProv = $refProvArray[$refProvCode];
        //var_dump($refProv);
        $refProvNameF = $refProv['FirstName'];
        if ($refProvNameF == "LARISSA") {
            $refProvNameF = "LARISA";
        }
        $refProvNameL = $refProv['LastName'];
        if (!empty($refProvNameF)) {
            //print_r($refProv);
            try {
                $refProvQuery = sqlQuery("SELECT * FROM `users` WHERE `fname` LIKE '" . $refProvNameF . "%' AND `lname` LIKE '" . $refProvNameL . "%' LIMIT 1");
            } catch (\Exception $e) {
                echo "had a ref prov code " . $refProvNameF . " " . $refProvNameL . " but no match on name \n";
            }
            $refProvId = $refProvQuery['id'] ?? '';
            if (!empty($refProvId)) {
             //print_r($refProvQuery);
                //echo "updating refprov to " . $refProvId . "\n";
                $updateRefProv = sqlStatement("UPDATE `patient_data` SET `ref_providerID` = ? WHERE `pubpid` = ?", [$refProvId, $pubpid]);
            } else {
                //print_r($refProv);
                //exit;
            }
        }
    }

    $primProvCode = trim($record['PrimaryCarePhysicianId']);
    if (!empty($primProvCode)) {
        if ($primProvCode == '1') {
            echo "updating primprov to Marie \n";
            $updatePrimProv = sqlStatement("UPDATE `patient_data` SET `providerID` = ? WHERE `pubpid` = ?", ['5', $pubpid]);
        }
        $primProv = $refProvArray[$primProvCode];
        //var_dump($primProv);
        $primProvNameF = $primProv['FirstName'];
        if ($primProvNameF == "LARISSA") {
            $primProvNameF = "LARISA";
        }
        $primProvNameL = str_replace("'", "\'", $primProv['LastName']);
        if (!empty($primProvNameF)) {
            //print_r($primProv);
            try {
                $primProvQuery = sqlQuery("SELECT * FROM `users` WHERE `fname` LIKE '" . $primProvNameF . "%' AND `lname` LIKE '" . $primProvNameL . "%' LIMIT 1");
            } catch (\Exception $e) {
                echo "had a prim prov code " . $primProvNameF . " " . $primProvNameL . " but no match on name \n";
            }
            $primProvId = $primProvQuery['id'] ?? '';
            if (!empty($primProvId)) {
             //print_r($primProvQuery);
                echo "updating primprov to " . $primProvId . "\n";
                $updateprimProv = sqlStatement("UPDATE `patient_data` SET `providerID` = ? WHERE `pubpid` = ?", [$primProvId, $pubpid]);
            } else {
                echo "primProvID was empty \n";
                //print_r($primProv);
                //exit;
            }
        }
    }
}




function getRace(string $text): string
{
       $race = match ($text) {
        'White' => 'white',
        'African American, White' => 'white|black_or_afri_amer',
        'Asian' => 'Asian',
        'Asian, White' => 'white|Asian',
        'Black, White' => 'white|black_or_afri_amer',
        'American Indian' => 'amer_ind_or_alaska_native',
        'Comanche, Kickapoo, Potawatomi, Sac and Fox' => 'amer_ind_or_alaska_native',
        'African American' => 'black_or_afri_amer',
        'Japanese, White' => 'white|Asian',
        'Spanish American Indian, Black or African American, German' => 'white|Hispanic|black_or_afri_amer',
        'Potawatomi, White' => 'white|amer_ind_or_alaska_native',
        'Iowa, White' => 'white|amer_ind_or_alaska_native',
        'Black' => 'black_or_afri_amer',
        'Cherokee, Chinese, African American, English' => 'white|amer_ind_or_alaska_native',
        'Absentee Shawnee, White' => 'white|amer_ind_or_alaska_native',
        'Mexican American Indian, White' => 'white|Hispanic',
        'American Indian, White' => 'white|amer_ind_or_alaska_native',
        'Filipino' => 'Asian',
        'Black or African American' => 'black_or_afri_amer',
        'Abenaki, Cherokee, Chinese, African American, English' => 'white|amer_ind_or_alaska_native|Asian|black_or_afri_amer',
        'Mexican American Indian' => 'Hispanic',
        'Chinese' => 'Asian',
        'Prairie Band' => 'amer_ind_or_alaska_native',
        'Potawatomi' => 'amer_ind_or_alaska_native',
        'African' => 'black_or_afri_amer',
        'American Indian or Alaska Native' => 'amer_ind_or_alaska_native',
        'Spanish American Indian', 'white|amer_ind_or_alaska_native',
        'Mexican American Indian, African American' => 'Hispanic|amer_ind_or_alaska_native',
        'European, German, Italian' => 'white',
        'Black or African American, African American' => 'black_or_afri_amer',
        'Laotian' => 'Asian',
        'Italian' => 'white',
        'English' => 'white',
        'Filipino, White' => 'white|Asian',
        'Japanese' => 'Asian',
        'White, Middle Eastern or North African' => 'white|black_or_afri_amer',
        'Asian Indian' => 'Asian',
        'American Indian or Alaska Native, Black or African American, Native Hawaiian', 'amer_ind_or_alaska_native|' => 'amer_ind_or_alaska_native|native_hawai_or_pac_island|black_or_afri_amer',
        'American Indian or Alaska Native, White' => 'white|amer_ind_or_alaska_native',
        'Absentee Shawnee' => 'amer_ind_or_alaska_native',
        'Black or African American, White' => 'white|black_or_afri_amer',
        'Mexican American Indian, Navajo, White' => 'white|amer_ind_or_alaska_native|Hispanic',
        'Vietnamese' => 'Asian',
        'American Indian or Alaska Native, Black, White' => 'white|amer_ind_or_alaska_native|black_or_afri_amer',
        'Middle Eastern or North African' => 'white|black_or_afri_amer',
        '<Declined to Provide>' => 'decline_to_specify',
        'Indian Township' => 'amer_ind_or_alaska_native',
        'American Indian, African' => 'amer_ind_or_alaska_native|black_or_afri_amer',
        'American Indian or Alaska Native, Mexican American Indian' => 'amer_ind_or_alaska_native|Hispanic',
        'Black, Other Pacific Islander' => 'black_or_afri_amer|native_hawai_or_pac_island',
        'Black, Native Hawaiian or Other Pacific Islander, White' => 'white|black_or_afri_amer|native_hawai_or_pac_island',
        'Cherokee, Japanese, White' => 'white|amer_ind_or_alaska_native|Asian',
        'Choctaw, German, Irish' => 'white|amer_ind_or_alaska_native',
        'African American, African' => 'black_or_afri_amer',
        default => '',
       };

        return $race;
}

function getUniqueFieldValues($array, $field)
{
    return array_values(array_reduce($array, function ($carry, $item) use ($field) {
        $value = $item[$field] ?? null;
        if ($value !== null && !in_array($value, $carry)) {
            $carry[] = $value;
        }
        return $carry;
    }, []));
}


//$uniqueEthnicityCd = getUniqueFieldValues($csvRecords, 'LanguageCd');
//print_r($uniqueEthnicityCd);
/*
print_r($uniqueMaritalStatus);
    [0] =>
    [1] => Single
    [2] => Other
    [3] => Married

uniqueRace
    Array
(
    [0] =>
    [1] => White
    [2] => African American, White
    [3] => Asian
    [4] => Asian, White
    [5] => Black, White
    [6] => American Indian
    [7] => Comanche, Kickapoo, Potawatomi, Sac and Fox
    [8] => African American
    [9] => Japanese, White
    [10] => Spanish American Indian, Black or African American, German
    [11] => Potawatomi, White
    [12] => Iowa, White
    [13] => Black
    [14] => Cherokee, Chinese, African American, English
    [15] => Absentee Shawnee, White
    [16] => Mexican American Indian, White
    [17] => American Indian, White
    [18] => Filipino
    [19] => Black or African American
    [20] => Abenaki, Cherokee, Chinese, African American, English
    [21] => Mexican American Indian
    [22] => Chinese
    [23] => Prairie Band
    [24] => Potawatomi
    [25] => African
    [26] => American Indian or Alaska Native
    [27] => Spanish American Indian
    [28] => Mexican American Indian, African American
    [29] => European, German, Italian
    [30] => Black or African American, African American
    [31] => Laotian
    [32] => Italian
    [33] => English
    [34] => Filipino, White
    [35] => Japanese
    [36] => White, Middle Eastern or North African
    [37] => Asian Indian
    [38] => American Indian or Alaska Native, Black or African American, Native Hawaiian
    [39] => American Indian or Alaska Native, White
    [40] => Absentee Shawnee
    [41] => Black or African American, White
    [42] => Mexican American Indian, Navajo, White
    [43] => Irish
    [44] => Vietnamese
    [45] => American Indian or Alaska Native, Black, White
    [46] => Middle Eastern or North African
    [47] => <Declined to Provide>
    [48] => Indian Township
    [49] => American Indian, African
    [50] => American Indian or Alaska Native, Mexican American Indian
    [51] => Black, Other Pacific Islander
    [52] => Black, Native Hawaiian or Other Pacific Islander, White
    [53] => Cherokee, Japanese, White
    [54] => Choctaw, German, Irish
    [55] => Irish, Scottish
    [56] => African American, African

    Array ethnicity code
(
    [0] =>
    [1] => NH
    [2] => HS
    [3] => HS11
    [4] => DE
    [5] => HS17
    [6] => HS13
    [7] => HS39
    [8] => HS22
    [9] => HS12
    [10] => HS10
    [11] => HS14
    [12] => HS40
    [13] => HS1
    [14] => HS27
    [15] => HS33
    [16] => HS16
    [17] => HS30
    [18] => HS38
)
    Array language cd
(
    [0] =>
    [1] => eng
    [2] => spa
    [3] => vie
    [4] => otr
    [5] => afr
)

*/
