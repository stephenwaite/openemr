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
      $pat_name = substr($line, 9, 31);
      $pieces = explode(",", $pat_name);
      $pat_lname = $pieces[0];
      $rest_name = explode(" ", trim($pieces[1]));
      $pat_fname = $rest_name[0];
      $pat_mname = $rest_name[1] ?? '';
      $tape[$acc_no]['lname'] = $pat_lname;
      $tape[$acc_no]['fname'] = $pat_fname;
      $tape[$acc_no]['mname'] = $pat_mname;
      $tape[$acc_no]['street'] = trim(substr($line, 40, 25));
      $tape[$acc_no]['suite'] = trim(substr($line, 65, 25));
      $tape[$acc_no]['city'] = trim(substr($line, 90, 25));
      $tape[$acc_no]['state'] = trim(substr($line, 115, 2));
      $tape[$acc_no]['zip'] = trim(substr($line, 117, 10));
      $tape[$acc_no]['admit_date'] = trim(substr($line, 127, 10));
      $tape[$acc_no]['admit_time'] = trim(substr($line, 137, 5));
      $tape[$acc_no]['work_comp'] = trim(substr($line, 142, 1));
      $gar_name = substr($line, 143, 35);
      $pieces = explode(",", $gar_name);
      $tape[$acc_no]['gar_lname'] = $pieces[0];
      $rest_name = explode(" ", trim($pieces[1]));
      $tape[$acc_no]['gar_fname'] = $rest_name[0];
      $tape[$acc_no]['gar_mname'] = $rest_name[1] ?? '';
      $tape[$acc_no]['gar_street'] = trim(substr($line, 178, 25));
      $tape[$acc_no]['gar_suite'] = trim(substr($line, 203, 25));
      $tape[$acc_no]['gar_city'] = trim(substr($line, 228, 25));
      $tape[$acc_no]['gar_state'] = trim(substr($line, 253, 2));
      $tape[$acc_no]['gar_zip'] = trim(substr($line, 255, 10));
      $tape[$acc_no]['email'] = trim(substr($line, 265, 30));
      $tape[$acc_no]['pri_ins_code'] = trim(substr($line, 295, 5));
      $tape[$acc_no]['pri_ins_pol'] = trim(substr($line, 300, 30));
      $tape[$acc_no]['pri_ins_cert'] = trim(substr($line, 330, 20));
      $tape[$acc_no]['pri_ins_group'] = trim(substr($line, 350, 20));
      $tape[$acc_no]['pri_ins_group_name'] = trim(substr($line, 370, 30));
      $sub_name = substr($line, 400, 35);
      if (!empty(trim($sub_name))) {
        $pieces = explode(",", $sub_name) ?? '';
        $tape[$acc_no]['pri_ins_sub_lname'] = $pieces[0];    
        $rest_name = explode(" ", trim($pieces[1] ?? ''));
        $tape[$acc_no]['pri_ins_sub_fname'] = ($rest_name[0] ?? '');
        $tape[$acc_no]['pri_ins_sub_mname'] = ($rest_name[1] ?? '');
      } else {
        $tape[$acc_no]['pri_ins_sub_lname'] = '';    
        $tape[$acc_no]['pri_ins_sub_fname'] = '';
        $tape[$acc_no]['pri_ins_sub_mname'] = '';
      }
      $tape[$acc_no]['pri_ins_employer_name'] = trim(substr($line, 435, 30));
      $tape[$acc_no]['pat_gender'] = trim(substr($line, 465, 1));
      // FILLER PIC X(1)
      $tape[$acc_no]['pat_dob'] = trim(substr($line, 467, 10));
      $tape[$acc_no]['pat_ssn'] = trim(substr($line, 477, 9));
      $tape[$acc_no]['pat_relate'] = trim(substr($line, 486, 2));
      $tape[$acc_no]['pri_ins_name'] = trim(substr($line, 488, 25));
      $tape[$acc_no]['pri_ins_contact'] = trim(substr($line, 513, 25));
      $tape[$acc_no]['pri_ins_street'] = trim(substr($line, 538, 20));
      $tape[$acc_no]['pri_ins_suite'] = trim(substr($line, 558, 15));
      $tape[$acc_no]['pri_ins_city'] = trim(substr($line, 573, 20));
      $tape[$acc_no]['pri_ins_state'] = trim(substr($line, 593, 2));
      $tape[$acc_no]['pri_ins_zip'] = trim(substr($line, 595, 10));
      $tape[$acc_no]['pri_ins_phone'] = trim(substr($line, 605, 12));
      $tape[$acc_no]['pri_ins_auth'] = trim(substr($line, 617, 20));
      $tape[$acc_no]['sec_ins_code'] = trim(substr($line, 637, 5));
      $tape[$acc_no]['sec_ins_pol'] = trim(substr($line, 642, 30));
      $tape[$acc_no]['sec_ins_cert'] = trim(substr($line, 672, 20));
      $tape[$acc_no]['sec_ins_group'] = trim(substr($line, 692, 20));
      $tape[$acc_no]['sec_ins_group_name'] = trim(substr($line, 712, 30));
      $sec_ins_sub_name = trim(substr($line, 742, 35));
      if (!empty(trim($sec_ins_sub_name))) {
        $pieces = explode(",", $sec_ins_sub_name) ?? '';
        $tape[$acc_no]['sec_ins_sub_lname'] = $pieces[0];    
        $rest_name = explode(" ", trim($pieces[1] ?? ''));
        $tape[$acc_no]['sec_ins_sub_fname'] = ($rest_name[0] ?? '');
        $tape[$acc_no]['sec_ins_sub_mname'] = ($rest_name[1] ?? '');
      } else {
        $tape[$acc_no]['sec_ins_sub_lname'] = '';    
        $tape[$acc_no]['sec_ins_sub_fname'] = '';
        $tape[$acc_no]['sec_ins_sub_mname'] = '';
      }
      $tape[$acc_no]['sec_ins_employer_name'] = trim(substr($line, 777, 30));
      $tape[$acc_no]['sec_ins_sub_gender'] = trim(substr($line, 807, 1));
      // FILLER PIC X(1)
      $tape[$acc_no]['sec_ins_sub_dob'] = trim(substr($line, 809, 10));
      $tape[$acc_no]['sec_ins_sub_ssn'] = trim(substr($line, 819, 9));
      $tape[$acc_no]['sec_ins_relate'] = trim(substr($line, 828, 2));
      $tape[$acc_no]['sec_ins_name'] = trim(substr($line, 830, 25));
      $tape[$acc_no]['sec_ins_contact'] = trim(substr($line, 855, 25));
      $tape[$acc_no]['sec_ins_street'] = trim(substr($line, 880, 20));
      $tape[$acc_no]['sec_ins_suite'] = trim(substr($line, 900, 15));
      $tape[$acc_no]['sec_ins_city'] = trim(substr($line, 915, 20));
      $tape[$acc_no]['sec_ins_state'] = trim(substr($line, 935, 2));
      $tape[$acc_no]['sec_ins_zip'] = trim(substr($line, 937, 10));
      $tape[$acc_no]['sec_ins_phone'] = trim(substr($line, 947, 12));
      // R1-IO PIC X(4)
      // FILLER PIC X(2)
    }

    if (substr($line, 0, 2) == "++") {
      $tape[$acc_no]['accident_date'] = trim(substr($line, 2, 6));
      $tape[$acc_no]['accident_time'] = trim(substr($line, 8, 5));
      $ref_prov_name = trim(substr($line, 13, 22));
      if (!empty(trim($ref_prov_name))) {
        $pieces = explode(",", $ref_prov_name) ?? '';
        $tape[$acc_no]['ref_prov_lname'] = $pieces[0];    
        $rest_name = explode(" ", trim($pieces[1] ?? ''));
        $tape[$acc_no]['ref_prov_fname'] = ($rest_name[0] ?? '');
        $tape[$acc_no]['ref_prov_mname'] = ($rest_name[1] ?? '');
      } else {
        $tape[$acc_no]['ref_prov_lname'] = '';    
        $tape[$acc_no]['ref_prov_fname'] = '';
        $tape[$acc_no]['ref_prov_mname'] = '';
      }
      $tape[$acc_no]['rrmc_diag'] = trim(substr($line, 35, 130));
      $tape[$acc_no]['tri_ins_code'] = trim(substr($line, 165, 5));
      $tape[$acc_no]['tri_ins_pol'] = trim(substr($line, 170, 30));
      $tape[$acc_no]['tri_ins_cert'] = trim(substr($line, 200, 20));
      $tape[$acc_no]['tri_ins_group'] = trim(substr($line, 220, 20));
      $tape[$acc_no]['tri_ins_group_name'] = trim(substr($line, 240, 30));
      $tri_ins_sub_name = trim(substr($line, 270, 35));
      if (!empty(trim($sec_ins_sub_name))) {
        $pieces = explode(",", $sec_ins_sub_name) ?? '';
        $tape[$acc_no]['tri_ins_sub_lname'] = $pieces[0];    
        $rest_name = explode(" ", trim($pieces[1] ?? ''));
        $tape[$acc_no]['tri_ins_sub_fname'] = ($rest_name[0] ?? '');
        $tape[$acc_no]['tri_ins_sub_mname'] = ($rest_name[1] ?? '');
      } else {
        $tape[$acc_no]['tri_ins_sub_lname'] = '';    
        $tape[$acc_no]['tri_ins_sub_fname'] = '';
        $tape[$acc_no]['tri_ins_sub_mname'] = '';
      }
      $tape[$acc_no]['tri_ins_employer_name'] = trim(substr($line, 305, 30));
      $tape[$acc_no]['tri_ins_sub_gender'] = trim(substr($line, 335, 1));
      // FILLER PIC X(1)
      $tape[$acc_no]['tri_ins_sub_dob'] = trim(substr($line, 337, 10));
      $tape[$acc_no]['tri_ins_sub_ssn'] = trim(substr($line, 347, 9));
      $tape[$acc_no]['tri_ins_relate'] = trim(substr($line, 356, 2));
      $tape[$acc_no]['tri_ins_name'] = trim(substr($line, 358, 25));
      $tape[$acc_no]['tri_ins_contact'] = trim(substr($line, 383, 25));
      $tape[$acc_no]['tri_ins_street'] = trim(substr($line, 408, 20));
      $tape[$acc_no]['tri_ins_suite'] = trim(substr($line, 428, 15));
      $tape[$acc_no]['tri_ins_city'] = trim(substr($line, 443, 20));
      $tape[$acc_no]['tri_ins_state'] = trim(substr($line, 463, 2));
      $tape[$acc_no]['tri_ins_zip'] = trim(substr($line, 465, 10));
      $tape[$acc_no]['tri_ins_phone'] = trim(substr($line, 475, 12));
      // FILLER PIC X(20)
      $tape[$acc_no]['ter_ins_code'] = trim(substr($line, 507, 5));
      $tape[$acc_no]['ter_ins_pol'] = trim(substr($line, 512, 30));
      $tape[$acc_no]['ter_ins_cert'] = trim(substr($line, 542, 20));
      $tape[$acc_no]['ter_ins_group'] = trim(substr($line, 562, 20));
      $tape[$acc_no]['ter_ins_group_name'] = trim(substr($line, 582, 30));
      $ter_ins_sub_name = trim(substr($line, 612, 35));
      if (!empty(trim($sec_ins_sub_name))) {
        $pieces = explode(",", $sec_ins_sub_name) ?? '';
        $tape[$acc_no]['ter_ins_sub_lname'] = $pieces[0];    
        $rest_name = explode(" ", trim($pieces[1] ?? ''));
        $tape[$acc_no]['ter_ins_sub_fname'] = ($rest_name[0] ?? '');
        $tape[$acc_no]['ter_ins_sub_mname'] = ($rest_name[1] ?? '');
      } else {
        $tape[$acc_no]['ter_ins_sub_lname'] = '';    
        $tape[$acc_no]['ter_ins_sub_fname'] = '';
        $tape[$acc_no]['ter_ins_sub_mname'] = '';
      }
      $tape[$acc_no]['ter_ins_employer_name'] = trim(substr($line, 647, 30));
      $tape[$acc_no]['ter_ins_sub_gender'] = trim(substr($line, 777, 1));
      // FILLER PIC X(1)
      $tape[$acc_no]['ter_ins_sub_dob'] = trim(substr($line, 779, 10));
      $tape[$acc_no]['ter_ins_sub_ssn'] = trim(substr($line, 789, 9));
      $tape[$acc_no]['ter_ins_relate'] = trim(substr($line, 798, 2));
      $tape[$acc_no]['ter_ins_name'] = trim(substr($line, 800, 25));
      $tape[$acc_no]['ter_ins_contact'] = trim(substr($line, 825, 25));
      $tape[$acc_no]['ter_ins_street'] = trim(substr($line, 850, 20));
      $tape[$acc_no]['ter_ins_suite'] = trim(substr($line, 870, 15));
      $tape[$acc_no]['ter_ins_city'] = trim(substr($line, 885, 20));
      $tape[$acc_no]['ter_ins_state'] = trim(substr($line, 905, 2));
      $tape[$acc_no]['ter_ins_zip'] = trim(substr($line, 907, 10));
      $tape[$acc_no]['tri_ins_phone'] = trim(substr($line, 917, 12));
      // FILLER PIC X(20)
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
    case 6251:
      if ($charge == 'C8900' ) return "74185";
      break;  
    case 6252:
      if ($charge == 'C8902' ) return "74185";
      break;
    case 6327:
      if ($charge == 'C8908' ) return "77049";
      break;
  }

}

var_dump($tape);
