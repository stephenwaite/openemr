<?php

// Copyright (C) 2013 Mark Kuperman <mark.kuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
// This a batch program that sends procedure orders to the host
// specified by Lab's SFTP parameters enterered today
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
require_once('../interface/orders/receive_hl7_results.inc.php');

// Force logging off
$GLOBALS["enable_auditlog"] = 0;


$pprow = sqlQuery("select * from procedure_providers where ppid = 2");

$hl7 = file_get_contents("/users/bioref/CC897/results/CC897_5076832.HL7");
$msg = receive_hl7_results($hl7, $pprow);
echo $msg;
?>
