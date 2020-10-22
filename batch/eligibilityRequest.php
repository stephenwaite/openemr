<?php
// Copyright (C) 2013 Mark Kuperman <mark.kuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This a batch program that creates EDI 270 file for the patients
// from tomorrow's schedule
// This file is called from a file containing X12 partner specifics:
// $X12 = "GatewayEDI";
// $SRV = "sftp.gatewayedi.com";
// $USR = "ID";
// $PASS = "PASS";
// chdir(dirname(__FILE__));
// include('./eligibilityRequest.php');

// Disable PHP timeout.  This will not work in safe mode.
ini_set('max_execution_time', '0');

//starting the PHP session (also regenerating the session id to avoid session fixation attacks)
$ses = session_start();
session_regenerate_id(true);

//SANITIZE ALL ESCAPES
$fake_register_globals = false;

//STOP FAKE REGISTER GLOBALS
$sanitize_all_escapes = true;

//Settings that will override globals.php
$_GET['site'] = "default";

$ignoreAuth = true; // no login required

require_once('../interface/globals.php');
require_once('../library/sql.inc');
require_once('../library/edi.inc');

// Force logging off
$GLOBALS["enable_auditlog"]=0;
// Segment Terminator	
$segTer	= "~"; 	
// Component Element seperator
$compEleSep = "^";

// Determine date
$dt = time() + 60*60*24;        //tomorrow
$dtStr = date('Y-m-d', $dt);
//echo "debug:date " . $dtStr . "\n";

// Get X12 partner info
$query = 'SELECT id, id_number, x12_sender_id, x12_receiver_id, x12_version, processing_format ' . 
        'FROM x12_partners ' . 
        'WHERE name = ? ' . 
        'LIMIT 1';
$X12info = sqlQuery($query, array($X12));
// Have to mimic the Eligibility request report
$x12arr[0] = $X12info['id'];
$x12arr[1] = $X12info['id_number'];
$x12arr[2] = $X12info['x12_sender_id'];
$x12arr[3] = $X12info['x12_receiver_id'];
$x12arr[4] = $X12info['x12_version'];
$x12arr[5] = $X12info['processing_format'];

// Create 270 content
// $dtStr = '2013-02-01';  //debugging
$arr = getScheduledForDate($dtStr);

$str = print_elig($arr,$x12arr,$compEleSep,$segTer);

// Write 270 content to a file

$ediDir = $GLOBALS['edi_271_file_path'];
$fileOutName = $ediDir . "elig-270-" . $dtStr . ".edi";
$fileOut = fopen($fileOutName,"w");
fputs($fileOut, $str);
fclose($fileOut);

echo date('Y-m-d H:i:s') . ": Created 270-Eligibility request $fileOutName \n";

// upload $fileOutName to the X12 partner's site

$exc = "sshpass -p " . $PASS . " sftp " . $USR . "@" . $SRV . "<<EOF
     cd eligibility
     put " . $fileOutName . "
     quit
EOF
";
exec($exc);
//echo $exc . "\n";          //debugging
?>
