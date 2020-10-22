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
$form_table = "form_quest_result";
$form_name = "quest_result";
$form_title = "Quest Result";
$order_table = "form_quest_order";
$order_name = "quest_order";

// grab inportant data
$authuser = $_SESSION['authUser'];	
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];
$id = $_POST["id"];
if ($id) $form_id = $id;

// get the table column names
$fields = sqlListFields($form_table);

// remove control fields
$fields = array_slice($fields,7);

// retrieve the object
$result_data = new wmtForm($form_name,$form_id,true); // retrieve object
if (!$result_data->id) {
	throw new Exception("Missing result record during form update processing...");
}

// NOTE!! only select fields are updated on this record...
$result_data->reviewed_id = formData('reviewed_id');
$result_data->notified_id = formData('notified_id');
$result_data->notified_person = formData('notified_person');
$result_data->result_notes = formData('result_notes');
$result_data->result_handling = formData('result_handling');

$reviewed = false;
$notified = false;
// check for reviewed information
$result_data->reviewed_datetime = 'NULL';
if ($result_data->reviewed_id == "_blank") $result_data->reviewed_id = "";

if ($result_data->reviewed_id) {
	$reviewed_date = formData('reviewed_date');
	if ($reviewed_date) {
		$result_data->reviewed_datetime = date('Y-m-d H:i:s',strtotime($reviewed_date));
	}
	else {
		$result_data->reviewed_datetime = date('Y-m-d H:i:s');
	}
	$reviewed = true;
	$result_data->status = 'v';
}

// check for notified information
$result_data->notified_datetime = 'NULL';
if ($result_data->notified_id == "_blank") $result_data->notified_id = "";

if ($result_data->notified_id) {
	$notified_date = formData('notified_date');
	if ($notified_date) {
		$result_data->notified_datetime = date('Y-m-d H:i:s',strtotime($notified_date));
	}
	else {
		$result_data->notified_datetime = date('Y-m-d H:i:s');
	}
	$notified = true;
	$result_data->status = 'n';
}

// update the result data
$result_data->update();

// REMOVED 2013-05-06 CRISWELL 
//		ORDER RECORDS IN REVIEWED/FINAL/PARTIAL REPORT WAS CONFUSING
// update the order data
//if ($reviewed || $notified) {
//	$order_data = new wmtForm($order_name,$result_data->request_id);
//	if ($reviewed) $order_data->status = 'v'; // results reviewed
//	if ($reviewed && $notified) $order_data->status = 'n'; // patient notified
//	if ($order_data->id) $order_data->update(); // avoid errors from junk test data
//}

formHeader("Redirecting...");
if ($mode =='single') {
	echo "\n<script language='Javascript'>window.close();</script>\n";
}
else {
	formJump();
}
formFooter();

?>