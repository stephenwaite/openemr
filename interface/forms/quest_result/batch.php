<?php
/** **************************************************************************
 *	QUEST_RESULTS/BATCH.PHP
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
 *  @subpackage results
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
error_reporting(E_ALL ^ E_NOTICE);
ini_set('error_reporting', E_ALL ^ E_NOTICE);
ini_set('display_errors',1);

$ignoreAuth=true; // signon not required!!

// ENVIRONMENT SETUP
if (defined('STDIN')) {
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
 
$BROWSER = ($_POST['browser']) ? $_POST['browser'] : FALSE; // never allow browser from command line
$DEBUG = ($_POST['form_debug']) ? $_POST['form_debug'] : $_GET['debug'];
$FROM = ($_POST['form_from_date']) ? $_POST['form_from_date'] : $_GET['from'];
$THRU = ($_POST['form_to_date']) ? $_POST['form_to_date'] : $_GET['thru'];
$SITE = ($_SESSION['site_id']) ? $_SESSION['site_id'] : $_GET['site'];

$here = dirname(dirname(dirname(__FILE__)));

require_once($here."/globals.php");
require_once("{$GLOBALS['srcdir']}/options.inc.php");
require_once("{$GLOBALS['srcdir']}/lists.inc");
require_once("{$GLOBALS['srcdir']}/forms.inc");
include_once("{$GLOBALS['srcdir']}/pnotes.inc");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestResultClient.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestModelHL7v2.php");
require_once("{$GLOBALS['srcdir']}/quest/QuestParserHL7v2.php");
require_once("{$GLOBALS['srcdir']}/classes/Document.class.php");

$QUEST_PID = FALSE;
$QUEST_ID = FALSE;

// GET DEFAULT SITE ID
$query = "SELECT title FROM list_options ";
$query .= "WHERE list_id = 'Quest_Site_Identifiers' AND is_default = 1 LIMIT 1";
if ($dummy = sqlQuery($query)) $GLOBALS['lab_quest_siteid'] = $dummy['title'];	
	
// GET QUEST DUMMY PATIENT
$query = "SELECT pid FROM patient_data WHERE lname = '#QUESTLABS#' LIMIT 1";
if ($dummy = sqlQuery($query)) $QUEST_PID = $dummy['pid'];

// GET QUEST DUMMY PROVIDER
$query = "SELECT id FROM users WHERE username = 'quest' LIMIT 1";
if ($dummy = sqlQuery($query)) $QUEST_ID = $dummy['id'];

// VALIDATE INSTALL
$invalid = "";
if (!$QUEST_PID) $invalid .= "Missing QUEST Patient Record\n";
if (!$QUEST_ID) $invalid .= "Missing QUEST User/Provider Record\n";
if (!$GLOBALS["lab_quest_enable"]) $invalid .= "Quest Interface Not Enabled\n";
if (!$GLOBALS["lab_quest_catid"] > 0) $invalid .= "No Quest Document Category\n";
if (!$GLOBALS["lab_quest_facilityid"]) $invalid .= "No Receiving Facility Identifier\n";
if (!$GLOBALS["lab_quest_siteid"]) $invalid .= "No Sending Clinic Identifier\n";
if (!$GLOBALS["lab_quest_username"]) $invalid .= "No Quest Username\n";
if (!$GLOBALS["lab_quest_password"]) $invalid .= "No Quest Password\n";
if (!file_exists("{$GLOBALS["srcdir"]}/wmt")) $invalid .= "Missing WMT Library\n";
if (!file_exists("{$GLOBALS["srcdir"]}/quest")) $invalid .= "Missing Quest Library\n";
if (!file_exists("{$GLOBALS["srcdir"]}/tcpdf")) $invalid .= "Missing TCPDF Library\n";
if (!extension_loaded("curl")) $invalid .= "CURL Module Not Enabled\n";
if (!extension_loaded("xml")) $invalid .= "XML Module Not Enabled\n";
if (!extension_loaded("sockets")) $invalid .= "SOCKETS Module Not Enabled\n";
if (!extension_loaded("soap")) $invalid .= "SOAP Module Not Enabled\n";
if (!extension_loaded("openssl")) $invalid .= "OPENSSL Module Not Enabled\n";

if ($invalid) { ?>
<html><head></head><body>
<h1>Quest Diagnostic Interface Not Available</h1>
The interface is not enabled, not properly configured, or required components are missing!!
<br/><br/>
For assistance with implementing this service contact:
<br/><br/>
<a href="http://www.williamsmedtech.com/page4/page4.html" target="_blank"><b>Williams Medical Technologies Support</b></a>
<br/><br/>
<table style="border:2px solid red;padding:20px"><tr><td style="white-space:pre;color:red"><h3>DEBUG OUTPUT</h3><?php echo $invalid ?></td></tr></table>
</body></html>
<?php
exit; 
}

// special pnote insert function
function questPnote($pid, $newtext, $assigned_to = '', $datetime = '') {
	$message_sender = 'quest';
	$message_group = 'Default';
	$authorized = '0';
	$activity = '1';
	$title = 'Lab Results';
	$message_status = 'New';
	if (empty($datetime)) $datetime = date('Y-m-d H:i:s');

	$body = date('Y-m-d H:i') . ' (Quest Labs to '. $assigned_to;
	$body .= ') ' . $newtext;

	return sqlInsert("INSERT INTO pnotes (date, body, pid, user, groupname, " .
			"authorized, activity, title, assigned_to, message_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
			array($datetime, $body, $pid, $message_sender, $message_group, $authorized, $activity, $title, $assigned_to, $message_status) );
}

// set process defaults
$order_table = "form_quest_order";
$order_name = "quest_order";
$result_title = "Quest Results - ";
$result_table = "form_quest_result";
$result_name = "quest_result";
$item_table = "form_quest_result_item";
$item_name = "quest_result_item";

// get the table column names
$fields = sqlListFields($result_table);
$fields = array_slice($fields,7);

// get a handles to processors
$client = new QuestResultClient();
$ack_client = new QuestResultClient();

// initialize
$last_pid = null;
$last_order = null;

$results = array(); // to collect result records
$acks = array(); // to collect ack records

if ($BROWSER) { // debug output to html page
?>
<html>
	<head>
		<?php html_header_show();?>
		<title><?php echo $form_title; ?></title>

		<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
		<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.css" media="screen" />
		<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/interface/forms/quest_order/style_wmt.css" media="screen" />
		
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-1.7.2.min.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-ui-1.10.0.custom.min.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/common.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.pack.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/dialog.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/overlib_mini.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/textformat.js"></script>
	
	</head>
	
	<body>
		<table style="width:100%">
			<tr>
				<td colspan="2">
					<h2>Quest Result Processing</h2>
				</td>
			</tr>
			<tr>
				<td colspan="2">
					<pre>
<?php 
} // end of debug output header

echo "START OF BATCH PROCESSING: ".date('Y-m-d H:i:s')."\n";

if (!$FROM || !$THRU) { // must have both
	$FROM = FALSE;
	$THRU = FALSE;
}

if ($FROM) {
	if ($from_date = strtotime($FROM)) {
		$FROM = date('m/d/Y', $from_date);
	}
	else {
		echo "  -- Invalid from date: ($FROM) IGNORING DATES \n";
		$FROM = FALSE;
		$THRU = FALSE;
	}
}

if ($THRU) {
	if ($thru_date = strtotime($THRU)) {
		$THRU = date('m/d/Y', $thru_date);
	}
	else {
		echo "  -- Invalid to date: ($THRU) IGNORING DATES \n";
		$FROM = FALSE;
		$THRU = FALSE;
	}
}

$response_id = '';

if ($FROM && $THRU) {
	echo "  -- Reprocessing from: $FROM to: $THRU \n";
	$client->buildRequest(100,$FROM,$THRU);
}
else {
	$client->buildRequest(100);
}

$messages = $client->getResults($DEBUG);

echo "\n\n";

foreach ($messages as $message) {
	ob_start(); // buffer the output
	
	// walsh custom
	$message->pid = str_replace('.', '0', $message->pid);
	
	$response_id = $message->response_id; // the same value in all messages
	if ($DEBUG) {	
		echo "<hr/>";
		echo "Processing Results for Patient: ".$message->name[0].", ".$message->name[1]." ".$message->name[2]."\n";
		echo "Patient PID: ".$message->pid."\n";
		echo "Patient DOB: ".date('Y-m-d',strtotime($message->dob))."\n";
		echo "Order Number: $message->order_number \n";
		echo "Provider: ".$message->provider_id[0]." - ".$message->provider_id[1]." ".$message->provider_id[2]."\n";
		echo "Facility: $message->facility \n\n";
	}

	$pid = 0;
	$provider_id = 0;
	$request_id = 0;
	$result_id = 0;
	$site_id = 0;
	$encounter = 0;
	$request_handling = 0;
	$matched = FALSE;
	$order_data = FALSE;
	
	// match order
	$query = "SELECT ot.id AS request_id, ot.pat_first, ot.pat_middle, ot.pat_last, ot.request_provider, ot.request_handling, forms.encounter FROM $order_table ot ";
	$query .= "LEFT JOIN forms ON ot.id = forms.form_id AND forms.formdir = 'quest_order' ";
	$query .= "WHERE ot.pid = '$message->pid' AND ot.pat_DOB = '".date('Y-m-d',strtotime($message->dob))."' AND ot.order0_number = '$message->order_number' AND ot.request_facility = '$message->facility' ";
	$query .= "LIMIT 1";
	if ($parent = sqlQuery($query)) {
		if ($parent['request_id'] && $parent['encounter']) {
			$request_id = $parent['request_id'];
			$order_data = new wmtForm($order_name, $request_id);
			$request_handling = $parent['request_handling'];
			$encounter = $parent['encounter'];
			$matched = TRUE;
		}
	}
	// RON - 20130925 - Fix for possible missing records
	//else {
	if (!$matched) {
		echo "WARNING: NO MATCHING ORDER FOUND FOR THESE RESULTS \n";
	}
	
	// find previous result (if there is one)
	$query = "SELECT id FROM $result_table ";
	$query .= "WHERE request_order = '$message->order_number' AND lab_number = '$message->lab_number' ";
	$query .= "LIMIT 1";
	if ($result = sqlQuery($query)) {
		$result_id = $result['id'];
	}
	
	// validate pid
	$query = "SELECT pid, providerID, lname, fname, mname FROM patient_data WHERE pid = '".$message->pid."' AND DOB = '".date('Y-m-d',strtotime($message->dob))."' ";
	if ($patient = sqlQuery($query)) {
		$pid = $patient['pid'];
	}
	else {
		$matched = FALSE;
		$pid = $QUEST_PID; // if no valid patient use Quest dummy
		echo "WARNING: NO MATCHING PATIENT FOUND FOR THIS PID \n";
	}
		
	// validate result provider
	if ($message->provider_id[0]) { // 2013-05-07 CRISWELL - CATCH BLANK NPI NUMBER
		$query = "SELECT id, facility_id, username FROM users WHERE npi = '".$message->provider_id[0]."' ";
		if ($provider = sqlQuery($query)) {
			$provider_id = $provider['id']; // use result provider if found
			$provider_facility = $provider['facility_id'];
			$provider_username = $provider['username'];
		}
	}
	if (!$provider_id) { // use patient default provider
		$query = "SELECT id, facility_id, username FROM users WHERE id = '".$patient['providerID']."' ";
		if ($provider = sqlQuery($query)) {
			$provider_id = $provider['id']; // patient default provider
			$provider_facility = $provider['facility_id'];
			$provider_username = $provider['username'];
		}
	}
	if (!$provider_id) { // use quest dummy provider
		$provider_id = $QUEST_ID;
		$provider_username = 'quest';
	}

	// validate facility
	$query = "SELECT o.option_id, o.title, f.name, f.id  FROM list_options o, facility f ";
	$query .= "WHERE o.list_id = 'Quest_Site_Identifiers' AND o.title = '$message->facility' ";
	$query .= "AND o.option_id = f.id ";
	if ($site = sqlQuery($query)) {
		$site_id = $site['id'];
		$site_name = $site['name'];
		$site_code = $site['title'];
	}
	else {
		$site_name = 'UNKNOWN';
		$query = "SELECT f.name, f.id, o.title FROM facility f ";
		$query .= "LEFT JOIN list_options o ON o.option_id = f.id ";
		$query .= "WHERE f.id = '$provider_facility' ";
		if ($site = sqlQuery($query)) {
			$site_id = $site['id'];
			$site_name = $site['name'];
			$site_code = $site['npi'];
		}
	}
	
	// no order or previous result but found patient
	if ($pid != $QUEST_PID && !$request_id && !$result_id) {
		// build dummy encounter for this patient/result
		$provider_id = ($provider_id) ? $provider_id : $QUEST_ID;
		$conn = $GLOBALS['adodb']['db'];
		$encounter = $conn->GenID("sequences");
		addForm($encounter, "QUEST RESULT ENCOUNTER",
			sqlInsert("INSERT INTO form_encounter SET " .
				"date = '$message->datetime', " .
				"onset_date = '$message->datetime', " .
				"reason = 'GENERATED ENCOUNTER FOR QUEST LAB RESULT', " .
				"facility = '" . add_escape_custom($site_name) . "', " .
				"pc_catid = '', " .
				"facility_id = '$site_id', " .
				"billing_facility = '', " .
				"sensitivity = 'normal', " .
				"referral_source = '', " .
				"pid = '$pid', " .
				"encounter = '$encounter', " .
				"provider_id = '$provider_id'"),
			"newpatient", $pid, 0, date('Y-m-d'), 'quest');
		$matched = TRUE;
	}
	
	/* ---- REMOVED 2013-03-27 CRISWELL
	// order not found
	if (!$request_id) {
		if ($DEBUG) {
			// display final results
			echo "<hr/>";
			echo "STORED RECORDS: 0";
			echo "\nTOTAL DOCUMENTS: 0";
			echo "\nACKNOWLEDGMENT: [CR] No matching request found (ORDER:".$message->order_number." - PID:".$message->pid." - DOB:".$message->dob.")";
			echo "<hr/><hr/>";
		}
		else {
			echo "DATE: ".date('Y-m-d H:i:s')." -- ORDER: ".$message->order_number." -- PID: ".$message->pid." -- ERROR: No matching order found!!\n";
		}
		$acks[] = $client->buildResultAck($message->message_id,"No matching request found (ORDER:".$message->order_number." - PID:".$message->pid." - DOB:".$message->dob.")");
		continue;		
	}
	*/
	
	// validate the respository directory
	$repository = $GLOBALS['oer_config']['documents']['repository'];		
	$file_path = $repository . preg_replace("/[^A-Za-z0-9]/","_",$pid) . "/";
	if (!file_exists($file_path)) {
		if (!mkdir($file_path,0700)) {
			throw new Exception("The system was unable to create the directory for this upload, '" . $file_path . "'.\n");
		}
	}
		
	$docnum = 0;
	$documents = array();
	// store all of the documents
	foreach ($message->documents as $document) {
		if ($document->documentData) {
			$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
			$docName = $message->order_number . "_" . $message->lab_number . "_RESULT";
			
			$docnum++;
			$file = $docName."_".$unique.".pdf";
			while (file_exists($file_path.$file)) { // don't overlay duplicate file names
				$docName = $message->order_number . "_" . $message->lab_number . "_RESULT_".$docnum++;
				$file = $docName."_".$unique.".pdf";
			}
			
			if (($fp = fopen($file_path.$file, "w")) == false) {
				throw new Exception('Could not create local file ('.$file_path.$file.')');
			}
			fwrite($fp,$document->documentData);
			fclose($fp);
				
			if ($DEBUG) echo "\nDocument Name: " . $file;
				
			// register the new document
			$d = new Document();
			$d->name = $docName;
			$d->storagemethod = 0; // only hard disk sorage supported
			$d->url = "file://" .$file_path.$file;
			$d->mimetype = "application/pdf";
			$d->size = filesize($file_path.$file);
			$d->owner = $QUEST_ID;
			$d->hash = sha1_file( $file_path.$file );
			$d->type = $d->type_array['file_url'];
			$d->set_foreign_id($pid);
			$d->persist();
			$d->populate();
				
			$documents[] = $d; // save for later
	
			// update cross reference
			$query = "REPLACE INTO categories_to_documents set category_id = '".$GLOBALS['lab_quest_catid']."', document_id = '" . $d->get_id() . "'";
			sqlStatement($query);
				
			if ($DEBUG) echo "\nDocument Completion: SUCCESS\n";
		}
	}
	
	// create or retrieve result record
	$result_data = new wmtForm($result_name, $result_id, TRUE); // will create if no id present
	
	// result form data
	$result_data->date = date('Y-m-d H:i:s');
	$result_data->user = 'quest';
	$result_data->pid = $pid; // store under QUEST if no valid patient
	$result_data->groupname = 'Default';
	$result_data->authorized = 0;
	$result_data->priority = 'n';
	
	$result_data->request_id = $request_id;
	$result_data->request_pid = $pid;
	$result_data->request_DOB = $message->dob;
	$result_data->request_pat_last = $message->name[0];
	$result_data->request_pat_first = $message->name[1];
	$result_data->request_pat_middle = $message->name[2];
	$result_data->request_provider = $provider_id;
	$result_data->request_npi = $message->provider_id[0];
	$result_data->request_control = $message->order_control;
	$result_data->request_order = $message->order_number;
	$result_data->request_facility = $site_id;
		
	$result_data->lab_number = $message->lab_number;
	$result_data->lab_status = $message->lab_status;
	
	$result_data->status = 'x'; // assume partial results
	if ($message->lab_status == 'CM') $result_data->status = 'z'; // final
	if (!$matched) {
		$result_data->request_id = 0;
		$result_data->status = 'u'; // orphan
	}

	$result_data->document_id = $documents[0]->get_id(); // only saving first document
	
	$result_data->lab_notes = ''; // combine notes

	if ($message->notes) {
		foreach ($message->notes AS $note) {
			if ($result_data->lab_notes) $result_data->lab_notes .= "\n";
			$result_data->lab_notes .= mysql_real_escape_string($note->comment);
		}
	}
	 
	$items = array(); // for new test
	
	// poor naming... ORDERS == TESTS ORDERED
	foreach ($message->orders as $order) {
		if ($DEBUG) {
			echo "Order Control: $order->order_control \n";
			echo "Order Number: $order->order_number \n";
			echo "Lab Number: $order->lab_number \n";
			echo "Lab Status: $order->lab_status \n";
			echo "Test Ordered: ".$order->service_id[0]." - ".$order->service_id[1]."\n";
			if ($order->component_id) echo "Profile Component: ".$order->component_id[0]." - ".$order->component_id[4]."\n";
			echo "Specimen Date: ".date('Y-m-d H:i:s', strtotime($order->specimen_datetime))." \n";
			echo "Received Date: ".date('Y-m-d H:i:s', strtotime($order->received_datetime))." \n"; 
			echo "Result Date: ".date('Y-m-d H:i:s', strtotime($order->result_datetime))." \n";
			echo "Test Action: $order->action_code \n";
			echo "Result Status: $order->result_status \n\n";
		}
		
		$result_data->specimen_datetime = $order->specimen_datetime;
		$result_data->received_datetime = $order->received_datetime;
		$result_data->result_datetime = $order->result_datetime;
		
		$items_abnormal = 0;
		foreach ($order->results as $result) {
			
			// merge notes into a single field
			$notes = '';
			if ($result->notes) {
				foreach ($result->notes as $note) {
					if ($notes) $notes .= "\n";
					$notes .= mysql_real_escape_string($note->comment);
				}
			}
			
			if ($DEBUG) {
				echo "Value Type: $result->value_type \n";
				echo "Observation: ".$result->observation_id[4]." \n";
				echo "Observed Value: $result->observation_value \n";
				echo "Observed Units: $result->observation_units \n";
				echo "Observed Range: $result->observation_range \n";
				echo "Observed Status: $result->observation_status \n";
				echo "Observed Abnormal: $result->abnormal_flags \n";
				echo "Observed Date: " .date('Y-m-d H:i:s', strtotime($result->observation_datetime)). "\n";
				echo "NOTES:\n $notes";
				echo "<hr/>";
			}
				
			// generate the object
			$item_data = new wmtForm($item_name); // empty object

			// default form data
			$item_data->date = date('Y-m-d H:i:s');
			$item_data->user = 'quest';
			$item_data->pid = $pid; // store under QUEST if no valid patient
			$item_data->groupname = 'Default';
			$item_data->authorized = 0;
			$item_data->sequence = count($items);

			$item_data->test_code = mysql_real_escape_string($order->service_id[0]);
			$item_data->test_text = mysql_real_escape_string($order->service_id[1]);
			$item_data->component_code = mysql_real_escape_string($order->component_id[0]);
			$item_data->component_text = mysql_real_escape_string($order->component_id[4]);
				
			$item_data->observation_type = mysql_real_escape_string($result->value_type);
			$item_data->observation_label = mysql_real_escape_string(ltrim($result->observation_id[4]));
			$item_data->observation_value = mysql_real_escape_string($result->observation_value);
			$item_data->observation_units = mysql_real_escape_string($result->observation_units);
			$item_data->observation_range = mysql_real_escape_string($result->observation_range);
			$item_data->observation_status = mysql_real_escape_string($result->observation_status);
			$item_data->observation_abnormal = mysql_real_escape_string($result->observation_abnormal);
			$item_data->observation_datetime = $result->observation_datetime;
			$item_data->observation_notes = mysql_real_escape_string($notes);
			
			$items[] = $item_data;
			
			if ($item_data->observation_abnormal != 'N') $items_abnormal++;
		}
	}
	
	// now save all of the results
	if (count($items)) { // got results
		
		$result_form = '';
		$result_form = $result_title.$result_data->request_order." (".$result_data->lab_number.")";
		
		$result_data->result_abnormal = $items_abnormal;
		$result_data->result_handling = $request_handling;
		
		if ($result_id) { // have an existing record
			if ($result_data->result_notes) $result_data->result_notes = mysql_real_escape_string($result_data->result_notes)."\n";
			$result_data->result_notes .= "RESULTS REVISED: ".date('Y-m-d H:i:s')." - Previous review data cleared!!";
				
			$result_data->reviewed_id = '';
			$result_data->reviewed_datetime = 'NULL';
			$result_data->notified_id = '';
			$result_data->notified_datetime = 'NULL';
			$result_data->notified_person = '';
				
			$result_data->update();

			// remove existing detail records
			$query = "DELETE FROM $item_table WHERE parent_id = $result_id ";
			sqlStatement($query);
		}
		else { // need a new record
			$result_id = wmtForm::insert($result_data);
			if ($encounter) // only add form if matched to order or dummy encounter created
				addForm($encounter, $result_form, $result_id, $result_name, $pid, 0, 'NOW()', $provider_username);
		}
		
		// insert new item records
		foreach ($items as $record) {
			$record->parent_id = $result_id;
			wmtForm::insert($record);
		}
		
		// update status of order
		if ($order_data) {
			$order_data->status = 'g'; // results received for order
			$order_data->update();
		}
	}
	
	// if found, send them a message
	if ($provider_username && $provider_username != 'quest') {
		if ($patient) {
   			$link_ref = "../../forms/quest_result/update.php?id=$result_id&pid=".$pid."&enc=".$encounter;
			$note = "\n\nQuest lab results received for patient '".$patient['fname']." ".$patient['lname']."' (pid: ".$pid.") order number '".$message->order_number."'. ";
			$note .= "To review these results click on the following link: ";
  			$note .= "<a href='". $link_ref ."' target='_blank' class='link_submit' onclick='top.restoreSession()'>". $result_form ."</a>\n\n";
			questPnote($pid, $note, $provider_username);
		}
		else {
			$note = "Quest lab results received for an unknown patient. ";
			$note .= "\n\nThe information provided indicates the results are for patient '".$message->name[1]." ".$message->name[0]."' (pid: ".$message->pid.") ";
			$note .= "and order number '".$message->order_number."'. ";
			$note .= "Please use the Orphan Lab Results report to assign these results to a valid patient.\n\n";
			questPnote($pid, $note, $provider_username);
		}
	}
	

	// display results
	$doccnt = 0;
	foreach ($documents as $document) {
		$doccnt++;
		if ($DEBUG) {
			echo "Document Title: ".$document->name." \n";
			echo "Document link: /controller.php?document&retrieve&patient_id=".$pid."&document_id=".$document->get_id()." \n\n";
		}
	}

	// LAST... prepare acknowledgement
	$acks[] = $client->buildResultAck($message->message_id);
	
	if ($DEBUG) {
		// display final results
		echo "<hr/>";
		echo "STORED RECORDS: ".count($items); 
		echo "\nTOTAL DOCUMENTS: ".$doccnt; 
		echo "\nACKNOWLEDGMENT: [CA] Result processed (ORDER: ".$message->order_number." LAB: ".$message->lab_number.")"; 
		echo "<hr/><hr/>";
	}
	else {
		echo "DATE: ".date('Y-m-d H:i:s')." -- ORDER: ".$message->order_number." -- LAB: ".$message->lab_number." -- PID: ".$message->pid." -- DOCUMENTS: ".$doccnt." -- RESULTS: ".count($items)."\n";
	}
	
	$output = ob_get_flush();
	
	$query = "INSERT INTO form_quest_batch SET ";
	$query .= "date = '$result_data->date', ";
	$query .= "pid = '$message->pid', ";
	$query .= "user = '".$_SESSION['authUser']."', ";
	$query .= "groupname = 'Default', ";
	$query .= "authorized = 0, ";
	$query .= "activity = 1, ";
	$query .= "facility = '$result_data->request_facility', ";
	$query .= "order_number = '$result_data->request_order', ";
	$query .= "order_datetime = '$result_data->specimen_datetime', ";
	$query .= "provider_id = '$result_data->request_provider', ";
	$query .= "provider_npi = '$result_data->request_npi', ";
	$query .= "pat_dob = '$result_data->request_DOB', ";
	$query .= "pat_first = '".mysql_real_escape_string($result_data->request_pat_first)."', ";
	$query .= "pat_middle = '".mysql_real_escape_string($result_data->request_pat_middle)."', ";
	$query .= "pat_last = '".mysql_real_escape_string($result_data->request_pat_last)."', ";
	$query .= "lab_number = '$result_data->lab_number', ";
	$query .= "lab_status = '$result_data->lab_status', ";
	$query .= "result_output = ? ";
	sqlStatementNoLog($query, array(mysql_real_escape_string($output)));
}

// send the acknowledgements
if (count($acks) > 0) {
	if ($DEBUG) {
		echo "\nACK RESPONSE ID: ".$response_id;
		foreach ($acks AS $ack) {
			echo "\nACK MESSAGE ID: ".$ack->resultId." - CODE: ".$ack->ackCode;
		}
	}
	$client->sendResultAck($response_id, $acks, $DEBUG);
}

echo "\nEND OF BATCH PROCESSING: ".date('Y-m-d H:i:s')."\n\n\n";

if ($BROWSER) { // end of debug html output
?>
					</pre>
				</td>
			</tr>
		</table>
	</body>
</html>
<?php 
} // end of bedug output footer
?>