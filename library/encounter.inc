<?php
/** 
* library/encounter.inc - encounter functions for globals and acl 
*
* Copyright (C) 2010-2014 Brady Miller <brady@sparmy.com>
*
* LICENSE: This program is free software; you can redistribute it and/or 
* modify it under the terms of the GNU General Public License 
* as published by the Free Software Foundation; either version 3 
* of the License, or (at your option) any later version. 
* This program is distributed in the hope that it will be useful, 
* but WITHOUT ANY WARRANTY; without even the implied warranty of 
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
* GNU General Public License for more details.  
* You should have received a copy of the GNU General Public License 
* along with this program. If not, see <http://opensource.org/licenses/gpl-lice
* 
* @package OpenEMR 
* @author	Brady Miller	<brady@sparmy.com>
* @author	Stephen Waite	<stephen.waite@cmsvt.com> 
* @link http://www.open-emr.org
*/

include_once("../interface/globals.php");
include_once("$srcdir/forms.inc");

//function called to set the global session variable for encounter number
function setencounter($enc) {
	
	$return_val = 1;
	global $encounter;
	global $pid;
	
	if ($enc == "" ) {
		$enc = date("Ymd");
		if (getFormByEncounter($pid,$enc)) {
			//there is an encounter enterred for today
		} else {
			//addForm($enc, "New Patient Encounter", 0, $pid, 1);
			$return_val = 0;
		}
	}
	
	$_SESSION['encounter']=$enc;
	$encounter=$enc;
	
	
	//returns 1 on successful global set, or 0 if there was no
	//current encounter, signifying that the interface should load
	//the screen for a new encounter
	return $return_val;
}



// make sure proper authorization is in place for encounter access, including subforms like fee_sheet/new.php
function feeSheetAuth($enc_pid) {

  // get provider id for providers who can only view their stuff
  $tmp_provid = sqlQuery("SELECT provider_id FROM form_encounter " .
    "WHERE pid = ? AND encounter = ? " , array($enc_pid[0], $enc_pid[1]));

  $encounter_provid = 0 + $tmp_provid['provider_id'];

  if (acl_check('acct','bill')) {
      return;
  } elseif (acl_check('encounters','coding_a')) {
      return;
  } elseif (acl_check('encounters','coding') && ($encounter_provid == $enc_pid[2])) {
      return;
  } else {   
      die("You are not authorized to view this area of the EMR.  Please ask an administrator for permission.");
  }
}
?>

