<?php

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";
require __DIR__ . '/../../vendor/autoload.php';


use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;
use OpenEMR\Services\PatientService;
use OpenEMR\Services\Utils\DateFormatterUtils;
use PhpOffice\PhpSpreadsheet\IOFactory;

$inputFileName = '/tmp/test2.xls';
$inputFileType = 'Xls';

sqlStatement('truncate patient_data');

$reader = IOFactory::createReader($inputFileType);
$spreadsheet = $reader->load($inputFileName);
$worksheet = $spreadsheet->getActiveSheet();
$rows = $worksheet->toArray();
$cntr = 0;
$patientService = new PatientService();
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
    echo $cntr . "\n";
    $format = 'm/d/Y H:i:s';
    $dateMedtask = $value[10];
    if (!$dateMedtask) {
        $dateMedtask = date('m/d/Y H:i:s');
    }
    echo $dateMedtask . "\n";
    $testdate = \DateTimeImmutable::createFromFormat($format, $dateMedtask);

    var_dump($testdate);
    $oedate = $testdate->format('Y-m-d');
    $patient['DOB'] = $oedate;
    $patient['sex'] = ($value[11] == 'M') ? 'Male': 'Female';
    $patient['phone_home'] = $value[15];
    $patient['phone_cell'] = $value[18];
    $patient['phone_contact'] = $value[20];
    $patient['email'] = $value[23];


    $cntr++;

    $result = $patientService->insert($patient);
    var_dump($result->getValidationMessages());
}
