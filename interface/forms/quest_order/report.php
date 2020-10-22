<?php
/** **************************************************************************
 *	QUEST_ORDER/REPORT.PHP
 *
 *	Copyright (c)2013 - Williams Medical Technology, Inc.
 *
 *	This program is licensed software: licensee is granted a limited nonexclusive
 *  license to install this Software on more than one computer system, as long as all
 *  systems are used to support a single licensee. Licensor is and remains the owner
 *  of all titles, rights, and interests in program.
 *  
 *  Licensee will not make copies of this Software or allow copies of this Software 
 *  to be made by others, unless authorized by the licensor. Licensee may make copies 
 *  of the Software for backup purposes only.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT 
 *	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS 
 *  FOR A PARTICULAR PURPOSE. LICENSOR IS NOT LIABLE TO LICENSEE FOR ANY DAMAGES, 
 *  INCLUDING COMPENSATORY, SPECIAL, INCIDENTAL, EXEMPLARY, PUNITIVE, OR CONSEQUENTIAL 
 *  DAMAGES, CONNECTED WITH OR RESULTING FROM THIS LICENSE AGREEMENT OR LICENSEE'S 
 *  USE OF THIS SOFTWARE.
 *
 *  @package quest
 *  @subpackage order
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
require_once("../../globals.php");
include_once("{$GLOBALS['srcdir']}/sql.inc");
include_once("{$GLOBALS['srcdir']}/api.inc");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.report.php");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.forms.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");

if (!function_exists("quest_order_report")) { // prevent redeclarations

function quest_order_report($pid, $encounter, $cols, $id) {
	$form_name = 'quest_order';
	$form_table = 'form_quest_order';
	$item_name = 'quest_order_action';
	$item_table = 'form_quest_order_item';
	$form_title = 'Quest Lab Order';

	// Retrieve form content
	$order_data = new wmtForm($form_name,$id);

	$item_list = array();
	$query = "SELECT  * FROM form_quest_order_item WHERE parent_id = '$id' ORDER BY id";
	$result = sqlStatement($query);
	while ($row = sqlFetchArray($result)) {
		$item_list[] = $row;	
	}

	// Report outter frame
	print "<link rel='stylesheet' type='text/css' href='../../forms/quest_order/style_wmt.css' />";
	print "<div class='wmtReport'>\n";
	print "<table class='wmtFrame' cellspacing='0' cellpadding='3'>\n";

	// Status header
	$content = "";
	$status = 'Complete';
	if ($order_data->status == 'i') $status = "Incomplete";
	if ($order_data->status || $order_data->priority) {
		$content .= "<tr><td colspan='4'>\n";
		$content .= "<table class='wmtStatus' style='margin-bottom:10px'><tr>";
		$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Status:</td>";
		$content .= "<td class='wmtOutput'>" . $status . "</td>";
		$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Priority:</td>";
		$content .= "<td class='wmtOutput'>" . ListLook($order_data->priority, 'Form_Priority') . "</td>\n";
		$content .= "</tr></table></td></tr>\n";
	}
//  NOTE!! different status codes so can't use default print routine
//	$content = do_status($order_data->status, $order_data->priority);
		if ($content) print $content;
	
	// Order summary
	$content = '';
	$content .= do_line(date('Y-m-d',strtotime($order_data->request_datetime)),'Order Date');
	$content .= do_line($order_data->request_number,'Requestion');
	$content .= do_line(UserIdLook($order_data->request_provider),'Ordering Provider');
	if ($order_data->request_provider != $order_data->user) {
		$content .= do_line(UserLook($order_data->user),'Entering Clinician');
	}
	$content .= do_line("<div style='white-space:pre-wrap'>".$order_data->request_notes."</div>",'Clinic Notes');
	do_section($content, 'Order Summary');
	
	// Loop through diagnosis
	$content = '';
	$dx_count = 1;
	for ($d = 0; $d < 10; $d++) {
		$key = "dx".$d."_code";
		$dx_code = $order_data->$key;
		if ($dx_code) {
			$key = "dx".$d."_text";
			$dx_text = $order_data->$key;

			// Diagnosis section
			$content .= do_columns($dx_code, 'ICD Code',$dx_text, 'Description');
//			$content .= do_blank();
		}
	}	
	do_section($content, 'Order Diagnosis');
	
	// Order specimen
	$content = '';
	$collected = ($order_data->order0_datetime)?date('Y-m-d h:i A',strtotime($order_data->order0_datetime)):null;
	$pending = ($order_data->order0_pending)?date('Y-m-d h:i A',strtotime($order_data->order0_pending)):null;
	
	if ($order_data->order0_psc) {
		$content .= do_line('YES','PSC Hold Order');
		$content .= do_line($pending,'Scheduled Date');
	}
	else {
		$content .= do_line('YES','Sample Collected');
		$content .= do_columns($collected,'Collection Date',$order_data->order0_volume,'Volume(ml)');
		$content .= do_columns(($order_data->order0_fasting)?'YES':'NO','Patient Fasting',$order_data->order0_duration,'Duration (hours)');
	}
	$content .= do_break();
	
	// loop through requestions
	$num = 1;
	foreach ($item_list as $item_data) {
		$need_blank = false;
		
		// Test section
		$type = ($item_data['test_profile'])? "Profile Code" : "Test Code";
		$content .= do_line($item_data['test_code'],$type);
		$content .= do_line($item_data['test_text'], 'Description');
//		$content .= do_columns($item_data['test_code'],$type,$item_data['test_text'], 'Description');

		// add profile tests if necessary
		if ($item_data['test_profile']) {
			$query = "SELECT p.description FROM cdc_order_codes oc ";
			$query .= "JOIN cdc_profiles p ON oc.test_cd = p.test_cd ";
			$query .= "WHERE oc.active_ind = 'A' AND oc.test_cd = '".$item_data['test_code']."' ";
			$query .= "ORDER BY oc.test_cd";
			$result = sqlStatement($query);

			while ($profile = sqlFetchArray($result)) {
				if ($profile['description']) {
					$content .= do_line('  - '.$profile['description'], '');
					$need_blank = true;
				}
			}
			
		}
	
		// add AOE questions if necessary
		for ($a = 0; $a < 20; $a++) {
			if ($item_data[aoe.$a._code]) {
				$aoe = "<td class='wmtLabel' style='width:120px'>&nbsp;</td><td class='wmtLabel' style='width:300px;white-space:nowrap'>".$item_data[aoe.$a._label].": </td>\n";
				$aoe .= "<td class='wmtOutput' style='white-space:nowrap'>".$item_data[aoe.$a._text]."</td>\n";
				if ($aoe) $content .= "<tr>".$aoe."</tr>";
//				$content .= do_columns('BLANK','',$item_data[aoe.$a._text], $item_data[aoe.$a._label],TRUE);
				$need_blank = true;
			}
		}

		if ($need_blank) $content .= do_blank(); // skip first time
	}
	// lab notes
	if ($order_data->order0_notes) {
		$content .= do_break();
		$content .= do_line($order_data->order0_notes,'Lab Notes');
	}
	
	do_section($content, 'Order Requestion - '.$order_data->order0_number);
//		}
//	}	
	
		
	
	print "</td></tr></table>";
	
} // end declaration 

} // end if function

?>
