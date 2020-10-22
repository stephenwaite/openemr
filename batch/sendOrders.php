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
require_once('../interface/orders/gen_hl7_order.inc.php');

// Force logging off
$GLOBALS["enable_auditlog"] = 0;

// Determine date
$br = "\n";
//if ($_GET) {
//    $dt = $_GET['date'];
//    $br = "<br>";
//} else
if (count($argv) > 1)
    $dt = $argv[1];
  else
    $dt = date('Y-m-d');

$dt1 = $dt . " 00:00:00";
$dt2 = $dt . " 23:59:59";

echo "Send Orders Batch Date=$dt" . $br;

$sql = "SELECT po.procedure_order_id, pp.ppid " .
        "FROM procedure_order AS po, procedure_providers AS pp, forms AS f " .
        "WHERE pp.ppid = po.lab_id " .
        "AND f.formdir = 'procedure_order' " .
        "AND f.form_id = po.procedure_order_id " .
        "AND f.deleted = 0 " .
        "AND po.date_collected between ? AND ? " .
        "AND po.date_transmitted IS NULL " .
        "AND pp.protocol = 'SFTP'";
$res = sqlStatement($sql, array($dt1, $dt2));

while ($row = sqlFetchArray($res)) {
    $orderId = $row['procedure_order_id'];
    $ppid = $row['ppid'];
    $alertmsg = '';
    $hl7 = '';
    $alertmsg = gen_hl7_order($orderId, $hl7);
    if (empty($alertmsg))
      $alertmsg = send_hl7_order($ppid, $hl7);
        if (empty($alertmsg))
            sqlStatement("UPDATE procedure_order SET date_transmitted = NOW() WHERE " .
                    "procedure_order_id = ?", array($orderId));
    echo "Order: " . $orderId . " Lab: " . $ppid . " -> " . $alertmsg . $br;
}
?>
