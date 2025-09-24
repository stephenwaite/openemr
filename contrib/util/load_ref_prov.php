<?php

/**
 * Load referring providers from a csv file
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use League\Csv\Reader;

// setup a csv file with a header consiting of type, code and modifier
// at the specified location
$filename = $argv[2];
echo $filename . "\n";
$filepath = $GLOBALS['temporary_files_dir'];
echo $filepath . "\n";
$reader = Reader::createFromPath($filepath . DIRECTORY_SEPARATOR . $filename);
//$reader->setDelimiter(",");

$start_record = $argv[3];
$reader->setHeaderOffset($start_record);
$header = $reader->getHeader();
var_dump($header);
$records = $reader->getRecords($header);
foreach ($records as $key => $val) {
    echo $key . "\n";
    var_dump($val);
    $sqlArr = [
      trim($val['Code']),
      trim($val['First Name']),
      trim($val['Last Name']),
      trim($val['Valedictory']),
      trim($val['Address 1']),
      trim($val['Address 2']),
      trim($val['City']),
      trim($val['State']),
      trim($val['Zip']),
      trim($val['Office Phone']),
      trim($val['Fax']),
      trim($val['NPI']),
    ];
    $userid = sqlInsert(
        "INSERT INTO users (info, fname, lname, valedictory, street, streetb, city, `state`, zip, phone, fax, npi, abook_type)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'external_provider')",
        $sqlArr
    );
}
