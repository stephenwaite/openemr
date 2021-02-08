<?php

$ignoreAuth = true;
$_GET['site'] = 'default';
//$argv = $_GET['argv'];
require_once(dirname(__FILE__) . "/../interface/globals.php");

use League\Csv\Reader;

$csv = Reader::createFromPath('/tmp/test.csv', 'r');
$csv->setHeaderOffset(0);
$header_offset = $csv->getHeaderOffset(); //returns 0
$header = $csv->getHeader(); //returns ['First Name', 'Last Name', 'E-mail']
echo $header;



