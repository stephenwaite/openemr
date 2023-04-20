<?php

$_GET['site'] = $argv[1];
$ignoreAuth = 1;
require_once(__DIR__ . "/../../interface/globals.php");

use OpenEMR\Services\EncounterService;
use OpenEMR\Services\ListService;

$records = [];
$start_date = '2012-01-01';
$end_date = '2023-01-31';
$encs_by_date_range = (new EncounterService())->getEncountersByDateRange($start_date, $end_date);

foreach ($encs_by_date_range as $key => $enc) {
    if (!empty($enc['reason'])) {
        //echo $enc['pid'] . "\n";
        $encs[$enc['pid']][] = $enc;
    }
}

ksort($encs);
//var_dump($encs);
//exit;


$listService = new ListService();
$encounterService = new EncounterService();

foreach($encs as $pid => $enc) {
    $out = 'Roger Kellogg, MD' . "\n";
    $out .= '286 Hospital Loop Rd' . "\n";
    $out .= 'Berlin, VT 05602' . "\n\n";
    $pid = strval($pid);
    $pt_data = getPatientData($pid);
    $fname = strtoupper(str_replace(' ', '_', trim($pt_data['fname'])));
    $lname = strtoupper(str_replace(' ', '_', trim($pt_data['lname'])));
    $filename = $lname . "_" . $fname . "-" . $pt_data['DOB'] . ".txt";
    $out .= $lname . ", " . $fname . " " . $pt_data['DOB'] . "\n";
    kelPrint(($listService->getAll($pid, 'allergy')), 'Allergies');
    kelPrint($listService->getAll($pid, 'dental'), 'Dental');
    kelPrint($listService->getAll($pid, 'medical_problem'), 'Medical Problems');
    kelPrint($listService->getAll($pid, 'medication'), 'Medications');
    kelPrint($listService->getAll($pid, 'surgery'), 'Surgeries');

    if (count($enc) > 1) {
        usort($enc, function($a, $b) {return intval($a['date']) < intval($b['date']); });
    }
    foreach($enc as $key => $value) {
        printEncounter($value);
    }
    $fh = fopen('/tmp/' . $filename, 'w') or die("unable to open file!");
    fwrite($fh, $out);
    fclose($fh);
}

function kelPrint($arr, $section) {
    global $out;
    if (!empty($arr)) {
        $out .= "\n" . $section . ":\n";
    } else {
        return;
    }

    $items = '';
    foreach($arr as $item) {
        $items .= $item['title'] . "; ";
    }
    $out .= wordwrap($items, 80, "\n", true) . "\n";
}

function printEncounter($arr) {
    global $out;
    foreach($arr as $k => $v) {
        if ($k == 'date') {
            $out .= "\n" . strtoupper($k) . ": " . substr($v, 0, 10) . "\n";
        }

        if ($k == 'reason') {
            $chunk = wordwrap($v, 80, "\n", true);
            $out .= "Speech Dictation: " . $chunk . "\n";
        }
    }
}
