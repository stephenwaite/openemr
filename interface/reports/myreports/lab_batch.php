<?php
/** **************************************************************************
 *	MYREPORTS/LAB_BATCH.PHP
 *
 *	Copyright (c)2013 - Williams Medical Technology, Inc.
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
 *  @package quest
 *  @subpackage myreports
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 *  @uses quest_order/common.php
 * 
 *************************************************************************** */

require_once("../../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/billing.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/formatting.inc.php");
require_once "$srcdir/options.inc.php";
require_once "$srcdir/formdata.inc.php";
require_once "$srcdir/wmt/wmt.include.php";

?>
<html>
<head>
<?php html_header_show();?>
<title><?php xl('Quest Batch Result Processing','e'); ?></title>
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

</style>

<script type="text/javascript" src="../../../library/textformat.js"></script>
<script type="text/javascript" src="../../../library/dialog.js"></script>
<script type="text/javascript" src="../../../library/dynarch_calendar.js"></script>
<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
<script type="text/javascript" src="../../../library/dynarch_calendar_setup.js"></script>
<script type="text/javascript" src="../../../library/js/jquery.1.3.2.js"></script>

<script>
	function doSubmit() {
		$('#theform').submit();
	}
</script>
</head>
<body class="body_top">
<span class='title'><?php xl('Quest Batch Processing','e'); ?></span>

<form method='post' name='theform' id='theform' action='../../forms/quest_result/batch.php'>

<div id="report_parameters">
<table>
 <tr>
  <td width='800px'>
    <div style='float:left'>

      <table class='text'>
        <tr>
		  <td class='label'><?php xl('Include Details','e'); ?>: </td>
		    <td>
          	<input type='checkbox' id='form_debug' name='form_debug' value='1' /></td>
          	</tr>
       </table>

    </div>
  </td>
  <td align='left' valign='middle' height="100%">
    <table style='border-left:1px solid; width:100%; height:100%' >
      <tr>
        <td>
          <div style='margin-left:15px'>
            <a href='#' class='css_button' onclick='doSubmit()'>
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

<div class='text'>
 	<?php echo xl('Please select whether to include details and click Submit to retrieve latest lab results.', 'e' ); ?>
</div>
<input type="hidden" name="browser" value="1" />
</form>
</body>

</html>
