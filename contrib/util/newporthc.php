<?php

require __DIR__ . '/vendor/autoload.php';
// collect parameters (need to do before globals)
$_GET['site'] = $argv[1];
$ignoreAuth = 1;
require_once(__DIR__ . "/../../interface/globals.php");

sqlStatement("truncate patient_data");
sqlStatement("truncate insurance_data");


// CALL the underlying service that is used by the api
use OpenEMR\Services\PatientService;
use OpenEMR\Services\InsuranceService;



// Parse PDF file and build necessary objects.

function x12Clean($str)
{
        return trim(preg_replace('/[^A-Z0-9!"\\&\'()+,\\-.\\/;?=@ ]/', '', strtoupper($str)));
}

function x12Zip($zip)
{
        $zip = x12Clean($zip);
        // this will take out dashes and pad with trailing 9s if not 9 digits
        return preg_replace('/[^0-9]/', '', $zip);
        /* return str_pad(
            preg_replace('/[^0-9]/', '', $zip),
            9,
            9,
            STR_PAD_RIGHT
        ); */
}

//$config = new Smalot\PdfParser\Config();
//$config->setDataTmFontInfoHasToBeIncluded(true);
$parser = new \Smalot\PdfParser\Parser();
$path_to_mdf = '/tmp';
$directory_of_facesheets = $path_to_mdf . '/20230116';
$array_of_facesheets = scandir($directory_of_facesheets);
$bad_dirs = array('.', '..');

$patient_service = new PatientService();
$insurance_service = new InsuranceService();
$insurance_index = array(
    '1' => 'primary',
    '2' => 'secondary',
    '3' => 'tertiary'
);

foreach ($array_of_facesheets as $key => $facesheet) {
    if (in_array($facesheet, $bad_dirs)) {
        continue;
    }
    $filename = $directory_of_facesheets . '/' . $facesheet;
    //echo $facesheet . "\n";
    echo $filename . "\n";
    $pdf = $parser->parseFile($directory_of_facesheets . '/' . $facesheet);
    $facesheet_text = $pdf->getPages()[0]->getText();
    $person = getPerson($facesheet_text);
    //var_dump($person);
    //exit;
    if (!checkPubPid($person['pubpid'])) {
        $person_insert = $patient_service->insert($person);
        $person_data = $person_insert->getData();
        var_dump($person_data[0]);
        $pid = $person_data[0]['pid'];
        //echo "$uuid \n";
        for ($i=1; $i < 4; $i++) {
            if (!(empty($person['insurance'][$insurance_index[$i]]))) {
                if (
                    $i == 3
                    && $person['insurance'][$insurance_index[$i]]['provider'] == 2
                    ) {
                    $type = 'primary';
                    $date = '2022-01-01';
                } else {
                    $type =  $insurance_index[$i];
                    $date = '2022-10-01';
                }
                $insurance_insert = $insurance_service->insert
                (
                    $pid,
                    $type,
                    array(
                        'provider' => $person['insurance'][$insurance_index[$i]]['provider'],
                        'plan_name' => '',
                        'policy_number' => $person['insurance'][$insurance_index[$i]]['policy_number'],
                        'group_number' => '',
                        'subscriber_lname' => $person['lname'],
                        'subscriber_mname' => $person['mname'],
                        'subscriber_fname' => $person['fname'],
                        'subscriber_relationship' => 'self',
                        'subscriber_ss' => '',
                        'subscriber_DOB' => $person['DOB'],
                        'subscriber_street' => $person['street'],
                        'subscriber_postal_code' => $person['postal_code'],
                        'subscriber_city' => $person['city'],
                        'subscriber_state' => $person['state'],
                        'subscriber_country' => '',
                        'subscriber_phone' => '',
                        'subscriber_employer' => '',
                        'subscriber_employer_street' => '',
                        'subscriber_employer_postal_code' => '',
                        'subscriber_employer_state' => '',
                        'subscriber_employer_country' => '',
                        'subscriber_employer_city' => '',
                        'copay' => '',
                        'date' => $date,
                        'subscriber_sex' => $person['sex'],
                        'accept_assignment' => 'TRUE',
                        'policy_type' => 'FALSE'
                    )
                );
            }
        }
    } else {
        echo "person with mrn " . $person['pubpid'] . " already exists \n";
    }
}

function getPerson($text)
{
    global $arr;
    global $key;
    global $offset;
    global $supplement;
    $supplement = false;
    if (stripos($text, 'Supplement') !== false) {
        $supplement = true;
    }
    $arr = explode("\n", $text);
    //$ptName = getName($arr['5']);

    foreach ($arr as $key => $value) {
        //echo $value . "\n";
        if (trim($key) == 'ADMISSION RECORD') {
            $line_no = 0;
        } else {
            $line_no = $key;
        }
        if (trim($prior_key ?? '') == 'Resident Name') {
            //echo $line_no . "\n";
            $mrn = getMRN($value);
            $data = array();
            $name = getName($value);
            $offset = 1;
            $address = getPhoneAndAddress($arr[$key + $offset]);
            $offset = 2;
            if (checkTextForDOB($arr[$key + $offset])) {
                //echo $arr[$key + $offset] . "\n";
                $dob = getDOB($arr[$key + $offset]);
                $sex = getSex($arr[$key + $offset]);
            } elseif (checkTextForDOB($arr[$key + $offset + 1])) {
                $offset++;
                //echo $arr[$key + $offset] . "\n";
                $dob = getDOB($arr[$key + $offset]);
                $sex = getSex($arr[$key + $offset]);
            } else {
                $offset = $offset + 2;
                //echo $arr[$key + $offset] . "\n";
                $dob = getDOB($arr[$key + $offset]);
                $sex = getSex($arr[$key + $offset]);
            }

            $offset++;
            $ins = getInsurance();
            /* if (empty($ins['insurance']['secondary']['provider'])) {
                var_dump($ins);
            } */
            /* if (empty($ins)) {
                $offset++;
                getInsurance();
            } */

            $data = array_merge(['pubpid' => $mrn], $name, $address, $dob, $sex, $ins);
            //var_dump($data);
        }
        $prior_key = substr($value, 0, 14);
    }
    return($data);
}

function getMRN($text)
{
    $lineArr = explode(",", $text);
    $parts =  preg_split('/\s+/', $lineArr[1]);
    foreach ($parts as $part) {
        if (
            is_numeric($part)
            && strlen($part) == 4
        ) {
            return trim($part);
        }
    }
}

function getName($text)
{
    $lineArr = explode(",", $text);
    $parts = preg_split('/\s+/', $lineArr[0]);
    //var_dump($parts);
    $count = count($parts);
    //echo "count is $count \n";
    if ($count == 3) {
        $last_name = $parts[2];
    } elseif ($count == 4) {
        $last_name = $parts[3];
    } elseif ($count == 5) {
        if (strpos($parts[4], '.')) {
            $last_name = $parts[3];
        } else {
            $last_name = $parts[4];
        }
    }

    $parts = preg_split('/\s+/', $lineArr[1]);
    //var_dump($parts);
    //echo $lineArr[1] . "\n";
    $first_name = null;
    foreach ($parts as $key => $value) {
        if (!empty($value)) {
            //echo $key . " " . $value . "\n";
            if (empty($first_name)) {
                $first_name = $value;
            } else {
                $mid_init = $value;
            }
            if (strpos($parts[$key + 1], '/') !== false) {
               break;
            }
        }
    }
    return array(
        'fname' => x12Clean($first_name),
        'mname' => x12Clean($mid_init ?? ''),
        'lname' => x12Clean($last_name)
    );
}

function getPhoneAndAddress($text)
{
    $address_position = strpos($text, 'Address') + 7;
    $sex_position =  strpos($text, 'Sex');
    if (!empty($address_position)) {
        $address_text = trim(substr($text, $address_position, ($sex_position - $address_position)));
        //echo $address_text . "\n";
        $parts = explode(',', $address_text);
        //echo count($parts) . "\n";
        if (count($parts) == '4') {
            $line1 = x12Clean($parts[0]);
            $line2 = '';
            $city = x12Clean($parts[1]);
            $state = x12Clean($parts[2]);
            $zip = x12Zip($parts[3]);
        } elseif (count($parts) == '5') {
            $line1 = x12Clean($parts[0]);
            $line2 = x12Clean($parts[1]);
            $city = x12Clean($parts[2]);
            $state = x12Clean($parts[3]);
            $zip = x12Zip($parts[4]);
        }
        if ($address_position != 7) {
            //echo $line1 . " line1 " . $line2 . " line2 " . $city . " city " . $state . " st " . $zip . " zip\n";
        } else {
            //echo "resident is at 148 Prouty \n";
            $line1 = "148 PROUTY DR";
            $line2 = '';
            $city = 'NEWPORT';
            $state = 'VT';
            $zip = '05855';
        }
    }
    $parts = preg_split('/\s+/', $text);
    //var_dump($parts);
    if (strpos($parts[0], '(') !== false) {
        //echo "pt phone is " . $parts[0] . $parts[1] . "\n";
        $phone = $parts[0] . $parts[1];
    } else {
        $phone = '';
    }
    return array(
        'street' => x12Clean($line1),
        'street_line_2' => x12Clean($line2),
        'city' => x12Clean($city),
        'state' => x12Clean($state),
        'postal_code' => x12Zip($zip),
        'phone_home' => x12Clean($phone)
    );
}

function checkTextForDOB($text)
{
    if (strpos($text, 'English') !== false) {
        return true;
    }

    return false;
}

function getDOB($text)
{
    //$english_position = strpos($text, 'English') + 7;
    //$start = 32;
    //echo $text . "\n";
    $parts = preg_split('/\t+/', $text);
    //var_dump($parts);
    foreach ($parts as $part) {
        if (
            strpos($part, '/') !== false
            && strlen($part) == 10
            ) {
                $raw_date = trim($part) . "\n";
                $YYYY = substr($raw_date, 6, 4);
                $MM = substr($raw_date, 0, 2);
                $DD = substr($raw_date, 3, 2);
                return array('DOB' => $YYYY . '-' . $MM . '-' . $DD);
        } else {
            //echo $part . "\n";
        }
    }
    return false;
}

function getSex($text)
{
    //$english_position = strpos($text, 'English') + 7;
    //$start = 32;
    //echo $text . "\n";
    $parts = preg_split('/\t+/', $text);
    $sex = 'Female';
    if (trim($parts[4]) == 'M') {
        $sex = 'Male';
    }
    return array(
        'sex' => $sex
    );
}

function checkTextForMedicare($text)
{
    if (strpos($text, 'Medicare (HIC) #') !== false) {
        return true;
    }

    return false;
}

function checkTextForBeneficiary($text) {
    if (strpos($text, 'Beneficiary') !== false) {
        return true;
    }

    return false;
}

function getMBI($text)
{
    global $filename;
    if (strlen(trim($text)) == 11) {
        return x12Clean($text);
        //echo trim($text) . "\n";
    } else {
        //echo "bad mbi " . $filename . "\n";
        return false;
    }
}

function checkTextForMedicaid($text)
{
    if (
        strpos($text, 'Medicaid #') !== false
        && !strpos($text, 'Social Security #') !== false
        ) {
        return true;
    }

    return false;
}

function getPolicyNo($text)
{
    $parts = preg_split('/\t+/', $text);
    return x12Clean($parts[0]);
}

function checkTextForSSN($text)
{
    if (strpos($text, 'Social Security #') !== false) {
        return true;
    }

    return false;
}

function getSSN($text)
{
    return x12Clean(str_replace('-', '', substr($text, 0, 11)));
}

function checkTextForAdmitted($text)
{
    if (strpos($text, 'Admitted From') !== false) {
        return true;
    }

    return false;
}

function checkTextForInsuranceName($text)
{
    if (
        strpos($text, 'Insurance Name') !== false
        && strpos($text, 'Insurance Policy') === false
        ) {
        return true;
    }

    return false;
}

function getInsuranceCode($text)
{
    if ($text == 'BCBS') {
        $code = 4932;
        $med_adv = true;
    }
    if ($text == 'BCBSFEDERAL') {
        $code = 15;
    }
    if ($text == 'AARP') {
        $code = 12;
    }
    if ($text == 'BANKERSLIFE') {
        $code = 264;
    }
    if ($text == 'UNITEDHEALTHCARE') {
        $code = 58;
    }
    if ($text == 'CHAMPVA') {
        $code = 7667;
    }
    if ($text == 'BLUECROSSBLUESHIELD') {
        $code = 15;
    }
    if ($text == 'WELLCARE') {
        $code = 4762;
        $med_adv = true;
    }

    return array(
        'code' => $code ?? null,
        'med_adv' => $med_adv ?? null
    );
}

function checkTextForMBI($text)
{
    if (strpos($text, 'Medicare Beneficiary ID') !== false) {
        return true;
    }

    return false;
}

function getInsurance()
{
    global $arr;
    global $key;
    global $offset;
    global $supplement;
    $prins = null;
    $pripol = null;
    $secins = null;
    $secpol = null;
    $trins = null;
    $tripol = null;
    $ins_code = null;
    $ins_pol = null;
    //echo $arr[$key + $offset] . "\n";
    if (checkTextForAdmitted($arr[$key + $offset])) {
        $offset++;
    }

    if (
        checkTextForMedicare($arr[$key + $offset]) 
        && !checkTextForMedicaid($arr[$key + $offset])
        ) {
        $temp_policy = getPolicyNo($arr[$key + $offset +1]);
        if (
            checkTextForBeneficiary($arr[$key + $offset + 2])
            && (getMBI($arr[$key + $offset + 3]))
        ) {
            $offset++;
            $offset++;
            $offset++;
            $prins = 2;
            $pripol = $temp_policy;
            if (checkTextForMedicaid($arr[$key + $offset + 1])) {
                $offset++;
                $prins = 2;
                $pripol = $temp_policy;
                $secins = 7;
                $offset++;
                $secpol = getPolicyNo($arr[$key + $offset]);
            }
        } elseif (checkTextForMedicaid($arr[$key + $offset + 2])) {
            $offset++;
            $offset++;
            $prins = 2;
            $pripol = $temp_policy;
            $secins = 7;
            $offset++;
            $secpol = getPolicyNo($arr[$key + $offset]);
        } 
    } elseif (
        checkTextForMedicare($arr[$key + $offset])
        && checkTextForMedicaid($arr[$key + $offset])
    ) {
        $offset++;
        $prins = 7;
        $pripol = getPolicyNo($arr[$key + $offset]);
    }

    $offset++;
    if (checkTextForSSN($arr[$key + $offset])) {
        $offset++;
        $ssn = getSSN($arr[$key + $offset]);
        if (checkTextForInsuranceName($arr[$key + $offset])) {
            $offset++;
            $ins_code = getInsuranceCode(x12Clean($arr[$key + $offset]));
            $offset++;
            $offset++;
            $ins_pol = getPolicyNo($arr[$key + $offset]);
        } 
    } else {
        // for private pay the ssn can be on the same line with insurance name
        $ssn = getSSN($arr[$key + $offset]);
        if (checkTextForInsuranceName($arr[$key + $offset])) {
            $offset++;
            $ins_code = getInsuranceCode(x12Clean($arr[$key + $offset]));
            $offset++;
            $offset++;
            $ins_pol = getPolicyNo($arr[$key + $offset]);
        }
    }

    if ($supplement) {
        //echo "supplement is true \n";
        if (!empty($ins_code['code'])) {
            $temp_ins = $secins;
            $temp_pol = $secpol;
            $secins = $ins_code['code'];
            $secpol = $ins_pol;
            $trins = $temp_ins;
            $tripol = $temp_pol;
        }
    } elseif (!$supplement) {
        //echo "supplement false and ins_code is " . $ins_code['code'] ?? '' . "\n";
        if ($ins_code) {
            if (
                $ins_code['med_adv']
                && $prins == 2
            ) {
                $trins = $prins;
                $tripol = $pripol;
                $prins = $ins_code['code'];
                $pripol = $ins_pol;
            } elseif ($ins_code['code']) {
                if (empty($secins)) {
                    //echo "set 2ndary to " . $ins_code['code'] . "\n";
                    $secins = $ins_code['code'];
                    $secpol = $ins_pol;
                }
            }
        }
    }
    return array(
        'ss' => $ssn,
        'insurance' => array(
            'primary' => array(
                'provider' => $prins,
                'policy_number' => $pripol
            ),
            'secondary' => array(
                'provider' => $secins,
                'policy_number' => $secpol
            ),
            'tertiary' => array(
                'provider' => $trins,
                'policy_number' => $tripol
            )
        )
    );
}

function checkPubPid($pubpid) {
    $sql = sqlQuery("SELECT * FROM patient_data WHERE pubpid=?", array($pubpid));
    if (!empty($sql['id'])) {
        return true;
    }
    return false;
}