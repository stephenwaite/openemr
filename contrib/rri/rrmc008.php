<?php
/*
* @package cms
* @link    http://www.cmsvt.com
* @author  s waite <cmswest@sover.net>
* @copyright Copyright (c) 2021 cms <cmswest@sover.net>
* @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
* rrmc008.php      
*/

use OpenEMR\Billing\Accession;

$ignoreAuth = true;
$_GET['site'] = $argv[1];
$file = $argv[2];
require_once(dirname(__FILE__) . "/../../interface/globals.php");
require_once(dirname(__FILE__) . "/../../library/patient.inc");

$handle = fopen($file, "r");

$tape = array();

// create array of tape with accession no. as key
while (($line = fgets($handle)) !== false) {    
    if (substr($line, 0, 2) == "##") {
      $acc_no = trim(substr($line, 2, 7));
      if (!($tape[$acc_no] ?? '')) {
        $tape[$acc_no] = array();
      }
      $pat_name = substr($line, 9, 30);
      $pieces = explode(",", $pat_name);
      $pat_lname = $pieces[0];
      $rest_name = explode(" ", trim($pieces[1]));
      $pat_fname = $rest_name[0];
      $pat_mname = $rest_name[1] ?? '';
      $tape[$acc_no]['lname'] = $pat_lname;
      $tape[$acc_no]['fname'] = $pat_fname;
      $tape[$acc_no]['mname'] = $pat_mname;
      $tape[$acc_no]['street'] = trim(substr($line, 40, 50));
      $email = trim(substr($line, 265, 30));
      $tape[$acc_no]['email'] = $email;
    }

    if (substr($line, 0, 2) == "++") {
      $mrn = trim(substr($line, 849, 8));
      $mrn = trim(str_replace('-', '', $mrn));
      $tape[$acc_no]['mrn'] = $mrn;
      $ssn = trim(substr($line, 974, 9));
      $tape[$acc_no]['ssn'] = $ssn;
    }

    if (substr($line, 0, 2) == "$$") {
      $chg = 'charge';
      if (substr($line, 2, 3) == "CAN") {
        $chg = 'cancel';
      }  
      $tape[$acc_no]['charges'][] = array(
        'chg' => $chg,
        'rrmc_key' => trim(substr($line, 5, 7)),
        'cdm' => trim(substr($line, 8, 4)),
        'dos' => trim(substr($line, 12, 6)),
        'clin' => trim(substr($line, 21, 64)),
        'pos' => trim(substr($line, 85, 4)),
        'doc' => trim(substr($line, 89, 22)),
        'cpt' => trim(substr($line, 111, 5)),
        'hcpcs' => trim(substr($line, 119, 5)),
        'mod1' => trim(substr($line, 127, 2)),
        'mod2' => trim(substr($line, 130, 2)),
        'mod3' => trim(substr($line, 133, 2)),
        'place' => trim(substr($line, 140, 20)),
        'loc' => trim(substr($line, 180, 6))
      );     
    }
}          

foreach($tape as $key => $value) {
  // sanity check on incoming charges
  foreach($value['charges'] as $index => $charge) {
      $check_charge = $charge['cpt'] ?: $charge['hcpcs'];
      if (!checkIfWeHaveProc($charge['cdm'], $check_charge)) {        
        die("For $key, we need to add " . $check_charge . " and maybe 26 mod to procfile");
      };
      $change_rrmc_cpt = changeHCPCSToCPT($charge['cdm'], $check_charge);
      if ($change_rrmc_cpt) {        
        $code = $change_rrmc_cpt;
        echo "changing the cpt to $code". "\n";
      }  
  }
}


function checkIfWeHaveProc($cdm, $cpt)
{
  if($prof_component = sqlQueryNoLog("select proc_cpt from procfile where proc_cdm = ? and proc_cpt = ? and proc_mod = '26'", [$cdm, $cpt])) {
    //print_r($prof_component);
    return $prof_component;
  }
  if($prof_component = sqlQueryNoLog("select proc_cpt from procfile where proc_cdm = ? and proc_cpt = ? and proc_mod = ''", [$cdm, $cpt])) {
    return $prof_component;
  }
  return false;
}

function changeHCPCSToCPT($cdm, $charge) {
  switch($cdm) {
    case 6250:
      if ($charge == 'C8901' ) return "74185";
      break;
  }

}

var_dump($tape);
