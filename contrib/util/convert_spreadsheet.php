<?php

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";
require __DIR__ . '/../../vendor/autoload.php';


use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = '/tmp/test3.xls';
$inputFileType = 'Xls';

sqlStatement('truncate patient_data');

$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();
$cntr = 0;
$patientService = new PatientService();
$insuranceService = new InsuranceService();

$ins_codes = [];
foreach ($rows as $key => $value) {
    if (!$cntr) {
        $cntr++;
        /* var_dump($value);
        exit; */
        continue;
    }

    foreach ($value as $k => $v) {
        if ($v == 'NULL') {
            $value[$k] = '';
        }
    }

    $patient = [];
    $patient['pubpid'] = $value[0];
    $patient['fname'] = $value[1];
    $patient['lname'] = $value[2];
    $patient['mname'] = $value[3];
    $patient['suffix'] = $value[4];
    $patient['street'] = $value[5];
    $patient['street_line_2'] = $value[6];
    $patient['city'] = ucfirst($value[7]);
    $patient['state'] = $value[8];
    $patient['postal_code'] = $value[9];
    //echo $cntr . "\n";
    $format = 'm/d/Y H:i:s';
    $dateMedtask = $value[10];
    if (!$dateMedtask) {
        $dateMedtask = date('m/d/Y H:i:s');
    }
    //echo $dateMedtask . "\n";
    $testdate = \DateTimeImmutable::createFromFormat($format, $dateMedtask);

    //var_dump($testdate);
    $oedate = $testdate->format('Y-m-d');
    $patient['DOB'] = $oedate;
    $patient['sex'] = ($value[11] == 'M') ? 'Male' : 'Female';
    $patient['phone_home'] = $value[15];
    $patient['phone_cell'] = $value[18];
    $patient['phone_contact'] = $value[20];
    $patient['email'] = $value[23];


    $cntr++;

    $result = $patientService->insert($patient);
    $result_data = $result->getData();
    $ins_data['pid'] = $result_data[0]['pid'];
    $ins_data['type'] = 'primary';
    switch ($value[60]) {
        case '6897':
            $ins_data['provider'] = 2;
            break;
        case '6898':
            $ins_data['provider'] = 7;
            break;
        case '6926':
            $ins_data['provider'] = 11;
            break;
        case 'default':
            $ins_data['provider'] = 15;
            break;
    }
    $ins_data['policy_number'] = $value[62];
    $ins_data['group_number'] = $value[63];
    $ins_data['subscriber_relationship'] = 'self';
    $ins_data['subscriber_fname'] = $patient['fname'];
    $ins_data['subscriber_lname'] = $patient['lname'];
    $ins_data['subscriber_DOB'] = $patient['DOB'];
    $ins_data['subscriber_sex'] = $patient['sex'];
    $ins_data['accept_assignment'] = 'TRUE';
    $ins_data['subscriber_street'] = $patient['street'];
    $ins_data['subscriber_postal_code'] = $patient['postal_code'];
    $ins_data['subscriber_street_line_2'] = $patient['street_line_2'];
    $ins_data['subscriber_city'] = $patient['city'];
    $ins_data['subscriber_state'] = $patient['state'];

    $ins_data['date'] = "2024-01-01";
    $ins_data['date_end'] = "";

    $ins_result = $insuranceService->insert($ins_data);
    var_dump($ins_result);
    //echo $value[60] . "\n";
    //if (!empty($value[64]) && $value[64] != "NULL") {
    //    $ins_codes[$value[60]] .= $value[64];
    //};
}

//var_dump($ins_codes);
