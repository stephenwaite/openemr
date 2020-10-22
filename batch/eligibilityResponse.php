<?php
// Copyright (C) 2013 Mark Kuperman <mark.kuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This a batch program that retrieves EDI 271 fils from the X12 partner's site
// This file is called from a file containing X12 partner specifics:
// $X12 = "GatewayEDI";
// $SRV = "sftp.gatewayedi.com";
// $USR = "ID";
// $PASS = "PASS";
// chdir(dirname(__FILE__));
// include('./eligibilityResponse.php');

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
require_once('../library/edi.inc');

// Force logging off
$GLOBALS["enable_auditlog"]=0;
// Segment Terminator	
$segTer	= "~"; 	
// Component Element seperator
$compEleSep = "^";

$ediDir = $GLOBALS['edi_271_file_path'];
chdir($ediDir);
echo date('Y-m-d H:i:s') . ": Retreiving 271-Eligibility responses \n";

$exc = "sshpass -p " . $PASS . " sftp " . $USR . "@" . $SRV . "<<EOF
     cd eligibilityresponses
     mget *.elr
     rm *.elr
     quit
EOF
";
exec($exc);
?>
