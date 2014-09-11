<?php
/**
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 */
     
$fake_register_globals=false;
$sanitize_all_escapes=true;
     
require_once("../globals.php");

$site= $_SESSION['site_id'];

$filename = '../../sites/' . $site . '/edi/process_bills.log';


$fh = fopen($filename,'r');

while ($line = fgets($fh)) {
    echo($line);
    echo("<br />");
    }
    fclose($fh);
    
?>