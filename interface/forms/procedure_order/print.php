<?php

// Copyright (C) 2013 Mark Kuperman <mkuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

$sanitize_all_escapes = true;
$fake_register_globals = false;

require_once("../../globals.php");
require_once("$srcdir/acl.inc");
require_once("../../orders/print_order.php");

// Check authorization.
$thisauth = acl_check('encounters', 'coding');
if (!$thisauth) die(xl('Not authorized'));

$orderId = intval($_GET['orderid']);

$flContent = getOrderPdf($orderId);

$flName = "proc-$orderId.pdf";
$fl = fopen($GLOBALS['OE_SITE_DIR'] . "/edi/$flName", 'w');
if ($fl) {
    fwrite($fl, $flContent);
    fclose($fl);
}

//debugging
//$flName = "/Users/mark2/Documents/MyDev/openemr/elabs/Requisition 1.pdf";
//$flContent = file_get_contents($flName);

header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header("Content-Type: application/force-download");
header("Content-Disposition: attachment; filename=$flName");
header("Content-Description: File Transfer");
header("Content-Length: " . strlen($flContent));
echo $flContent;
exit;

?>
