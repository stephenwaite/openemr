<?php
/** **************************************************************************
 *	QUEST_ORDER/PROCESS.PHP
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
require_once("{$GLOBALS['srcdir']}/options.inc.php");
require_once("{$GLOBALS['srcdir']}/lists.inc");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestOrderClient.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestModelHL7v2.php");

$document_url = $GLOBALS['web_root']."/controller.php?document&retrieve&patient_id=".$pid."&amp;document_id=";

function getCreds($id) {
	if (!$id) return;
	
	$query = "SELECT * FROM users WHERE id = '".$id."' LIMIT 1";
	$user = sqlQuery($query);
	return $user['npi']."^".$user['lname']."^".$user['fname']."^".$user['mname']."^^^^^NPI";
}

// get a handle to processor
$client = new QuestOrderClient($quest_siteid);

// get a new quest object
$request = new Request_HL7v2();

// add request information
$request->request_number = $order_data->order0_number; // MSH.10, ORC.02, OBR.02

$request->pid = $order_data->pid; // PID.02

$request->name = $order_data->pat_last . "^";
$request->name .= $order_data->pat_first . "^"; // PID.05
$request->name .= $order_data->pat_middle;

$request->dob = date('Ymd',strtotime($order_data->pat_DOB)); // PID.07
$request->sex = substr($order_data->pat_sex, 0, 1); // PID.08
$request->ss = $order_data->pat_ss; // PID.19

$request->address = $order_data->pat_street . "^^"; // PID.11
$request->address .= $order_data->pat_city . "^";
$request->address .= $order_data->pat_state . "^";
$request->address .= $order_data->pat_zip . "^";

$request->phone = preg_replace("/[^0-9,.]/", "", $order_data->pat_phone); // PID.13

$request->datetime = date('YmdHis',strtotime($order_data->request_datetime)); // MSH.07
$request->application = ""; // PSC or blank

$request->verified_id = getCreds($_SESSION['authId']); // ORC.11
$request->provider_id = getCreds($order_data->request_provider); // ORC.12

$request->facility = $quest_siteid;

if ($order_data->order0_fasting) {
	$fasting = "PATIENT FASTING ";
	if ($order_data->order0_duration) $fasting .= ": ".$order_data->order0_duration." HOURS";
	$request->fasting = $fasting; // OBR - NTE
}

if ($order_data->order0_psc) {
	$request->application = "PSC"; // PSC or blank
}

$request->order_notes = $order_data->order0_notes; // PID - NTE

// add guarantor
$request->guarantor = $order_data->guarantor_last . "^";
$request->guarantor .= $order_data->guarantor_first . "^"; // PID.05
$request->guarantor .= $order_data->guarantor_middle;
$request->guarantor_phone = preg_replace("/[^0-9,.]/", "", $order_data->guarantor_phone);


$request->guarantor_address = $order_data->guarantor_street . "^^"; // PID.11
$request->guarantor_address .= $order_data->guarantor_city . "^";
$request->guarantor_address .= $order_data->guarantor_state . "^";
$request->guarantor_address .= $order_data->guarantor_zip . "^";


// create insurance records
$ins_primary_type = 0; // self insured
if ($order_data->ins_primary == 'No Insurance' || $order_data->ins_primary == '' || $order_data->ins_primary == 'Self Insured') {
	$ins = new Insurance_HL7v2();
	
	$ins->set_id = '1'; // IN1.02
	$ins->relation = 1; // IN1.17 (SELF)
//RON-20130429	$ins->company_name = 'Self Insured'; // IN1.04
	$ins->company_name = ''; // IN1.04
//RON-20130429		$ins->address = $request->address;
	$ins->address = '';
	$ins->type = 'P'; // IN1.47

	// create hl7 segment
	$client->addInsurance($ins);
}
else { // insurance found
//	$ins_list = array('ins_primary','ins_secondary','ins_tertiary');
	$ins_list = array('ins_primary','ins_secondary'); // Quest supports 2 only!!
	
	$seq = 0;
	foreach ($ins_list AS $ins_key) {
		// process insurance
		if ($order_data->$ins_key) { // found insurance
			// retrieve insurance information
			$key = $ins_key."_id";
			$ins_data = new wmtInsurance($order_data->$key);
			if ($ins_key == "ins_primary") $ins_primary_type = $ins_data->plan_type; // save for ABN check
		
			// build insurance record
			$ins = new Insurance_HL7v2();
			
			$seq++;
			$ins->set_id = $seq; // IN1.01 - sequence
			$ins->plan = $ins_data->plan_name; // IN1.08
			$ins->group = $ins_data->group_number; // IN1.08
			$ins->policy = $ins_data->policy_number; // IN1.36
					
			$ins->subscriber = $ins_data->subscriber_lname . "^";
			$ins->subscriber .= $ins_data->subscriber_fname . "^"; // IN1.16
			$ins->subscriber .= $ins_data->subscriber_mname;
				
			$relation = 8; // dependent
			if ($ins_data->subscriber_relationship == 'self') $relation = 1;
			if ($ins_data->subscriber_relationship == 'spouse') $relation = 2;
			$ins->relation = $relation; // IN1.17
	
			$ins->address = $ins_data->subscriber_street . "^^"; // IN1.19
			$ins->address .= $ins_data->subscriber_city . "^";
			$ins->address .= $ins_data->subscriber_state . "^";
			$ins->address .= $ins_data->subscriber_postal_code . "^";
			
				
			$ins->company_name = $ins_data->company_name; // IN1.04
			$ins->company_address = $ins_data->line1 . "^"; // IN1.05
			$ins->company_address .= $ins_data->line2 . "^";
			$ins->company_address .= $ins_data->city . "^";
			$ins->company_address .= $ins_data->state . "^";
			$ins->company_address .= $ins_data->zip . "^";
			
			$ins->type = 'T'; // IN1.47
					
			// create hl7 segment
			$client->addInsurance($ins);
		}
	}
	
}

// get date/time of specimen
$sample = $order_data->order0_datetime;
if ($order_data->order0_psc) $sample = $order_data->order0_pending;
$sample_datetime = ($sample)? date('YmdHis',strtotime($sample)) : ''; // OBR.07

// create orders (loop)
$seq = 1;
foreach ($item_list as $item_data) {
	$order = new Order_HL7v2();
	
	$order->set_id = $seq++; // OBR.01 - sequence
	$order->request_control = "NW"; // ORC.01 - CDC defined O119
	$order->request_number = $order_data->order0_number; // ORC.02
	$order->service_id = "^^^" . $item_data->test_code . "^" . $item_data->test_text; // OBR.04 (^^^6399^CBC)

	$order->specimen_datetime = $sample_datetime; // OBR.07
	
	$dx_count = 1;
	for ($d = 0; $d < 10; $d++) {
		$key = "dx".$d."_code";
		$dx_code = $order_data->$key;
		if ($dx_code) {
			$key = "dx".$d."_text";
			$dx_text = $order_data->$key;
			$dx_data = new Diagnosis_HL7v2();
			$dx_data->set_id = $dx_count++;
			$dx_data->diagnosis_code = str_replace(".", "", $dx_code);
			$dx_data->diagnosis_text = mysql_real_escape_string($dx_text);
			$order->diagnosis[] = $dx_data;
		}
	}
	
	$aoe_count = 1;
	for ($a = 0; $a < 20; $a++) {
		$key = "aoe".$a."_code";
		$aoe_code = $item_data->$key;
		if ($aoe_code) {
			$key = "aoe".$a."_label";
			$aoe_label = $item_data->$key;
			$key = "aoe".$a."_text";
			$aoe_text = $item_data->$key;
			$aoe_data = new Aoe_HL7v2();
			$aoe_data->set_id = $aoe_count++;
			$aoe_data->observation_code = $aoe_code;
			$aoe_data->observation_label = mysql_real_escape_string($aoe_label);
			$aoe_data->observation_text = mysql_real_escape_string($aoe_text);
			$order->aoe[] = $aoe_data;
		}
	}
	
	$client->addOrder($request,$order);
}

$reload_url = $rootdir.'/patient_file/encounter/view_form.php?formname=quest_order&id=';
?>
	
	<form method='post' action="" id="order_process" name="order_process" > 
		<table class="bgcolor2" style="width:100%;height:100%">
			<tr>
				<td colspan="2">
					<h2>Order Processing</h2>
				</td>
			</tr>
			<tr>
				<td colspan="2" style="padding-bottom:20px">
					<pre>
<?php 
$client->buildRequest($request);
//$client->validateOrder();
//$client->submitOrder();
//$doc_list = $client->getOrderDocuments($order_data->pid,'ABN');
if ($ins_primary_type == 2) { // medicare
	$doc_list = $client->getOrderDocuments($order_data->pid,'ABN');
	if (count($doc_list)) $order_data->order0_abn_id = $doc_list[0]->get_id();
}
	
$doc_list = $client->getOrderDocuments($order_data->pid,'REQ');

if (count($doc_list)) { // got a document so suceess
	$order_data->status = 'p'; // processed
	$order_data->order0_req_id = $doc_list[0]->get_id();
	$order_data->request_processed = date('Y-m-d H:i:s');
	$order_data->request_facility = $quest_siteid;
	$order_data->update();	
}
?>
					</pre>
				</td>
			</tr>
<?php 
if (count($doc_list)) { // no documents order failed
?>
			<tr>
				<td class="wmtLabel" colspan="2" style="padding-bottom:10px;padding-left:8px">
					Label Printer: 
					<select class="nolock" id="labeler" name="labeler" style="margin-right:10px">
						<?php ListSel($_SERVER['REMOTE_ADDR'], 'Quest_Label_Printers')?>
						<option value='file'>Print To File</option>
					</select>
					Quantity:
					<select class="nolock" name="count" style="margin-right:10px">
						<option value="1"> 1 </option>
						<option value="2"> 2 </option>
						<option value="3"> 3 </option>
						<option value="4"> 4 </option>
						<option value="5"> 5 </option>
					</select>

					<input class="nolock" type="button" tabindex="-1" onclick="printLabels(1)" value="Print Labels" />
				</td>
			</tr>
<?php 
} // end of failed test
?>				
			<tr>
				<td>
<?php if ($order_data->order0_abn_id) { ?>
					<input type="button" class="wmtButton" onclick="location.href='<?php echo $document_url . $order_data->order0_abn_id ?>';return false" value="ABN print" />
<?php } ?>				
<?php if ($order_data->order0_req_id) { ?>
					<input type="button" class="wmtButton" onclick="location.href='<?php echo $document_url . $order_data->order0_req_id ?>';return false" value="REQ print" />
<?php } ?>
				</td>
				<td style="text-align:right">
					<input type="button" class="wmtButton" onclick="doClose()" value="close" />
					<input type="button" class="wmtButton" onclick="doReturn(<?php echo $form_id ?>)" value="return" />
				</td>
			</tr>
		</table>
	</form>