<?php
// collect parameters (need to do before globals)
$_GET['site'] = $argv[1];
$ignoreAuth = 1;
require_once(__DIR__ . "/../../interface/globals.php");

use OpenEMR\Services\FacilityService;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;

$insurance_service = new InsuranceService();
$facility_service = new FacilityService();

function x12Clean($str)
{
    return trim(preg_replace('/[^A-Z0-9!"\\&\'()+,\\-.\\/;?=@ ]/', '', strtoupper($str ?? '')));
}

function x12Zip($zip)
{
        $zip = x12Clean($zip);
        // this will take out dashes and pad with trailing 9s if not 9 digits
        return preg_replace('/[^0-9]/', '', $zip);
        
}

$path_to_mdf = '/tmp';

$filename = '/tmp/' . $argv[2];
$new_facesheet = false;
$facesheet_cntr = 0;
if ($file = fopen($filename, "r")) {
    while(!feof($file)) {
        $textperline = fgets($file);
        if (
            strpos($textperline, 'RESIDENT PROFILE') !== false
        ) {
            if ($facesheet_cntr == 0) {
                $new_facesheet = false;
                $facesheet_cntr++;
            } else {
                $new_facesheet = true;
            }
        } else {
            $new_facesheet = false;
        }
        if (
            !$new_facesheet
        ) {
            if (strpos($textperline, 'FACILITY:') !== false) {
                if (stripos($textperline, 'MAPLE LANE') !== false) {
                    $facility_id = 7;
                } elseif (stripos($textperline, 'PINES')) {
                    $facility_id = 5;
                } elseif (stripos($textperline, 'UNION')) {
                    $facility_id = 4;
                }
                  
                $care_team_facility = array('care_team_facility' => $facility_id);
                $facility = $facility_service->getById($facility_id);
                continue;
            }

            if (strpos($textperline, 'IDENTIFIER:') !== false) {
                $parts = preg_split('/\s+/', $textperline);
                $lname = str_replace(',', '', $parts[4]);
                $fname = $parts[5];

                $name = [
                    'fname' => x12Clean($fname),
                    'lname' => x12Clean($lname)
                ];
            }

            if (strpos($textperline, 'MED REC NO:') !== false) {
                $parts = preg_split('/\s+/', $textperline);
                $pubpid = substr($parts[4], -5, 5);
            }

            if (strpos($textperline, 'SOC. SEC.') !== false) {
                $parts = preg_split('/\s+/', $textperline);
                $ssn = array('ss' => $parts[3]);
            }

            if (strpos($textperline, 'BIRTH DATE:') !== false) {
                $parts = preg_split('/\s+/', $textperline);
                $dob = array('DOB' => (new \DateTimeImmutable($parts[6]))->format('Y-m-d'));
            }

            if (strpos($textperline, 'GENDER  . :') !== false) {
                $parts = preg_split('/\s+/', $textperline);
                $sex = array('sex' => $parts[4]);
            }

            if (strpos($textperline, 'MEDICARE#') !== false) {
                $parts = explode("MEDICARE#", $textperline);
                $prins = 2; 
                $pripol = trim(preg_replace('/\s+/','', $parts[1]));
            }

            if (strpos($textperline, 'MEDICAID#') !== false) {
                $parts = explode("MEDICAID#", $textperline);
                $secpol = trim(preg_replace('/\s+/','', $parts[1]));
                if (!empty($secpol)) {
                    $secins = 7; 
                } else {
                    $secins = null;
                }
            }

            if (!empty($payors)) {
                $policy = x12Clean(substr($textperline, 50, 16));
                if (
                    !empty($policy)
                    && (!strpos($textperline, '---------') !== false)
                ) {
                    $payor_name = substr($textperline, 19, 30);
                    if (
                        !empty($payor_name)
                        && (
                            !((stripos($textperline, 'Medicare') !== false)
                            || (stripos($textperline, 'Medicaid') !== false))
                        )
                    ) {
                        echo $fname . " " . $lname . " " . $payor_name . " " . $policy . " also has " . $prins . " and " . $secins . "\n";
                    }
                } else {
                }
            }

            if (strpos($textperline, 'SECONDARY CONTACT') !== false
                || strIpos($textperline, 'GUARANTOR') !== false) {
                $second_address_line = false;
            }
  
            if (!empty($second_address_line)) {
                $parts = explode(",", $textperline);
                if (!empty($parts[1])) {
                    $city = trim($parts[0]);
                    $state_zip_parts = preg_split('/\s+/', $parts[1]);
                    $state = trim($state_zip_parts[1]);
                    $zip = trim($state_zip_parts[2]);
                    $second_address_line = false;
                } else {
                    $street2 = trim($parts[0]);
                }
            }


            if (!empty($primary_contact)) {
                $parts = explode("(", $textperline);
                $phone = '';
                if (!empty($parts[1])) {
                    $phone = "(" . $parts[1];
                }
                if (stripos($textperline, '60 MAPLE LANE') !== false) {
                    $use_facility_address = true;
                    $primary_contact = false;
                    continue;
                } elseif (stripos($textperline, 'LEGAL GUARDIAN') !== false) {
                    $use_facility_address = true;
                    $primary_contact = false;
                    continue;
                } else {
                    $use_facility_address = false;
                    $street = trim($parts[0]);
                    $address = $street;
                    $primary_contact = false;
                    $street2 = '';
                    $second_address_line = true;
                }
            }

            if (strpos($textperline, 'PRIMARY CONTACT:') !== false) {
                if (strpos($textperline, 'SELF') !== false) {
                    $primary_contact = true;
                    continue;
                } else {
                    $use_facility_address = true;
                    $primary_contact = false;
                }
            }

            if (strpos($textperline, 'PAYORS:') !== false) {
                if (
                    (!strpos($textperline, 'MEDICARE') !== false)
                    || (!strpos($textperline, 'MEDICARE') !== false)
                    ) {
                    $payors = true;
                    continue;
                } else {
                    $payors = false;
                }
            }
        } else {
            if ($use_facility_address) {
                $street = $facility['street'];
                $city = $facility['city'];
                $state = 'VT';
                $zip = $facility['postal_code'];
            }

            $address = array(
                'street' => x12Clean($street),
                'street_line_2' => x12Clean($street2 ?? ''),
                'city' => x12Clean($city),
                'state' => x12Clean($state),
                'postal_code' => x12Zip($zip),
                'phone_home' => x12Clean($phone ?? '')
            );

            if (empty($prins)) {
                $secins = $prins;
                $secins = null;
            }

            $insurance = array(
                'insurance' => array(
                    'primary' => array(
                        'provider' => $prins,
                        'policy_number' => $pripol
                    ),
                    'secondary' => array(
                        'provider' => $secins,
                        'policy_number' => $secpol
                    )
                )
            );

            $data = array_merge(['pubpid' => $pubpid], $name, $address, $dob, $sex, $ssn, $insurance, $care_team_facility);
            if (!checkSsn($data['ss'])) {
                insertPerson($data);
            } else {
                echo $data['fname'] . " " . $data['lname'] . " with ssn " . $data['ss'] . " already exists \n";
            }
            $new_facesheet = false;
            $payors = false;
        }
    }
    // get last person
    $data = array_merge(['pubpid' => $pubpid], $name, $address, $dob, $sex, $ssn, $insurance, $care_team_facility);
    if (!checkSsn($data['ss'])) {
        insertPerson($data);
    } else {
        echo $data['fname'] . " " . $data['lname'] . " with ssn " . $data['ss'] . " already exists \n";
    }
    echo "end of file \n";
    fclose($file);
}

function insertPerson($data) {
    global $prins;
    global $secins;
    global $person_data;
    $patient_service = new PatientService();
    $person_insert = $patient_service->insert($data);
    $person_data = $person_insert->getData();
    $pid = $person_data[0]['pid'];
    if (!empty($prins)) {
        $type =  'primary';
        $date = '2022-10-01';
        insInsert($type, $date, $data, $pid);
    }
    if (!empty($secins)) {
        $type =  'secondary';
        $date = '2022-10-01';
        insInsert($type, $date, $data, $pid);
    }
}


function checkSsn($ssn) {

    $sql = sqlQuery("SELECT * FROM patient_data WHERE ss=?", array($ssn));
    if (!empty($sql['id'])) {
        return true;
    }

    $ssn_without_dashes = substr($ssn, 0, 3) . substr($ssn, 4, 2) . substr($ssn, 7, 4);

    $sql = sqlQuery("SELECT * FROM patient_data WHERE ss=?", array($ssn_without_dashes));
    if (!empty($sql['id'])) {
        return true;
    }

    return false;
}

function insInsert($type, $date, $data, $pid) {
    global $insurance_service;
    $insurance_service->insert
    (
        $pid,
        $type,
        array(
            'provider' => $data['insurance'][$type]['provider'],
            'plan_name' => '',
            'policy_number' => $data['insurance'][$type]['policy_number'],
            'group_number' => '',
            'subscriber_lname' => $data['lname'],
            'subscriber_mname' => $data['mname'] ?? '',
            'subscriber_fname' => $data['fname'],
            'subscriber_relationship' => 'self',
            'subscriber_ss' => $data['ss'],
            'subscriber_DOB' => $data['DOB'],
            'subscriber_street' => $data['street'],
            'subscriber_postal_code' => $data['postal_code'],
            'subscriber_city' => $data['city'],
            'subscriber_state' => $data['state'],
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
            'subscriber_sex' => $data['sex'],
            'accept_assignment' => 'TRUE',
            'policy_type' => 'FALSE'
        )
        );
}