<?php
// Copyright (C) 2007-2010 Rod Roark <rod@sunsetsystems.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

// This report shows past encounters with filtering and sorting.

require_once("../../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/billing.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/formatting.inc.php");
require_once "$srcdir/options.inc.php";
require_once "$srcdir/formdata.inc.php";
require_once "$srcdir/wmt/wmt.include.php";

$alertmsg = ''; // not used yet but maybe later

// For each sorting option, specify the ORDER BY argument.
//
$ORDERHASH = array(
  'doctor'  => 'lower(users.lname), lower(users.fname), form_encounter.date',
  'patient' => 'lower(patient_data.lname), lower(patient_data.fname), form_encounter.date',
  'pubpid'  => 'lower(patient_data.pubpid), form_encounter.date',
  'time'    => 'form_encounter.date, lower(users.lname), lower(users.fname)',
);

$last_month = mktime(0,0,0,date('m')-1,date('d'),date('Y'));
$form_from_date = fixDate($_POST['form_from_date'], date('Y-m-d', $last_month));
$form_to_date = fixDate($_POST['form_to_date'], date('Y-m-d'));
$form_provider  = $_POST['form_provider'];
$form_facility  = $_POST['form_facility'];
$form_status  = $_POST['form_status'];
$form_name      = $_POST['form_name'];
$form_lab	= $_POST['form_lab'];
$form_special = $_POST['form_special'];
$form_details   = "1";

$form_orderby = $ORDERHASH[$_REQUEST['form_orderby']] ?
  $_REQUEST['form_orderby'] : 'doctor';
$orderby = $ORDERHASH[$form_orderby];

// Get the info.
//
//  "( forms.formdir = 'abc_SHarvey' OR forms.formdir = 'whc_newpat' " .
//  "OR forms.formdir = 'whc_visit' OR forms.formdir = 'whc_well_woman') ";
//  $formlist = getCustomForms();
$query = "SELECT " .
  "forms.formdir, forms.form_name, forms.deleted, forms.form_id, forms.user, " .
  "form_encounter.encounter, form_encounter.date, form_encounter.reason, " .
  "patient_data.fname, patient_data.mname, patient_data.lname, " .
  "patient_data.pubpid, patient_data.pid AS ppid, " .
  "users.lname AS ulname, users.fname AS ufname, users.mname AS umname, " .
  "users.username " .
  "FROM forms " .
  "LEFT JOIN form_encounter USING (pid, encounter) " .
  "LEFT JOIN users ON forms.user = users.username " .
  "LEFT JOIN patient_data ON forms.pid = patient_data.pid " .
  "WHERE " .
  "forms.deleted != '1' AND patient_data.lname != '#QUESTLABS#' AND " .
// 2013-02-14 R.CRISWELL
//  "forms.formdir = 'whc_lab' ";
  " (forms.formdir = 'whc_lab' OR forms.formdir LIKE 'quest%') ";
// ---------------------
if ($form_to_date) {
  $query .= "AND form_encounter.date >= '$form_from_date 00:00:00' AND form_encounter.date <= '$form_to_date 23:59:59' ";
} else {
  $query .= "AND form_encounter.date >= '$form_from_date 00:00:00' AND form_encounter.date <= '$form_from_date 23:59:59' ";
}
if ($form_provider) {
	
	$query .= "AND forms.user = '$form_provider' ";
}
if ($form_facility) {
  $query .= "AND form_encounter.facility_id = '$form_facility' ";
}
if ($form_name) {
  $query .= "AND forms.formdir = '$form_name' ";
}
$query .= "ORDER BY $orderby";
//echo $query."<br />\n";

$res = sqlStatement($query);
?>
<html>
<head>
<?php html_header_show();?>
<title><?php xl('Lab Forms by Status','e'); ?></title>

<style type="text/css">@import url(../../../library/dynarch_calendar.css);</style>

<link rel=stylesheet href="<?php echo $css_header;?>" type="text/css">
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

.nowrap {
	white-space: nowrap;
}

</style>

<script type="text/javascript" src="../../../library/textformat.js"></script>
<script type="text/javascript" src="../../../library/dialog.js"></script>
<script type="text/javascript" src="../../../library/dynarch_calendar.js"></script>
<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
<script type="text/javascript" src="../../../library/dynarch_calendar_setup.js"></script>
<script type="text/javascript" src="../../../library/js/jquery.1.3.2.js"></script>

<script LANGUAGE="JavaScript">

 var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

 function dosort(orderby) {
  var f = document.forms[0];
  f.form_orderby.value = orderby;
  f.submit();
  return false;
 }

 function refreshme() {
  document.forms[0].submit();
 }

</script>

</head>
<body class="body_top">
<!-- Required for the popup date selectors -->
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

<span class='title'><?php xl('Report','e'); ?> - <?php xl('Lab Forms','e'); ?></span>

<div id="report_parameters_daterange">
<?php echo date("d F Y", strtotime($form_from_date)) ." &nbsp; to &nbsp; ". date("d F Y", strtotime($form_to_date)); ?>
</div>

<form method='post' name='theform' id='theform' action='lab_forms.php'>

<div id="report_parameters">
<table>
 <tr>
  <td width='900px'>
    <div style='float:left'>

      <table class='text'>
        <tr>
          <td class='label'><?php xl('Facility','e'); ?>: </td>
          <td>
	    <?php dropdown_facility(strip_escape_custom($form_facility), 'form_facility', true); ?></td>
          <td class='label'><?php xl('Provider','e'); ?>: </td>
          <td><?php
               // Build a drop-down list of providers.
              $query = "SELECT id, username, lname, fname FROM users WHERE authorized ".
                "= 1 $provider_facility_filter ORDER BY lname, fname";
              $ures = sqlStatement($query);

              echo "   <select name='form_provider'>\n";
              echo "    <option value=''>-- " . xl('All') . " --\n";

              while ($urow = sqlFetchArray($ures)) {
                $provid = $urow['username'];
                echo "    <option value='$provid'";
                if ($provid == $_POST['form_provider']) echo " selected";
                echo ">" . $urow['lname'] . ", " . $urow['fname'] . "\n";
              }
              echo "   </select>\n";
              ?></td>
           <td class='label'><?php xl('Laboratory','e'); ?>: </td>
          <td><?php
               // Build a drop-down list of lab names.
              $query = "SELECT option_id, title FROM list_options WHERE ".
                "list_id = 'Lab_Form_Labs' ORDER BY seq";
              $ures = sqlStatement($query);

              echo "   <select name='form_lab'>\n";
              echo "    <option value=''>-- " . xl('All') . " --\n";

              while ($urow = sqlFetchArray($ures)) {
                $labid = $urow['option_id'];
                echo "    <option value='$labid'";
                if ($labid == $_POST['form_lab']) echo " selected";
                echo ">" . $urow['title'] . "\n";
              }
              echo "   </select>\n";
              ?></td>
         </tr>
         </table>
      <table class='text'>
         <tr>
           <td class='label'><?php xl('From','e'); ?>: </td>
           <td>
             <input type='text' name='form_from_date' id="form_from_date" size='10' value='<?php echo $form_from_date ?>' onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
             <img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22' id='img_from_date' border='0' alt='[?]' style='cursor:pointer' title='<?php xl('Click here to choose a date','e'); ?>'></td>
           <td class='label'><?php xl('To','e'); ?>: </td>
           <td>
             <input type='text' name='form_to_date' id="form_to_date" size='10' value='<?php echo $form_to_date ?>' onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
             <img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22' id='img_to_date' border='0' alt='[?]' style='cursor:pointer' title='<?php xl('Click here to choose a date','e'); ?>'></td>
          <td class='label'><?php xl('Form Status','e'); ?>: </td>
          <td><?php
               // Build a drop-down list of form statuses.
              $query = "SELECT option_id, title FROM list_options WHERE ".
                "list_id = 'Lab_Form_Status' ORDER BY seq";
              $ures = sqlStatement($query);

              echo "   <select name='form_status'>\n";
              echo "    <option value=''>-- " . xl('All') . " --\n";

              while ($urow = sqlFetchArray($ures)) {
                $statid = $urow['option_id'];
                echo "    <option value='$statid'";
                if ($statid == $_POST['form_status']) echo " selected";
                echo ">" . $urow['title'] . "\n";
              }
              
              echo "   </select>\n";
              ?></td>
              
          <td class='label'><?php xl('Special Handling','e'); ?>: </td>
          <td><?php
               // Build a drop-down list of form statuses.
              $query = "SELECT option_id, title FROM list_options WHERE ".
                "list_id = 'Quest_Special_Handling' ORDER BY seq";
              $ures = sqlStatement($query);

              echo "   <select name='form_special'>\n";
              echo "    <option value=''>-- " . xl('All') . " --\n";

              while ($urow = sqlFetchArray($ures)) {
                $statid = $urow['option_id'];
                echo "    <option value='$statid'";
                if ($statid == $_POST['form_special']) echo " selected";
                echo ">" . $urow['title'] . "\n";
              }
              
              echo "   </select>\n";
              ?></td>
                  </tr>
       </table>

    </div>
  </td>
  <td align='left' valign='middle' height="100%">
    <table style='border-left:1px solid; width:100%; height:100%' >
      <tr>
        <td>
          <div style='margin-left:15px'>
            <a href='#' class='css_button' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
					<span>
						<?php xl('Submit','e'); ?>
					</span>
					</a>

            <?php if ($_POST['form_refresh'] || $_POST['form_orderby'] ) { ?>
            <a href='#' class='css_button' onclick='window.print()'>
						<span>
							<?php xl('Print','e'); ?>
						</span>
					</a>
            <?php } ?>
          </div>
        </td>
      </tr>
     </table>
  </td>
 </tr>
</table>

</div> <!-- end report_parameters -->

<?php
 if ($_POST['form_refresh'] || $_POST['form_orderby']) {
?>
<div id="report_results">
<table>

 <thead>
<?php if ($form_details) { ?>
  <th>
   <a href="nojs.php" onclick="return dosort('doctor')"
   <?php if ($form_orderby == "doctor") echo " style=\"color:#00cc00\"" ?>><?php  xl('Provider','e'); ?> </a>
  </th>
  <th>
   <a href="nojs.php" onclick="return dosort('time')"
   <?php if ($form_orderby == "time") echo " style=\"color:#00cc00\"" ?>><?php  xl('Date','e'); ?></a>
  </th>
  <th>
   <a href="nojs.php" onclick="return dosort('patient')"
   <?php if ($form_orderby == "patient") echo " style=\"color:#00cc00\"" ?>><?php  xl('Patient','e'); ?></a>
  </th>
  <th>
   <a href="nojs.php" onclick="return dosort('pubpid')"
   <?php if ($form_orderby == "pubpid") echo " style=\"color:#00cc00\"" ?>><?php  xl('ID','e'); ?></a>
  </th>
  <th>
   <?php  xl('Status','e'); ?>
  </th>
  <th>
   <?php  xl('Encounter','e'); ?>
  </th>
  <th>
   <?php  xl('Form','e'); ?>
  </th>
<?php } else { ?>
  <th><?php  xl('Provider','e'); ?></td>
  <th><?php  xl('Encounters','e'); ?></td>
<?php } ?>
 </thead>
 <tbody>
<?php
if ($res) {
  $lastdocname = "";
  $doc_encounters = 0;
  while ($row = sqlFetchArray($res)) {
    $docname = '';
    if (!empty($row['ulname']) || !empty($row['ufname'])) {
      $docname = $row['ulname'];
      if (!empty($row['ufname']) || !empty($row['umname']))
        $docname .= ', ' . $row['ufname'] . ' ' . $row['umname'];
    }

    $errmsg  = "";
    // FIX - These fields should be set in the master query now
    $this_id= $row['form_id'];
    $this_form= 'form_'.$row['formdir'];
    $sql = "SELECT * FROM $this_form WHERE id='$this_id'";
    $fdata = sqlStatement($sql);
    $farray = sqlFetchArray($fdata);
    $fstatus = '';
    if($row['formdir'] == 'whc_lab') $fstatus = $farray['wl_form_status'];

    // 2013-02-14 R.CRISWELL
    if ($row['formdir'] == 'quest_result' || $row['formdir'] == 'quest_order') {
    	$fstatus = $farray['status'];
    	if ($fstatus == 'u' || $fstatus == 'h') continue;
    	if ($form_status == 'f') {
    		if ($fstatus != 'x' && $fstatus != 'z') continue; // only keep results
    		if ($farray['result_abnormal'] == 0) continue; // only keep abnormal results 
    	}
    	else {
	    	if ($form_status && $form_status != $fstatus) continue;
    	}
    	if ($form_special > 0) {
    		if ($row['formdir'] == 'quest_order' && $farray['request_handling'] != $form_special) continue;
    		if ($row['formdir'] == 'quest_result' && $farray['result_handling'] != $form_special) continue;
    	}
    }
	else {    
	    if($form_status && ($form_status != $fstatus)) continue;
	    if ($form_special > 0) continue;
	}
    
    // 2013-02-14 R.CRISWELL
    // if($form_lab && ($form_lab != $farray['wl_lab'])) continue;
    if($form_lab && ($form_lab != $farray['wl_lab']))
    	if (($row['formdir'] == 'quest_result' || $row['formdir'] == 'quest_order') && $form_lab != 'q')
	    	continue;
    // ---------------------
    
    $link_ref="$rootdir/forms/whc_lab/clickable.php?click_id=$this_id&click_pid=".$row['ppid'];
    $status = ListLook($fstatus, 'Lab_Form_Status');
    if($status == 'Error' || $status == '') { $status = 'Unassigned'; }
?>
 <tr bgcolor='<?php echo $bgcolor ?>'>
  <td class="nowrap">
   <?php echo $docname; ?>&nbsp;
  </td>
  <td>
   <?php echo oeFormatShortDate(substr($row['date'], 0, 10)) ?>&nbsp;
  </td>
  <td>
   <?php echo $row['lname'] . ', ' . $row['fname'] . ' ' . $row['mname']; ?>&nbsp;
  </td>
  <td>
   <?php echo $row['pubpid']; ?>&nbsp;
  </td>
  <td>
   <?php echo $status; ?>&nbsp;
  </td>
  <td>
   <?php echo $row['reason']; ?>&nbsp;
  </td>
  <td style="min-width:130px">
 <?php 
    // 2013-02-14 R.CRISWELL
   	if ($row['formdir'] == 'quest_order')
   		$link_ref="$rootdir/forms/quest_order/update.php?id=$this_id&pid=".$row['ppid']."&enc=".$row['encounter'];
   	if ($row['formdir'] == 'quest_result')
   		$link_ref="$rootdir/forms/quest_result/update.php?id=$this_id&pid=".$row['ppid']."&enc=".$row['encounter'];
   	// ---------------------
  ?>
  <a href="<?php echo $link_ref; ?>" target="_blank" class="link_submit" onclick="top.restoreSession()"><?php echo $row['form_name']; ?></a>&nbsp;
  </td>
 </tr>
<?php
    $lastdocname = $docname;
  }

}
?>
</tbody>
</table>
</div>  <!-- end encresults -->
<?php } else { ?>
<div class='text'>
 	<?php echo xl('Please input search criteria above, and click Submit to view results.', 'e' ); ?>
</div>
<?php } ?>

<input type="hidden" name="form_orderby" value="<?php echo $form_orderby ?>" />
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>

</form>
</body>

<script language='JavaScript'>
 Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
 Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});

<?php if ($alertmsg) { echo " alert('$alertmsg');\n"; } ?>

</script>

</html>
