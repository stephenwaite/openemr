<?php
/*
* @package   OpenEMR
* @link      https://www.open-emr.org
* @author    Brady Miller <brady.g.miller@gmail.com>
* @copyright Copyright (c) 2021 Brady Miller <brady.g.miller@gmail.com>
* @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

if (php_sapi_name() !== 'cli') {
   echo "Only php cli can execute a command\n";
   die;
}


// collect parameters (need to do before globals)

$_GET['site'] = $argv[1];
$openemrPath = $argv[2];
$pubpid = $argv[3];
$accession = $argv[4];

$ignoreAuth = 1;
require_once($openemrPath . "/interface/globals.php");

use OpenEMR\Services\ProcedureService;

$vhie = new ProcedureService();

$foo = sqlQuery("select `pid`, `uuid` from `patient_data` where `pubpid` = ? order by `date` limit 0,1", array($pubpid));
//var_dump($foo);

$pid = $foo['pid'];

$result = $vhie->search(['pid' => $pid, 'order_account' => $accession]);
var_dump($result->getData());



//$vhie->search();

