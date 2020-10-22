<?php 
/** **************************************************************************
 *	LABORATORY/QUICK_ORDERS.PHP
 *
 *	Copyright (c)2017 - Medical Technology Services
 *
 *	This program is free software: you can redistribute it and/or modify it 
 *	under the terms of the GNU General Public License as published by the Free 
 *	Software Foundation, either version 3 of the License, or (at your option) 
 *	any later version.
 *
 *	This program is distributed in the hope that it will be useful, but WITHOUT 
 *	ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or 
 *	FITNESS FOR A PARTICULAR PURPOSE. See the GNU General Public License for 
 *	more details.
 *
 *	You should have received a copy of the GNU General Public License along with 
 *	this program.  If not, see <http://www.gnu.org/licenses/>.	This program is 
 *	free software; you can redistribute it and/or modify it under the terms of 
 *	the GNU Library General Public License as published by the Free Software 
 *	Foundation; either version 2 of the License, or (at your option) any 
 *	later version.
 *
 *  @package reports
 *  @subpackage quick
 *  @version 1.0
 *  @copyright Medical Technology Services
 *  @author Ron Criswell <info@mdtechsvcs.com>
 * 
 *************************************************************************** */

// Sanitize escapes
$sanitize_all_escapes = true;

// Stop fake global registration
$fake_register_globals = false;

require_once("../../globals.php");
require_once($GLOBALS['srcdir']."/acl.inc");
require_once($GLOBALS['srcdir']."/options.inc.php");
require_once($GLOBALS['srcdir']."/wmt/wmt.globals.php");

// Grab session data
$authuser = $_SESSION['authUser'];	
$groupname = $_SESSION['authProvider'];
$authorized = $_SESSION['userauthorized'];

// Security violation
if (!$authuser)
	die ("FATAL ERROR: you do not have permission to access this program!!");

// Security setup
$acl_add = acl_check('quick', 'enter');
$acl_view = acl_check('quick', 'access');
if ($acl_view) $acl_add = true;
$acl_full = acl_check('quick', 'detail');g;
$acl_full = true; ////// TESTING //////
if ($acl_full) $acl_add = $acl_view = true;

// Form security check
if (!$acl_add)
	die ("FATAL ERROR: you do not have permission to run this report!!");

// Load libraries
require_once("../../globals.php");
	
// report defaults
$report_title = 'Quick Pick Orders';
$order_name = 'quick_order';

// Load module list data
$status_list = new wmt\Options('Form_Status');
$priority_list = new wmt\Options('Form_Priority');

$lab_list = array();
$lab_res = sqlStatementNoLog("SELECT DISTINCT `ppid`, `name` FROM `procedure_providers`");
while ($record = sqlFetchArray($lab_res))
	$lab_list[$record['ppid']] = $record['name'];

// For each sorting option, specify the ORDER BY argument.
$ORDERHASH = array(
	'time'    	=> 'date_ordered, lower(doc_lname), lower(doc_fname), lower(fq.pid)',
	'doctor'  	=> 'lower(doc_lname), lower(doc_fname), date_ordered',
	'patient' 	=> 'lower(pat_lname), lower(pat_fname), date_ordered',
	'pid' 		=> 'lower(fq.pid), date_ordered',
	'encounter' => 'lower(fe.encounter), date_ordered',
	'dob' 		=> 'pd.DOB, date_ordered, lower(fq.pid)',
	'sex'		=> 'lower(pd.sex), date_ordered, lower(fq.pid)',
	'priority'  => 'fq.form_priority, date_ordered, lower(doc_lname), lower(doc_fname)',
	'status'    => 'fq.form_complete, date_ordered, lower(doc_lname), lower(doc_fname)',
);

// get date range
$last_month = mktime(0,0,0,date('m')-1,date('d'),date('Y'));
$form_from_date 	= ($_POST['form_from_date']) ? $_POST['form_from_date'] : date('Y-m-d', $last_month);
$form_to_date 		= ($_POST['form_to_date']) ? $_POST['form_to_date'] : date('Y-m-d');
$form_provider 		= $_POST['form_provider'];
$form_facility 		= $_POST['form_facility'];
$form_complete	 	= $_POST['form_complete'];
$form_patient 		= $_POST['form_patient'];
$set_id				= $_POST['set_id'];
$set_status 		= $_POST['set_status'];
$form_refresh 		= ($_POST['form_refresh'] || $_POST['form_orderby'])? true: false;

/* Change a status --- DEPRECATED
if ($set_id && $set_status) {
	$order_data = new wmt\Order('quick',$set_id);
	
	// Update the form record status
	$order_data->date = date('Y-m-d H:i:s');
	$order_data->form_complete = $set_status;

	// Run the update
	$order_data->store();

	// Reset parameters
	$set_id = '';
	$set_status = '';
	$form_refresh = true;
} */
	
// get sort order
$form_orderby	= $ORDERHASH[$_REQUEST['form_orderby']] ? $_REQUEST['form_orderby'] : 'time';
$orderby 		= $ORDERHASH[$form_orderby];

// retrieve records
$query = '';
$orders = array();
$results = false;

// retrieve data
if ($_POST['form_refresh'] || $_POST['form_orderby']) {
	$query = "SELECT fe.encounter, fe.date AS enc_date, fe.facility_id, fe.reason, opc.pc_catname, ";
	$query .= "u.fname AS doc_fname, u.mname AS doc_mname, u.lname AS doc_lname, ";
	$query .= "fq.id, fq.pid, fq.form_complete, fq.form_priority, fq.order_number, fq.activity, fq.narr_notes, fq.lab_provider, fq.int_provider, ";
	$query .= "po.provider_id, po.date_ordered, po.patient_instructions AS instructions, po.lab_id, ";
	$query .= "pd.fname AS pat_fname, pd.lname AS pat_lname, pd.mname AS pat_mname, pd.pubpid, pd.sex, pd.DOB, ";
	$query .= "f.form_id ";
	$query .= "FROM forms f ";
	$query .= "LEFT JOIN form_encounter fe ON fe.encounter = f.encounter ";
	$query .= "LEFT JOIN openemr_postcalendar_categories opc ON opc.pc_catid = fe.pc_catid ";
	$query .= "LEFT JOIN form_quick fq ON fq.id = f.form_id ";
	$query .= "LEFT JOIN procedure_order po ON po.procedure_order_id = fq.order_number ";
	$query .= "LEFT JOIN users u ON u.id = po.provider_id ";
	$query .= "LEFT JOIN patient_data pd ON pd.pid = fq.pid ";
	$query .= "WHERE f.deleted != '1' AND f.formdir = 'quick' ";
	
	if ($form_complete) {
		$query .= "AND fq.form_complete = ? ";
		$parms[] = $form_complete;
	}

	if ($form_facility) {
		$query .= "AND fe.facility_id = ? ";
		$parms[] = $form_facility;
	}
	
	if ($form_from_date) {
		$query .= "AND date_ordered >= ? AND date_ordered <= ? ";
		$parms[] = $form_from_date . ' 00:00:00';
		$parms[] = $form_to_date . ' 23:59:59';
	}
	
	if ($form_provider) {
		$query .= "AND po.provider_id = ? ";
		$parms[] = $form_provider;
	}
	
	if ($form_patient) {
		$query .= "AND f.pid = ? ";
		$parms[] = $form_patient;
	}
	
	$query .= "ORDER BY $orderby";
	$results = sqlStatement($query,$parms);

}
?>
<!DOCTYPE HTML>
<html>
<head>
		<?php html_header_show();?>
		<title><?php echo $report_title; ?></title>

<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
<link rel="stylesheet" type="text/css"
	href="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.css"
	media="screen" />

<script
	src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-min-1-9-1/index.js"></script>
<script
	src="<?php echo $GLOBALS['assets_static_relative']; ?>/jquery-ui-1-11-4/jquery-ui.min.js"></script>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/js/common.js"></script>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4_patch.js"></script>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/dialog.js"></script>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/overlib_mini.js"></script>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/textformat.js"></script>
<!-- script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/wmt/wmtstandard.js"></script -->

<!-- pop up calendar -->
<style type="text/css">
@import
url(<?php
echo
$GLOBALS[
'webroot'
]
?>/
library
/dynarch_calendar.css);
</style>
<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/dynarch_calendar.js"></script>
		<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
		<script type="text/javascript"
	src="<?php echo $GLOBALS['webroot'] ?>/library/dynarch_calendar_setup.js"></script>

<style type="text/css">
/* specifically include & exclude from printing */
@media print {
	#report_parameters {
		visibility: hidden;
		display: none;
	}
	#report_parameters_daterange {
		visibility: visible;
		display: inline;
	}
	#report_results table {
		margin-top: 0px;
	}
}

/* specifically exclude some from the screen */
@media screen {
	#report_parameters_daterange {
		visibility: hidden;
		display: none;
	}
}

#report_results table td {
	border-bottom: none;
}
</style>

<script>

			var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

			function doSubmit() {
				if ($('#from_date').val() == '' || $('#thru_date').val() == '') {
					alert("Order date range required for execution!!\nPlease enter from and to dates.");
					return false;
				}

				// post the form
				$('#theform').submit();
			}
	
			function doSort(orderby) {
				var f = document.forms[0];
				f.form_orderby.value = orderby;
				f.submit();
				return false;
			}

			function refreshme() {
	 			document.forms[0].form_ignore.value = '';
				document.forms[0].submit();
 			}

			function doStatus(obj,id) {
				var f = document.forms[0];
				f.form_refresh.value = '';
				f.set_status.value = obj.value;
				f.set_id.value = id;
				f.submit();
			}

			function openForm(id) {
				url = "<?php echo $webroot ?>/interface/forms/quick/view.php?pop=1&id=" + id;
				window.open(url, '_blank');
			}


			function loadPatient(pname, pid, pubpid, str_dob) {
				// in a popup so load the opener window if it still exists
				if ( (window.opener) && (window.opener.setPatient) ) {
					window.opener.loadFrame('RTop', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid);
				// inside an OpenEMR frame so replace current frame
				} else if ( (parent.left_nav) && (parent.left_nav.loadFrame) ) {
					parent.left_nav.loadFrame('RTop', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid);
				// not in a frame and opener no longer exists, create a new window
				} else {
					var newwin = window.open('../../main/main_screen.php?patientID=' + pid);
				}
			}

			function loadEncounter(pname, pid, pubpid, str_dob, str_date, enc) {
				// in a popup so load the opener window if it still exists
				if ( (window.opener) && (window.opener.setEncounter) ) {
                    window.opener.forceDual();
                    window.opener.loadFrame('RBot', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid + '&set_encounterid=' + enc);
				// inside an OpenEMR frame so replace current frames
				} else if ( (window.opener) && (window.opener.top) && (window.opener.top.left_nav) && (window.opener.top.left_nav.setEncounter) ) {
					window.opener.top.left_nav.forceDual();
					window.opener.top.left_nav.loadFrame('RBot', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid + '&set_encounterid=' + enc);
				// not in a frame and opener no longer exists, create a new window
				} else if ( (parent.left_nav) && (parent.left_nav.setEncounter) ) {
                    parent.left_nav.forceDual();
                    parent.left_nav.loadFrame('RBot', 'RTop', 'patient_file/summary/demographics.php?set_pid=' + pid + '&set_encounterid=' + enc);
				// not in a frame and opener no longer exists, create a new window
				} else {
					var newwin = window.open('../../main/main_screen.php?patientID=' + pid + '&encounterID=' + enc);
				}
			}

			setTimeout(function(){
				document.forms[0].submit();
			}, 50000);
		</script>
</head>


<body class="body_top">
	<!-- Required for the popup date selectors -->
	<div id="overDiv"
		style="position: absolute; visibility: hidden; z-index: 1000;"></div>

	<span class='title'><?php xl('Report','e'); ?> - <?php xl($report_title,'e'); ?></span>

	<div id="report_parameters_daterange">
			<?php echo date("d F Y", strtotime($form_from_date)) ." &nbsp; to &nbsp; ". date("d F Y", strtotime($form_to_date)); ?>
		</div>

	<form method='post' name='theform' id='theform'
		action='quick_orders.php'>
		<input type='hidden' name='set_id' id='set_id' value='' /> <input
			type='hidden' name='set_status' id='set_status' value='' />
		<div id="report_parameters">
			<table style='line-height: 25px'>
				<tr>
					<td style="width: 100%">
						<table class='text'>
							<tr>
								<td style="line-height: 22px">
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('Facility','e'); ?>: 
<?php
	// Build a drop-down list of facilities.
	$query = "SELECT id, name FROM facility ORDER BY name";
	$fres = sqlStatement($query);

	echo "   <select name='form_facility' style='max-width:200px'>\n";
	echo "    <option value=''>-- " . xl('All Facilities') . " --\n";

	while ($frow = sqlFetchArray($fres)) {
		$facid = $frow['id'];
		echo "    <option value='$facid'";
		if ($facid == $_POST['form_facility']) echo " selected";
		echo ">" . $frow['name'] . "\n";
	}
	
	echo "   </select>\n";
?>
										</div>
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('Provider','e'); ?>:
<?php
	// Build a drop-down list of providers.
	$query = "SELECT id, username, lname, fname FROM users WHERE authorized = 1 AND active = 1 $provider_facility_filter ORDER BY lname, fname";
	$ures = sqlStatement($query);

	echo "   <select name='form_provider' style='max-width:200px'>\n";
	echo "    <option value=''>-- " . xl('All Providers') . " --\n";

	while ($urow = sqlFetchArray($ures)) {
		$provid = $urow['id'];
		echo "    <option value='$provid'";
		if ($provid == $_POST['form_provider']) echo " selected";
		echo ">" . $urow['lname'] . ", " . $urow['fname'] . "\n";
	}
	
	echo "   </select>\n";
?>
										</div>
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('From','e'); ?>: 
											<input type='text' name='form_from_date' id="form_from_date"
											size='10' value='<?php echo $form_from_date ?>'
											onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)'
											title='yyyy-mm-dd'> <img src='../../pic/show_calendar.gif'
											align='absbottom' width='24' height='22' id='img_from_date'
											border='0' alt='[?]' style='cursor: pointer'
											title='<?php xl('Click here to choose a date','e'); ?>'>
									</div>
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('To','e'); ?>:
											<input type='text' name='form_to_date' id="form_to_date"
											size='10' value='<?php echo $form_to_date ?>'
											onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)'
											title='yyyy-mm-dd'> <img src='../../pic/show_calendar.gif'
											align='absbottom' width='24' height='22' id='img_to_date'
											border='0' alt='[?]' style='cursor: pointer'
											title='<?php xl('Click here to choose a date','e'); ?>'>
									</div>
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('Patients','e'); ?>: 
<?php
	// Build a drop-down list of processor names.
	$query = "SELECT DISTINCT pd.pid, CONCAT(pd.fname, ' ', pd.lname, ' (', pd.pubpid, ')') AS pat_name FROM form_quick fq ";
	$query .= "LEFT JOIN patient_data pd ON fq.pid = pd.pid ";
	$query .= "WHERE fq.form_complete = 'i' ORDER BY pd.lname, pd.fname, pd.pid";
	$ures = sqlStatement($query);

	echo "   <select name='form_patient' style='max-width:200px'>\n";
	echo "    <option value=''>-- " . xl('All') . " --\n";

	while ($urow = sqlFetchArray($ures)) {
		$pid = $urow['pid'];
		if (!$pid) continue;
		echo "    <option value='$pid'";
		if ($pid == $_POST['form_patient']) echo " selected";
		echo ">" . $urow['pat_name'] . "\n";
	}

	echo "   </select>\n";
  ?>
  										</div>
									<div
										style="float: left; margin-right: 20px; margin-bottom: 5px">
											<?php xl('Status','e'); ?>:
<?php
	// Build a drop-down list of form statuses.
	$query = "SELECT option_id, title FROM list_options WHERE list_id = 'Form_Status' ORDER BY seq";
	$ures = sqlStatement($query);

	echo "   <select name='form_complete'>\n";
	echo "    <option value=''>-- " . xl('All') . " --\n";
	$currid = isset($_POST['form_complete']) ? $_POST['form_complete'] : 'i';
	while ($urow = sqlFetchArray($ures)) {
		$statid = $urow['option_id'];
		echo "    <option value='$statid'";
		if ($statid == $currid) echo " selected";
		echo ">" . $urow['title'] . "\n";
	}
              
	echo "   </select>\n";
?>
										</div>
								</td>
							</tr>
						</table>
					</td>
					<td style="line-height:15px;vertical-align:middle;text-align:center;height:100%;padding-right:20px;min-width:<?php echo ($form_refresh)? '120px' : '75px' ?>">
<?php if ($form_refresh) { ?>
							<div style='float: right'>
							<a href='#' class='css_button' onclick='window.print()'> <span><?php xl('Print','e'); ?></span>
							</a>
						</div>
<?php } ?>
							<div style='float: right'>
							<a href='#' class='css_button' onclick='doSubmit()'> <span><?php xl('Submit','e'); ?></span>
							</a>
						</div>
					</td>
				</tr>
			</table>

		</div>
		<!-- end report_parameters -->

<?php if ($_POST['form_refresh'] || $_POST['form_orderby']) { ?>

			<div id="report_results">
			<table>
				<thead>
					<th><a href="nojs.php" onclick="return doSort('time')"
						<?php if ($form_orderby == "time") echo " style=\"color:#00cc00\"" ?>><?php  xl('Ordered Date/Time','e'); ?>
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('doctor')"
						<?php if ($form_orderby == "doctor") echo " style=\"color:#00cc00\"" ?>><?php  xl('Provider','e'); ?> 
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('patient')"
						<?php if ($form_orderby == "patient") echo " style=\"color:#00cc00\"" ?>><?php  xl('Patient Name / PID','e'); ?>
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('dob')"
						<?php if ($form_orderby == "dob") echo " style=\"color:#00cc00\"" ?>><?php  xl('Birthdate','e'); ?>
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('sex')"
						<?php if ($form_orderby == "sex") echo " style=\"color:#00cc00\"" ?>><?php  xl('Gender','e'); ?>
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('encounter')"
						<?php if ($form_orderby == "encounter") echo " style=\"color:#00cc00\"" ?>><?php  xl('Encounter / ID','e'); ?>
							</a></th>
					<th><a href="nojs.php" onclick="return doSort('priority')"
						<?php if ($form_orderby == "priority") echo " style=\"color:#00cc00\"" ?>><?php  xl('Priority','e'); ?>
							</a></th>
					<th style="width: 100px"><a href="nojs.php"
						onclick="return doSort('status')"
						<?php if ($form_orderby == "status") echo " style='color:#00cc00'" ?>><?php  xl('Status','e'); ?>
							</a></th>
					<th>
							<?php  xl('Form','e'); ?>
						</th>
				</thead>
				<tbody>
<?php
		$printed  = 0; 
		if (sqlNumRows($results) > 0) {
			$lastdocname = "";
			$doc_encounters = 0;
			while ($row = sqlFetchArray($results)) {
				$date = strtotime($row['date_ordered']);
				$datestr = date('Y-m-d', $date);
				$timestr = date('h:iA', $date);
				
				$docname = '';
				$parts = explode(' ', $row['doc_lname']);
				if (!empty($parts[0])) $docname = $parts[0];

				$patname = $row['pat_lname'] . ', ' . $row['pat_fname'];
				if ($row['pat_mname']) $patname .= ' ' . $row['pat_mname'];
				$patname .= ' (' . $row['pubpid'] . ')';
				
				$encname = $row['pc_catname'] . ' (' . $row['encounter'] . ')';
				
				$priority = $priority_list->getItem($row['form_priority']);
				if ($priority == 'Urgent') $priority = "<span style='font-weight:bold;color:red'>Urgent</span>";
	    		
				$status = $status_list->getItem($row['form_complete']);
				if ($status == 'Incomplete') $status = "<span style='font-weight:bold;color:red'>Incomplete</span>";
	    		
				$link_ref="$rootdir/forms/quick/update.php?id=".$row['form_id']."&pid=".$row['pid']."&enc=".$row['encounter']."&pop=1";
				
				$bgcolor = ($bgcolor == '#ffffff') ? '#ececec' : '#ffffff';
?>
						<tr bgcolor='<?php echo $bgcolor ?>' style='<?php echo ($printed > 0)? 'border-top:1px dashed;padding-top:10px' : 'border-top:none' ?>'>
						<td>
   								<?php echo $datestr . ' ' .$timestr ?>&nbsp;
							</td>
						<td class="nowrap">
								<?php echo $docname; ?>&nbsp;
							</td>
						<td class="nowrap">
								<?php 
									echo "<a href='#' onclick='loadPatient(";
									echo '"' . $patname . '","' . $row['pid'] . '","' . $row['pubpid'] . '","' . $row['DOB'] . '"';
									echo ")'><strong>". $patname; 
									echo "</strong></a>&nbsp;" ?>
							</td>
						<td>
   								<?php echo $row['DOB']; ?>&nbsp;
							</td>
						<td>
   								<?php echo $row['sex']; ?>&nbsp;
							</td>
						<td>
								<?php 
									echo "<a href='#' onclick='loadEncounter(";
									echo '"' . $patname . '","' . $row['pid'] . '","' . $row['pubpid'] . '","' . $row['DOB'] . '",';
									echo '"' . $datestr . '","' . $row['encounter'] . '"';
									echo ")'><strong>". $encname; 
									echo "</strong></a>&nbsp;" ?>
							</td>
						<td>
   								<?php echo $priority; ?>&nbsp;
							</td>
						<td>
   								<?php echo $status; ?>&nbsp;
							</td>
						<td style="min-width: 130px"><a href="<?php echo $link_ref; ?>"
							target="_blank" style="font-weight: bold"
							onclick="top.restoreSession()">Quick Form - <?php echo $row['order_number']; ?></a>&nbsp;
						</td>
<?php /* DEPRECATED --- ?>
							<td>
<?php if ($row['status'] == 'C') { ?>
								<input id='status' disabled value="Completed" style="width:100%;text-align:center"/>
<?php } else { ?>
								<select id='status' onchange="doStatus(this,'<?php echo $row['form_id']?>');" style='width:100%'>
									<?php $status_list->showOptions($row['form_complete']); ?>
								</select>
<?php } ?>
							</td>
<?php --- */ ?>
						</tr>
<?php
				// print provider instructions (if present) 
				if ($row['narr_notes']) { ?>
				
						<tr bgcolor='<?php echo $bgcolor ?>'>
						<td style='padding: 0 5px'>&nbsp;</td>
						<td style="text-align: right; padding: 0"><strong>Instructions: </strong>
						</td>
						<td colspan="6" style='padding: 0 5px'>
							<div style="white-space: pre-wrap"><?php echo $row['narr_notes'] ?></div>
						</td>
						<td>&nbsp;</td>
					</tr>
<?php 
				}

				// print provider instructions (if present)
				if ($row['lab_provider']) { ?>
				
						<tr bgcolor='<?php echo $bgcolor ?>'>
						<td style='padding: 0 5px'>&nbsp;</td>
						<td style="text-align: right; padding: 0"><strong>Laboratory: </strong>
						</td>
						<td colspan="6" style='padding: 0 5px'>
							<div style="white-space: pre-wrap"><?php echo $row['lab_provider'] ?></div>
						</td>
						<td>&nbsp;</td>
					</tr>
<?php 
				}
				
				// print provider instructions (if present)
				if ($row['int_provider']) { ?>
				
						<tr bgcolor='<?php echo $bgcolor ?>'>
						<td style='padding: 0 5px'>&nbsp;</td>
						<td style="text-align: right; padding: 0"><strong>Internal: </strong>
						</td>
						<td colspan="6" style='padding: 0 5px'>
							<div style="white-space: pre-wrap"><?php echo $row['int_provider'] ?></div>
						</td>
						<td>&nbsp;</td>
					</tr>
<?php 
				}
				
				// print items with order (if present)
				$item_list = wmt\OrderItem::fetchItemList($row['order_number']);
				if (!empty($item_list)) {
					foreach ($item_list AS $item) { ?>
								
						<tr bgcolor='<?php echo $bgcolor ?>'>
						<td colspan="2" style='padding: 0 5px'>&nbsp;</td>
						<td style='padding: 0 5px; vertical-align: top'>
								<?php echo $lab_list[$item->lab_id]; ?>&nbsp;
							</td>
						<td style='padding: 0 5px; vertical-align: top'>
								<?php echo strtoupper($item->procedure_code); ?>&nbsp;
							</td style='padding:0 5px'>
<?php 
		
						// are there results?
						$result_data = wmt\Result::fetchResult($item->procedure_order_id, $item->procedure_order_seq);
						if (empty($result_data)) { ?>
							<td colspan="5" style='padding: 0 5px; vertical-align: top'>
								<?php echo $item->procedure_name; ?>&nbsp;
<?php 
						} else { ?>
							
						
						<td colspan="1" style='padding: 0 5px; vertical-align: top'>
								<?php echo $item->procedure_name; ?>&nbsp;
							</td>
						<td colspan="4" style='padding: 0 5px'>
<?php 
							echo "<strong>Result: </strong>";
							echo $result_data->date_report;
							echo " - ";
							echo $result_data->report_notes; 

							// now get details is there are any
							$detail_list = wmt\ResultItem::fetchItemList($result_data->procedure_report_id);
					
							// display details if there are any
							if (count($detail_list) > 0) {
								echo "<br/>";
								foreach ($detail_list AS $detail) {
									echo "<span style='font-weight:bold;margin-left:15px'>" . $detail->result_text . ": </span>" . $detail->result ."<br/>";
								}
							}
						} ?>
							</td>
					</tr>
<?php 
					} // end item foreach ?>
					
						<tr bgcolor="<?php echo $bgcolor ?>">
						<td colspan="9" style="padding: 0px">&nbsp;</td>
					</tr>
<?php 					
				} // end if items
								
				$printed++;
				$lastdocname = $docname;
			}
		}
		
		if (!$printed) {
?>
						<tr>
						<td colspan="8"
							style="font-weight: bold; text-align: center; padding: 25px">NO
							QUALIFYING ORDERS FOUND</td>
					</tr>

<?php 
		}
?>
					</tbody>
			</table>
		</div>
		<!-- end encresults -->
<?php 
	} 
	else { 
?>
			<div class='text'>
				<?php echo xl('Please input search criteria above, and click Submit to view results.', 'e' ); ?>
			</div>
<?php 
	} 
?>

			<input type="hidden" name="form_orderby" id="form_orderby"
			value="<?php echo $form_orderby ?>" /> <input type='hidden'
			name='form_refresh' id='form_refresh' value='' />

	</form>
</body>

<script language='JavaScript'>
		Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
		Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});
		<?php if ($alertmsg) { echo " alert('$alertmsg');\n"; } ?>
	</script>

</html>
