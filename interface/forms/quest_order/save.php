<?php
/** **************************************************************************
 *	QUEST_ORDER/SAVE.PHP
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
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */
require_once("../../globals.php");
require_once("$srcdir/api.inc");
require_once("$srcdir/forms.inc");
require_once("$srcdir/sql.inc");
require_once("$srcdir/wmt/wmt.class.php");

// set process defaults
$form_id = null;
$form_table = "form_quest_order";
$form_name = "quest_order";
$form_title = "Quest Order";
$item_table = "form_quest_order_item";
$item_name = "quest_order_item";

// grab inportant data
$pid = $_SESSION['pid'];
$authuser = $_SESSION['authUser'];	
$groupname = $_SESSION['authProvider'];
$encounter = $_SESSION['encounter'];
$authorized = $_SESSION['userauthorized'];
$mode = $_POST['mode'];
$process = $_POST['process'];
$id = $_POST["id"];
if ($id) $form_id = $id;
$quest_siteid = $_POST['lab_quest_siteid'];

// get the table column names
$fields = sqlListFields($form_table);

// remove control fields
$fields = array_slice($fields,7);

// retrieve/generate the object
$order_data = new wmtForm($form_name,$id,true); // retrieve object
$item_list = array();

// process form
$order_data->date = date('Y-m-d H:i:s');
$order_data->pid = $pid;
$order_data->user = $authuser;
$order_data->groupname = $groupname;
$order_data->authorized = $authorized;

// retrieve the post data for the fields
foreach ($fields as $field) {
	$order_data->$field = formData($field);
	if ($order_data->$field == '_blank') $order_data->$field = '';
}

// set default status
$order_data->status = 'i'; // incomplete

// get datetime values (order, sample, pending sample)
$request_date = formData('request_date');
if ($request_date) {
	$order_data->request_datetime = date('Y-m-d H:i:s',strtotime($request_date));
}
else {
	$order_data->request_datetime = date('Y-m-d H:i:s');
}

// get ordering provider username information
$provider_username = '';
$provider_id = formData('request_provider');
if ($provider_id && $provider_id != '_blank') {
	$provider = sqlQuery("SELECT username FROM users WHERE id = $provider_id");
}
$provider_username = ($provider['username']) ? $provider['username'] : $authuser;

// verify order date
$order_data->order0_datetime = 'NULL';
$sample_date = formData('order0_date');
if ($sample_date) {
	$sample_time = ($_POST['order0_time'])?formData('order0_time'):'00:00:00';
	$order_data->order0_datetime = date('Y-m-d H:i:s',strtotime("$sample_date $sample_time"));
}

// verify pending date
$order_data->order0_pending = 'NULL';
$pending_date = formData('order0_pending_date');
if ($pending_date) {
	$pending_time = ($_POST['order0_pending_time'])?formData('order0_pending_time'):'00:00:00';
	$order_data->order0_pending = date('Y-m-d H:i:s',strtotime("$pending_date $pending_time"));
}

// get diagnosis data
for ($d = 0; $d < count($dx_code); $d++) {
	$key = "dx".$d."_code";
	$order_data->$key = formDataCore($dx_code[$d]);	
	$key = "dx".$d."_text";
	$order_data->$key = formDataCore($dx_text[$d]);	
}

// process new form
if ($mode == 'new') {
	// add order record
	$id = wmtForm::insert($order_data);
	$order_data = new wmtForm($form_name,$id); // refresh object
	
	// add to forms list
	addForm($encounter, $form_title." - ".$order_data->order0_number, $id, $form_name, $pid, $authorized, 'NOW()', $provider_username);
	
	// save id for reload
	$form_id = $id;
}
// process form update
else if ($mode == 'update') {
	// update the order data
	$order_data->update();
}
else {
	die ("Unknown mode '$mode'");
}

// get the table column names
$fields = sqlListFields($item_table);
$fields = array_slice($fields,7);

// remove existing test records
$query = "DELETE FROM $item_table WHERE parent_id = $order_data->id ";
sqlStatement($query);

// create replacement test records
for ($t = 0; $t < count($test_code); $t++) {
	// create a new test record
	$item_data = new wmtForm($item_name); 
	
	// process action form
	$item_data->parent_id = $order_data->id;
	$item_data->date = $order_data->date;
	$item_data->pid = $order_data->pid;
	$item_data->user = $order_data->user;
	$item_data->groupname = $order_data->groupname;
	$item_data->authorized = $order_data->authorized;

	$code = formDataCore($test_code[$t]);
	$item_data->test_code = $code;	
	$item_data->test_text = formDataCore($test_text[$t]);
	$item_data->test_profile = formDataCore($test_profile[$t]);
	
	$code_key = "aoe".$code."_code";
	$label_key = "aoe".$code."_label";
	$text_key = "aoe".$code."_text";
	for ($a = 0; $a < count(${$code_key}); $a++) {
		$key = "aoe".$a."_code";
		$item_data->$key = formDataCore(${$code_key}[$a]);
		$key = "aoe".$a."_label";
		$item_data->$key = formDataCore(${$label_key}[$a]);
		$key = "aoe".$a."_text";
		$item_data->$key = formDataCore(${$text_key}[$a]);
	}

	$id = wmtForm::insert($item_data); // create object
	$item_list[] = new wmtForm($item_name,$id);
}

// should we send the order
if (!$process) {
	// redirect to landing page
	formHeader("Redirecting...");
	
	$returnurl = $GLOBALS['concurrent_layout'] ? 'encounter_top.php' : 'patient_encounter.php';
	if ($address == "0" || $address == '')
		$address = "{$GLOBALS['rootdir']}/patient_file/encounter/$returnurl";
	
	echo "\n<script language='Javascript'>\nif ( top.frames.length == 0 ) { // not in a frame so pop up\n";
	echo "window.opener.location.reload(false);window.close();\n";
	echo "} else { \n";
	echo "top.restoreSession();window.location='$address';\n";
	echo "}\n</script>\n";

	formFooter();
}
else {
	include('process.php');	
}

?>