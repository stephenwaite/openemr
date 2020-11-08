<?php
/** **************************************************************************
 *	QUEST_RESULT/LINK.PHP
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
 *  @uses quest_result/update.php
 * 
 *************************************************************************** */
require_once("../../globals.php");
require_once("{$GLOBALS['srcdir']}/options.inc.php");
require_once("{$GLOBALS['srcdir']}/lists.inc");
require_once("{$GLOBALS['srcdir']}/forms.inc");
include_once("{$GLOBALS['srcdir']}/pnotes.inc");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");
require_once("{$GLOBALS['srcdir']}/classes/Document.class.php");

$result_title = "Quest Results - ";
$form_title = 'Quest Result Link';
$form_name = 'quest_link';
$form_id = $_REQUEST['id'];
$form_pid = $_REQUEST['pid'];
$process = $_REQUEST['process'];

// GET QUEST DUMMY PROVIDER
$QUEST_ID = '';
$query = "SELECT id FROM users WHERE username = 'quest' LIMIT 1";
if ($dummy = sqlQuery($query)) $QUEST_ID = $dummy['id'];

// special pnote insert function
function questPnote($pid, $newtext, $assigned_to = '', $datetime = '') {
	$message_sender = 'quest';
	$message_group = 'Default';
	$authorized = '0';
	$activity = '1';
	$title = 'Lab Results';
	$message_status = 'New';
	if (empty($datetime)) $datetime = date('Y-m-d H:i:s');

	// make inactive if set as Done
	if ($message_status == "Done") $activity = 0;

	$body = date('Y-m-d H:i') . ' (Quest Labs to '. $assigned_to;
	$body .= ') ' . $newtext;

	return sqlInsert("INSERT INTO pnotes (date, body, pid, user, groupname, " .
			"authorized, activity, title, assigned_to, message_status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
			array($datetime, $body, $pid, $message_sender, $message_group, $authorized, $activity, $title, $assigned_to, $message_status) );
}

$result_name = 'quest_result';
$result_data = new wmtForm($result_name,$form_id);
$form_id = $result_data->id; // verifies that we found record

$pat_data = wmtPatient::getPidPatient($form_pid);
$form_pid = $pat_data->pid; // verifies that we found record

$errors = '';
if ($process) { // doing the work

	// validate result provider
	$query = "SELECT id, facility_id, username, npi FROM users WHERE id = '".$pat_data->providerID."' ";
	if ($provider = sqlQuery($query)) {
		$provider_id = $provider['id']; // patient default provider
		$provider_facility = $provider['facility_id'];
		$provider_username = $provider['username'];
		$provider_npi = $provider['npi'];
	}
	else {
		$query = "SELECT id, facility_id, username, npi FROM users WHERE id = '".$result_data->request_provider."' ";
		if ($provider = sqlQuery($query)) {
			$provider_id = $provider['id']; // patient default provider
			$provider_facility = $provider['facility_id'];
			$provider_username = $provider['username'];
			$provider_npi = $provider['npi'];
		}
		else {
			$provider_id = $QUEST_ID;
			$provider_username = 'quest';
		}
	}

	// validate facility
	$site_name = "UNKNOWN";
	$query = "SELECT o.option_id, o.title, f.name, f.id FROM list_options o, facility f ";
	$query .= "WHERE o.list_id = 'Quest_Site_Identifiers' AND o.title = '$result_data->request_facility' ";
	$query .= "AND o.option_id = f.id ";
	if ($site = sqlQuery($query)) {
		$site_id = $site['id'];
		$site_name = $site['name'];
		$site_code = $site['title'];
	}
	else {
		$query = "SELECT o.option_id, o.title, f.name, f.id FROM list_options o, facility f ";
		$query .= "WHERE o.list_id = 'Quest_Site_Identifiers' AND f.id = '$provider_facility' ";
		$query .= "AND o.option_id = f.id ";
		if ($site = sqlQuery($query)) {
			$site_id = $site['id'];
			$site_name = $site['name'];
			$site_code = $site['title'];
		}
	}

	// validate the respository directory
	$repository = $GLOBALS['oer_config']['documents']['repository'];
	$file_path = $repository . preg_replace("/[^A-Za-z0-9]/","_",$form_pid) . "/";
	if (!file_exists($file_path)) {
		if (!mkdir($file_path,0700)) {
			throw new Exception("The system was unable to create the directory for this patient, '" . $file_path . "'.\n");
		}
	}
	
	//move document to new patient
	$d = new Document($result_data->document_id);
	$file = $d->get_url_file(); // name of document

	$docnum = 1;
	$baseName = substr($file, 0, strpos($file, '_RESULT')); // drop anything after
	$baseName .= "_RESULT"; // return the important part
	$docName = $baseName;
	$file = $baseName.".pdf";
	while (file_exists($file_path.$file)) { // don't overlay duplicate file names
		$docName = $baseName."_".$docnum++;
		$file = $docName.".pdf";
	}
	
	if (rename($d->get_url_filepath(),$file_path.$file)) {
		$d->url = "file://" .$file_path.$file;
		$d->set_foreign_id($form_pid);
		$d->persist();
	}
	else {
		throw new Exception("The system was unable to move the document to the new patient, '" . $file_path . "'.\n");
	}
	
	// build dummy encounter for this patient/result
	$conn = $GLOBALS['adodb']['db'];
	$encounter = $conn->GenID("sequences");
	addForm($encounter, "QUEST RESULT ENCOUNTER",
		sqlInsert("INSERT INTO form_encounter SET " .
			"date = '$result_data->specimen_datetime', " .
			"onset_date = '$result_data->specimen_datetime', " .
			"reason = 'GENERATED ENCOUNTER FOR QUEST LAB RESULT', " .
			"facility = '" . add_escape_custom($site_name) . "', " .
			"pc_catid = '', " .
			"facility_id = '$site_id', " .
			"billing_facility = '', " .
			"sensitivity = 'normal', " .
			"referral_source = '', " .
			"pid = '$form_pid', " .
			"encounter = '$encounter', " .
			"provider_id = '$provider_id'"),
		"newpatient", $form_pid, 0, date('Y-m-d'), 'quest');

	// update result form data
	$result_data->pid = $form_pid; // new patient
	$result_data->request_pid = $form_pid;
	$result_data->request_DOB = $pat_data->DOB;
	$result_data->request_pat_last = $pat_data->lname;
	$result_data->request_pat_first = $pat_data->fname;
	$result_data->request_pat_middle = $pat_data->mname;
	$result_data->request_provider = $provider_id;
	$result_data->request_npi = $provider_npi;
	$reqult_data->request_facility = $site_code;

	$result_data->status = 'y'; // assume partial results
	if ($result_data->lab_status == 'CM') $result_data->status = 'z'; // final
	
	$result_data->update();
	
	$result_form = $result_title.$result_data->request_order." (".$result_data->lab_number.")";
	addForm($encounter, $result_form, $result_data->id, $result_name, $form_pid, 0, 'NOW()', $provider_username);

	// send them a message
	if ($provider_username && $provider_username != 'quest') {
		$link_ref = "$rootdir/forms/quest_result/update.php?id=$result_data->id&pid=".$form_pid."&enc=".$encounter;
		$note = "\n\nQuest lab results for order number '".$result_data->request_order."' have been manually linked to your patient '".$pat_data->fname." ".$pat_data->lname."' (pid: ".$form_pid."). ";
		$note .= "To review these results click on the following link: ";
		$note .= "<a href='". $link_ref ."' target='_blank' class='link_submit' onclick='top.restoreSession()'>". $result_form ."</a>\n\n";
		questPnote($form_pid, $note, $provider_username);
	}

}
	
?>
<!DOCTYPE HTML>
<html>
	<head>
		<?php html_header_show();?>
		<title><?php echo $form_title; ?></title>
		
		<script>
		function doClose() {
			if((top.window.parent) && (parent.window)){
		        var wname = top.window.parent;
		        if (wname) wname.refreshResults();
	        }
	        top.window.close();
		}

		function doCancel() {
			top.window.close();
		}

		function doSubmit() {
			document.forms[0].submit();
		}
		</script>
		
	</head>
	<body style='background-color:rgb(254,253,207);padding:10px'>
		<form name="linkProcess" action="">	
			<input type="hidden" name="id" value="<?php echo $form_id ?>" />
			<input type="hidden" name="pid" value="<?php echo $form_pid ?>" />
			<input type="hidden" name="process" value="1" />
			
<?php 
if (!$form_id || !$form_pid) {
	echo "<h1 style='color:red'>Processing Error...</h1>";
	if (!$form_id) echo "Missing result record identifier!!<br/>";
	if (!$form_pid) echo "Missing patient record identifier!!<br/>";
	echo "<br/>ABORTING.. No changes where made!!";
	exit;
}
?>

<?php if (!$process) { ?>
			<h1>Result Linkage...</h1>
			Please confirm linking result # <?php echo ($result_data->request_order) ? $result_data->request_order : 'UNKNOWN' ?> (<?php echo $form_id ?>) to <?php echo $pat_data->format_name ?> (<?php echo $pat_data->pid?>).
			<br/><br/>
			A new encounter will be created for this patient and the Quest documents and result information<br/>
			will be transferred to this patient. Click [Continue] to complete the transfer or [Cancel] to close this<br/>
			window without making any changes.
			<br/><br/><br/><br/><br/>
			<center>
				<input type='button' name='linkButton' onclick="doSubmit()" value=" Continue "/>
				<input type='button' name='cancelButton' onclick="doCancel()" value=" Cancel "/>
			</center>
<?php } else { ?>
			<h1>Result Linked...</h1>
			Result # <?php echo ($result_data->request_order) ? $result_data->request_order : 'UNKNOWN' ?> (<?php echo $form_id ?>) linked to <?php echo $pat_data->format_name ?> (<?php echo $pat_data->pid?>).
			<br/><br/>
			Encounter #<?php echo $encounter ?> has been created for this patient and the Quest documents and result information<br/>
			have been successfully transferred. Click [Close] to close this window.
			<br/><br/><br/><br/><br/>
			<center>
				<input type='button' name='closeButton' onclick="doClose()" value=" Close "/>
			</center>
<?php } ?>
		</form>
	</body>
</html>
