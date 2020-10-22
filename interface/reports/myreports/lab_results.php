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
  'doctor'  => 'lower(u.lname), lower(u.fname), qr.specimen_datetime',
  'patient' => 'lower(qr.request_pat_last), lower(qr.request_pat_first), qr.specimen_datetime',
  'order'  => 'lower(qr.request_order), qr.specimen_datetime',
  'time'    => 'qr.specimen_datetime, lower(u.lname), lower(u.fname)',
  'status'    => 'qr.status, qr.specimen_datetime, lower(u.lname), lower(u.fname)',
);

$SITEHASH = array();
$sres = sqlStatement("SELECT * FROM list_options WHERE list_id = 'Quest_Site_Identifiers' ");
while ($rec = sqlFetchArray($sres)) {
	$SITEHASH[$rec['option_id']] = $rec['title'];
}

$last_month = mktime(0,0,0,date('m')-1,date('d'),date('Y'));
$form_from_date = fixDate($_POST['form_from_date'], date('Y-m-d', $last_month));
$form_to_date = fixDate($_POST['form_to_date'], date('Y-m-d'));
$form_provider  = $_POST['form_provider'];
$form_facility  = $_POST['form_facility'];
$form_status  = $_POST['form_status'];
$form_name      = $_POST['form_name'];
$form_lab	= $_POST['form_lab'];
$form_details   = "1";
$form_ignore = $_POST['form_ignore']; // there was a request to ignore an order

// hide an result
if ($form_ignore) {
	sqlStatement("UPDATE form_quest_result SET status='h' WHERE id = '".$form_ignore."' ");
	$form_ignore = '';
}

$form_orderby = $ORDERHASH[$_REQUEST['form_orderby']] ? $_REQUEST['form_orderby'] : 'doctor';
$orderby = $ORDERHASH[$form_orderby];

$query = "SELECT qr.*, u.lname AS ulname, u.fname AS ufname, u.mname AS umname, u.username AS username FROM form_quest_result qr " .
	"LEFT JOIN users u ON qr.request_provider = u.id WHERE qr.request_id = 0 ";
if ($form_to_date) {
  $query .= "AND specimen_datetime >= '$form_from_date 00:00:00' AND specimen_datetime <= '$form_to_date 23:59:59' ";
}
if ($form_provider) {
  $query .= "AND request_provider = '$form_provider' ";
}
if ($form_facility) {
  $query .= "AND request_facility = '".$SITEHASH[$form_facility]."' ";
}
if ($form_status) {
	$query .= "AND (qr.status = 'u' OR qr.status = 'h') ";
}
else {
	$query .= "AND qr.status = 'u' ";
}
$query .= "ORDER BY $orderby";

$res = sqlStatement($query);
?>
<html>
<head>
<?php html_header_show();?>
<title><?php xl('Orphan Lab Results','e'); ?></title>

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

#report_results table td {
	vertical-align:middle;
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
	 document.forms[0].form_ignore.value = '';
	document.forms[0].submit();
 }

 function doignore(id) {
	 	document.forms[0].form_ignore.value = id;
		document.forms[0].submit();
	 }

 function dosearch(id) {
	 url = "<?php echo $webroot ?>/interface/forms/quest_result/link_result.php?id=" + id;
	 dlgopen(url, 'search', 800, 500);
 }

 function showdoc(pid,docid) {
	 location.href="<?php echo $webroot ?>/controller.php?document&retrieve&patient_id=" + pid + "&document_id=" + docid;
 }

 
</script>

</head>
<body class="body_top">
<!-- Required for the popup date selectors -->
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

<span class='title'><?php xl('Report','e'); ?> - <?php xl('Orphan Lab Results','e'); ?></span>

<div id="report_parameters_daterange">
<?php echo date("d F Y", strtotime($form_from_date)) ." &nbsp; to &nbsp; ". date("d F Y", strtotime($form_to_date)); ?>
</div>

<form method='post' name='theform' id='theform' action='lab_results.php'>
<input type='hidden' name='form_ignore' id='form_ignore' value='' />
<div id="report_parameters">
<table>
 <tr>
  <td width='auto'>
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
                $provid = $urow['id'];
                echo "    <option value='$provid'";
                if ($provid == $_POST['form_provider']) echo " selected";
                echo ">" . $urow['lname'] . ", " . $urow['fname'] . "\n";
              }
              echo "   </select>\n";
              ?></td>
         </tr>
         <tr>
           <td class='label'><?php xl('From','e'); ?>: </td>
           <td>
             <input type='text' name='form_from_date' id="form_from_date" size='10' value='<?php echo $form_from_date ?>' onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
             <img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22' id='img_from_date' border='0' alt='[?]' style='cursor:pointer' title='<?php xl('Click here to choose a date','e'); ?>'></td>
           <td class='label'><?php xl('To','e'); ?>: </td>
           <td>
             <input type='text' name='form_to_date' id="form_to_date" size='10' value='<?php echo $form_to_date ?>' onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' title='yyyy-mm-dd'>
             <img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22' id='img_to_date' border='0' alt='[?]' style='cursor:pointer' title='<?php xl('Click here to choose a date','e'); ?>'></td>
          <td class='label' style='white-space:nowrap'><?php xl('Include Inactive','e'); ?>: </td>
          <td><?php
               // Include hidden records?
              echo "   <input type='checkbox' name='form_status' value='1' ";
              echo ($form_status)?"checked":"";
              echo " />\n";
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
   <a href="nojs.php" onclick="return dosort('order')"
   <?php if ($form_orderby == "order") echo " style=\"color:#00cc00\"" ?>><?php  xl('Order','e'); ?></a>
  </th>
  <th>
   <a href="nojs.php" onclick="return dosort('status')"
   <?php if ($form_orderby == "status") echo " style=\"color:#00cc00\"" ?>><?php  xl('Status','e'); ?></a>
  </th>
  <th>&nbsp;</th>
 </thead>
 <tbody>
<?php
if ($res) {
  $lastdocname = "";
  $doc_encounters = 0;
  while ($row = sqlFetchArray($res)) {
    $docname = '';
    if ($row['username'] == 'quest' || $row['username'] == '') {
    	$docname = '[ NO PROVIDER ]';
    }
    else {
    if (!empty($row['ulname']) || !empty($row['ufname'])) {
      $docname = $row['ulname'];
      if (!empty($row['ufname']) || !empty($row['umname']))
        $docname .= ', ' . $row['ufname'] . ' ' . $row['umname'];
    }
    }
    $errmsg  = "";
    $status = ($row['status'] == 'u') ? 'Orphan Active' : 'Orphan Inactive';
?>
 <tr bgcolor='<?php echo $bgcolor ?>'>
  <td>
   <?php echo ($docname)?$docname:''; ?>&nbsp;
  </td>
  <td>
   <?php echo oeFormatShortDate(substr($row['date'], 0, 10)) ?>&nbsp;
  </td>
  <td>
   <?php 
   if ($row['request_pat_last']) {	
	   echo $row['request_pat_last'] . ', ' . $row['request_pat_first'] . ' ' . $row['request_pat_middle'];
	}
	else {
		echo "[ NO PATIENT DATA ]";
	}
	?>
  </td>
  <td>
   <?php echo ($row['request_order']) ? $row['request_order'] : "[ NONE ]"; ?>&nbsp;
  </td>
  <td>
   <?php echo $status; ?>&nbsp;
  </td>
  <td style="text-align:right">
	  <input tabindex="-1" type="button" class="link_submit" onclick="dosearch(<?php echo $row['id'] ?>)"  value=" link " />&nbsp;
  <?php if ($row['document_id']) { ?>
		<input tabindex="-1" type="button" onclick="showdoc(<?php echo $row['pid'].",".$row['document_id'] ?>)" value="view" />
  <?php } ?>
	  <input tabindex="-1" type="button" class="link_submit" onclick="doignore(<?php echo $row['id'] ?>)"  value="hide" />&nbsp;
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
