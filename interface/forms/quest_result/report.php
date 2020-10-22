<?php
/** **************************************************************************
 *	QUEST_RESULT/REPORT.PHP
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
 *  @subpackage result
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
require_once("../../globals.php");
include_once("{$GLOBALS['srcdir']}/sql.inc");
include_once("{$GLOBALS['srcdir']}/api.inc");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.report.php");
include_once("{$GLOBALS['srcdir']}/wmt/wmt.forms.php");

if (!function_exists("quest_result_report")) { // prevent redeclarations

function quest_result_report($pid, $encounter, $cols, $id) {
	$form_name = 'quest_result';
	$form_table = 'form_quest_result';
	$order_name = 'quest_order';
	$order_table = 'form_quest_order';
	$item_name = 'quest_result_item';
	$item_table = 'form_quest_result_item';
	$form_title = 'Quest Lab Results';

	// Retrieve form content
	try {
		$result_data = new wmtForm($form_name,$id);
		$order_data = new wmtForm($order_name,$result_data->request_id);
	}
	catch (Exception $e) {
		print "THERE WAS AN ERROR RETRIEVING THESE RECORDS";
		exit;
	}
	
	$item_list = array();
	$query = "SELECT  * FROM form_quest_result_item WHERE parent_id = '$id' AND observation_value != 'DNR' ORDER BY id";
	$result = sqlStatement($query);
	while ($row = sqlFetchArray($result)) {
		$item_list[] = new wmtForm($item_name,$row['id']); // retrieve the data
	}

	// Report outter frame
	print "<link rel='stylesheet' type='text/css' href='../../forms/quest_order/style_wmt.css' />";
	print "<div class='wmtReport'>\n";
	print <<<EOT
<style>
@font-face {
    font-family: 'VeraSansMono';
    src: url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-webfont.eot');
    src: url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-webfont.eot?#iefix') format('embedded-opentype'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-webfont.woff') format('woff'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-webfont.ttf') format('truetype'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-webfont.svg#BitstreamVeraSansMonoRoman') format('svg');
    font-weight: normal;
    font-style: normal;
}
.mono { font-family: VeraSansMono, Arial, sans-serif }

@font-face {
    font-family: 'VeraSansMonoBold';
    src: url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-Bold-webfont.eot');
    src: url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-Bold-webfont.eot?#iefix') format('embedded-opentype'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-Bold-webfont.woff') format('woff'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-Bold-webfont.ttf') format('truetype'),
         url('{$GLOBALS['webroot']}/library/quest/fonts/VeraMono-Bold-webfont.svg#BitstreamVeraSansMonoBold') format('svg');
    font-weight: normal;
    font-style: normal;
}
.monoBold { font-family: VeraSansMonoBold, Arial, sans-serif }
</style>
EOT;
	print "<table class='wmtFrame' cellspacing='0' cellpadding='3'>\n";

	// Status header
	$content = "";
	$status = ($result_data->lab_status == 'CM')?'Final':'Partial';
	$priority = ($result_data->priority)?$result_data->priority:'n';
	if ($status || $priority) {
		$content .= "<tr><td colspan='4'>\n";
		$content .= "<table class='wmtStatus' style='margin-bottom:10px'><tr>";
		$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Status:</td>";
		$content .= "<td class='wmtOutput'>" . $status . "</td>";
		$content .= "<td class='wmtLabel' style='width:50px;min-width:50px'>Priority:</td>";
		$content .= "<td class='wmtOutput'>" . ListLook($priority, 'Form_Priority') . "</td>\n";
		$content .= "</tr></table></td></tr>\n";
	}
//  NOTE!! different status codes so can't use default print routine
//	$content = do_status($order_data->status, $order_data->priority);
	if ($content) print $content;
	
	// Order summary
	$content = '';
	$content .= do_columns(date('Y-m-d',strtotime($result_data->specimen_datetime)),'Order Date',date('Y-m-d',strtotime($result_data->result_datetime)),'Result Date');
	$content .= do_columns($result_data->request_order,'Requestion', ($result_data->lab_status == 'CM')? 'FINAL' : 'PARTIAL','Lab Status');
	$content .= do_columns(UserIdLook($result_data->request_provider),'Ordering Provider', UserIdLook($result_data->reviewed_id),'Reviewed By');
	if ($order_data->request_provider != $order_data->user) {
		$content .= do_columns(UserLook($order_data->user),'Entering Clinician', UserIdLook($result_data->notified_id),'Notification By');
	}
	$content .= do_columns('BLANK','', $result_data->notified_person, 'Person Notified');
	$content .= do_line("<div style='white-space:pre-wrap'>$result_data->lab_notes</div>",'Result Notes');
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
	$collected = ($result_data->specimen_datetime == 0)? '' : date('Y-m-d h:i A',strtotime($result_data->specimen_datetime));
	$pending = ($order_data->order0_pending == 0)? '' : date('Y-m-d h:i A',strtotime($order_data->order0_pending));
	
	if ($order_data->order0_psc) {
		$content .= do_columns('YES','PSC Hold Order',$pending,'Scheduled Date');
	}
	else {
		$content .= do_columns('YES','Sample Collected',$collected,'Collection Date');
		if ($order_data->id)
			$content .= do_columns(($order_data->order0_fasting)?'YES':'NO','Patient Fasting',$order_data->order0_duration,'Duration (hours)');
	}
	$content .= do_break();
	
//	$content .= do_line($result_data->lab_number,'Specimen');
//	$specimen = ($result_data->specimen_datetime == 0)? '' : date('Y-m-d',strtotime($result_data->specimen_datetime));
//	$specimen .= " ";
//	$specimen .= ($result_data->specimen_datetime == 0)? '' : date('h:ia',strtotime($result_data->specimen_datetime));
//	$content .= do_line($specimen,"Collected Date"); 
		
	$received = ($result_data->received_datetime == 0)? '' : date('Y-m-d',strtotime($result_data->received_datetime));
	$received .= " ";
	$received .= ($result_data->received_datetime == 0)? '' : date('h:i A',strtotime($result_data->received_datetime));
	$content .= do_columns($result_data->lab_number,'Specimen', $received,"Received Date"); 
		
	do_section($content, 'Specimen Information');
	
	echo "<style>div.wmtReport table td.wmtLabel, .wmtPrint .wmtLabel { white-space:normal }</style>";
	
	$legend = false;
	$newRow = '';
	$lastCode = '';
	$lastComp = '';
	$content = ''; // fresh section
	foreach ($item_list as $item_data) {
		$need_blank = false;
		
		if ($item_data->test_code != $lastCode) {
			if ($lastCode) {
				$content .= do_break();
			}
			$lastCode = $item_data->test_code;
			$content .= "<tr><td colspan='4'><h3 style='margin-bottom:0'>".$item_data->test_code." - ".$item_data->test_text."</h3></td></tr>\n";
			$legend = false;
		}
	
		if ($item_data->component_code != $lastComp) {
			$lastType = false;
			$lastComp = $item_data->component_code;
			if ($item_data->component_code) {
				$content .= "<tr><td colspan='4' style='padding-left:20px'><h4 style='margin-bottom:0'>".$item_data->component_text."</h4></td></tr>\n";
				$legend = false;
			}
		}
	
		if (!$legend) {
			$legend = true;
			$content .= "<tr><td width='200px'></td><td style='padding-left:10px'><small><b>OBSERVED RANGE</b></small></td><td><small><b>REFERENCE RANGE</b></small></tr>\n";
		}
		
		$abnormal = '';
		$hilite = '';
		if ($item_data->observation_abnormal == 'L') $abnormal = '  [ LOW ]';
		if ($item_data->observation_abnormal == 'LL') $abnormal = '  [ PANIC LOW ]';
		if ($item_data->observation_abnormal == 'H') $abnormal = '  [ HIGH ]';
		if ($item_data->observation_abnormal == 'HH') $abnormal = '  [ PANIC HIGH ]';
		if ($item_data->observation_abnormal == 'A') $abnormal = '  [ ABNORMAL ]';
		if ($item_data->observation_abnormal == 'AA') $abnormal = '  [ VERY ABNORMAL ]';
		if ($abnormal) $hilite = "color:#c00;";
		
		$newRow = "<tr style='".$hilite."'><td class='wmtLabel' style='width:200px'>".str_replace(':','',$item_data->observation_label).":</td>";
		
		if ($item_data->observation_value || $item_data->observation_units) {
			if (!$abnormal) $newRow .= "<td class='wmtOutput' style='width:400px;padding-left:10px'>".$item_data->observation_value." ".$item_data->observation_units."\n";
			if ($abnormal) $newRow .= "<td class='wmtOutput' style='".$hilite."width:400px;white-space:pre-wrap'>".$abnormal." ".$item_data->observation_value."</td>\n";
			if ($item_data->observation_range) {
				$newRow .= "<td class='wmtOutput mono' style='".$hilite."white-space:pre-wrap'>".$item_data->observation_range." ".$item_data->observation_units."</td></tr>";
			}
			else {
				$newRow .= "<td></td></tr>\n";
			}
			if ($item_data->observation_notes) $newRow .= "<tr><td></td>";
		}
		else {
//			$newRow .= "</tr><tr><td></td>\n";
		}
					
		if ($item_data->observation_notes) $newRow .= "<td class='wmtOutput mono' colspan='3' style='".$hilite."white-space:pre-wrap;padding-left:10px'>".$item_data->observation_notes."</td></tr>\n";
					
		if ($newRow) $content .= $newRow;
		
	}
	
	do_section($content, 'Lab Results - '.$result_data->request_order);
	
	
	print "</td></tr></table>";
	
	
} // end declaration 

} // end if function

?>
