<?php

// Copyright (C) 2013 Mark Kuperman <mark.kuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//
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

if (count($argv) > 1)
    $fn = $argv[1];
  else
    $fn = date('Y-m-d');
echo $fn . "\n";
$pprow = sqlQuery("select * from procedure_providers where ppid = 2");

$hl7 = file_get_contents("/var/www/openemr/sites/default/procedure_results/2/" . $fn);
$msg = receive_hl7_results($hl7, $pprow);
echo $msg;
?>
