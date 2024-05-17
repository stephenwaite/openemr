<?php
exit;
$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";
require __DIR__ . '/../../vendor/autoload.php';


use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;
use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = '/tmp/test2.xls';
$inputFileType = 'Xls';

sqlStatement('truncate patient_data');
sqlStatement('truncate insurance_data');

$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();
$cntr = 0;
$patientService = new PatientService();
$insuranceService = new InsuranceService();

foreach ($rows as $key => $value) {
    if (!$cntr) {
        $cntr++;
        /* var_dump($value);
        exit; */
        continue;
    }

    //var_dump($value);

    foreach ($value as $k => $v) {
        if ($v == 'NULL') {
            $value[$k] = '';
        }
    }

    $patient = [];
    $ins_data = [];

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
    $patient['guardiansname'] = $value[22] ?? '';
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
    if (empty($value[23])) {
        $patient['email'] = null;
    } else {
        $patient['email'] = $value[23];
    }
    echo $patient['fname'] . " " . $patient['lname'] . "\n";


    $cntr++;

    $result = $patientService->insert($patient);
    //var_dump($result);
    $result_data = $result->getData();
    //var_dump($result_data);
    $ins_data['pid'] = $result_data[0]['pid'];
    $ins_data['type'] = 'primary';
    switch ($value[60]) {
        case '6897':
            $ins_data['provider'] = 3; // HIGHMARK ADDRESS 8873
            break;
        case '6898':
            $ins_data['provider'] = 7; // United Healthcare Community Plan for Kids
            break;
        case '6902':
            $ins_data['provider'] = 43; // Aetna Better Health Kids    
            break;
        case '6919':
            $ins_data['provider'] = 35; // AETNA 
            break;    
        case '6920':
            $ins_data['provider'] = 51; // CIGNA
            break;
        case '6921':
            $ins_data['provider'] = 55; // PA MEDICAID?
            break;
        case '6926':
            $ins_data['provider'] = 11; // UPMC HEALTHPLAN
            break;
        case '6928':
            $ins_data['provider'] = 19; // HIGHMARK WHOLECARE
            break;    
        case '6932':
            $ins_data['provider'] = 27; // UMR
            break;
        case '6935':
            $ins_data['provider'] = 31; // UNITED HEALTHCARE
            break;
        case '6955':
            $ins_data['provider'] = 67; // CHAMPVA
            break;    
        case '6956':
            $ins_data['provider'] = 3; // HIGHMARK BCBS
            break;
        case '17388':
            $ins_data['provider'] = 39; // MERITAIN HEALTH
            break;
        case '17582':
            $ins_data['provider'] = 59; // MERITAIN HEALTH
            break;    
        case '17917':
            $ins_data['provider'] = 23; // TRICARE EAST
            break;
        case '18296':
            $ins_data['provider'] = 47; // Uhc shared services
            break;
        case '18602':
            $ins_data['provider'] = 15; // UPMC FOR YOU
            break;
    }

    if (empty($ins_data['provider'])) {
        continue;
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

    //echo $ins_data['pid'] . "\n";
    $ins_result = $insuranceService->insert($ins_data['pid'], $ins_data['type'], $ins_data);
    //echo $value[60] . "\n";
    //if (!empty($value[64]) && $value[64] != "NULL") {
    //    $ins_codes[$value[60]] .= $value[64];
    //};
}

//var_dump($ins_codes);
