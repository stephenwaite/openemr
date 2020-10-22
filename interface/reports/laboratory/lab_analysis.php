<?php
/**
 * How to present clinical parameter.
 *
 * Copyright (C) 2014 Joe Slam <trackanything@produnis.de>
 *
 * LICENSE: This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://opensource.org/licenses/gpl-license.php>.
 *
 * @package OpenEMR
 * @author Joe Slam <trackanything@produnis.de>
 * @link http://www.open-emr.org
 * 
 * @abstract Modified May 2014 - Ron Criswell <ron.criswell@MDTechSvcs.com>
 * 
 * ---------------------------------------------------------------------------------
 *
 * this script needs $pid to run...
 *
 * if you copy this file to another place,
 * make sure you set $path_to_this_script
 * to the propper path...


 * Prepare your data:
 * this script expects propper 'result_code' entries
 * in table 'procedure_results'. If your data miss
 * 'result_code' entries, you won't see anything,
 * so make sure they are there.
 * [additionally, the script will also look for 'units',
 * 'normal' and 'code_text'. If these data are not available,
 * the script will run anyway...]
 *
 * the script will list all available patient's 'result_codes'
 * from table 'procedure_results'. Check those you wish to view.
 * If you see nothing to select, then
 *    a) there is actually no lab data of this patient available
 *    b) the lab data are missing 'result_code'-entries in table 'procedure_results'
 *

 */
// Some initial api-inputs
$sanitize_all_escapes  = true;
$fake_register_globals = false;
require_once("../../globals.php");
require_once("../../../library/options.inc.php");
require_once("../../../library/wmt/wmt.class.php");
include_once($GLOBALS["srcdir"] . "/api.inc");

// Set the path to this script
$path_to_this_script = $rootdir . "/reports/laboratory/lab_analysis.php";


// is this the printable HTML-option?
$printable = $_REQUEST['print'];
$popup = $_REQUEST['popup'];

// date parameters
$from_date	= $_REQUEST['form_from_date'];
if (!$from_date || strtotime($from_date) === false) $from_date = "";
$to_date  	= $_REQUEST['form_to_date'];
if (!$to_date || strtotime($to_date) === false) $to_date = "";


// main db-spell
//----------------------------------------
$main_spell  = "SELECT procedure_result.procedure_result_id, procedure_result.result, procedure_result.result_text,  procedure_result.result_code, procedure_result.result_data_type, procedure_result.units, procedure_result.abnormal, procedure_result.range, ";
$main_spell .= "procedure_result.date AS result_date, procedure_result.result_status, procedure_report.date_collected, procedure_report.review_status, ";
$main_spell .= "form_encounter.encounter AS encounter_id, form_encounter.date AS encounter_date, procedure_order.procedure_order_id AS order_number ";
$main_spell .= "FROM procedure_result ";
$main_spell .= "JOIN procedure_report ";
$main_spell .= "	ON procedure_result.procedure_report_id = procedure_report.procedure_report_id ";
$main_spell .= "JOIN procedure_order ";
$main_spell .= "	ON procedure_report.procedure_order_id = procedure_order.procedure_order_id ";
//$main_spell .= "JOIN procedure_providers ";
//$main_spell .= "	ON procedure_providers.ppid = procedure_order.lab_id ";
$main_spell .= "JOIN form_encounter ";
$main_spell .= "    ON form_encounter.encounter = procedure_order.encounter_id ";
$main_spell .= "WHERE procedure_result.result_code = ? "; 
$main_spell .= "AND procedure_order.patient_id = ? ";
$main_spell .= "AND procedure_result.result IS NOT NULL ";
$main_spell .= "AND procedure_result.result != '' ";
//$main_spell .= "AND ( procedure_result.result_data_type = 'NM' OR procedure_result.result_data_type = 'SN' ) ";
$main_spell .= "AND procedure_result.result REGEXP '^[0-9]*[.]{0,1}[0-9]*$' ";
$main_spell .= "AND procedure_order.date_ordered >= ? AND procedure_order.date_ordered <= ? ";
$main_spell .= "ORDER BY procedure_order.date_ordered DESC ";
//----------------------------------------

// some styles and javascripts
// ####################################################
echo "<html><head>";
?>
<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
<link rel="stylesheet"
	href="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/jquery.jqplot.min.css"
	type="text/css">
<link rel="stylesheet"
	href="<?php echo $web_root; ?>/library/wmt/wmt.default.css"
	type="text/css">

<script><?php include_once("{$GLOBALS['srcdir']}/restoreSession.php"); ?></script>
        <script src="<?php echo  $webroot ?>/library/js/jquery-1.9.1.min.js"></script>
        <script src="<?php echo  $webroot ?>/library/js/jquery-ui-1.10.0.custom.min.js"></script>
        <script src="<?php echo $webroot ?>/library/js/common.js"></script>

<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/jquery.jqplot.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasTextRenderer.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasAxisLabelRenderer.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.dateAxisRenderer.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.canvasAxisTickRenderer.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.categoryAxisRenderer.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.highlighter.min.js"></script>
<script
	src="<?php echo $web_root; ?>/library/js/jquery.plot-1.0.8/plugins/jqplot.cursor.min.js"></script>

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

<style>
input[type='radio'] {
	margin-top: -3px;
	vertical-align: middle;
}

input[type='checkbox'] {
	vertical-align: middle;
	margin-bottom: 6px;
}

.result {
	font-family: monospace;
	text-align: center;
	border: 1px solid black;
}

.result_table {
	width: 100%;
	border: 1px solid black;
	margin-bottom: 5px;
	border-collapse: collapse;
	font-size: 11px;
}

.result_title td {
	font-size: 8px;
	font-weight: bold;
	text-align: center;
}

.result_row {
	background-color: #fff;
	line-height: 12px;
}

#labdata, #labdata table {
	font-size: 9pt;
	font-family: helvetica;
}

#labdata h2 {
	font-size: 1.5em;
	margin-bottom: 12px;
}

#report_parameters table table td {
	padding: 1px 3px;
}
</style>

<script>
function loadEncounter(datestr, enc) {
	if ( (window.opener) && (window.opener.setEncounter) ) {
		window.opener.setEncounter(datestr, enc, 'RTop');
//		window.opener.setRadio('RTop', 'enc');
		window.opener.loadFrame('enc2', 'RTop', 'patient_file/encounter/encounter_top.php?set_encounter=' + enc);
	} else {
		parent.left_nav.setEncounter(datestr, enc, 'RTop');
//		parent.left_nav.setRadio('RTop', 'enc');
		parent.left_nav.loadFrame('enc2', 'RTop', 'patient_file/encounter/encounter_top.php?set_encounter=' + enc);
	}
}

(function($) {
	$(document).ready(function() {
		$("#state").val("<?php echo ($_POST['state'] == 'Toggle Off')? 'Toggle Off' : 'Toggle On' ?>");
    	$("#state").click(function() {
    	    if ($("#state").val() == "Toggle On") {
    	    	$(".include").prop("checked", true);
        	    $("#state").val("Toggle Off");
    	    }
    	    else {
    	    	$(".include").prop("checked", false);
    	    	$("#state").val("Toggle On");
    	    }
    	});

        $("tr.result_row").click(function() {
            if ( $(this).hasClass('hilite') ) {
                $(this).removeClass('hilite');
            }
            else {
            	$(this).addClass('hilite');
            }
        });

        $.jqplot.config.enablePlugins = true;
        $(".plot").click(function(event) {
            event.preventDefault();
            
            var chart = $(this).attr('chart');
            if ($("#chart_"+chart).is(':visible')) {
                $("#chart_"+chart).hide();
            }
            else {
                $("#chart_"+chart).show();
                eval("var points = data_"+chart+";");
                eval("var min = parseFloat(min_"+chart+");");
                eval("var max = parseFloat(max_"+chart+");");
                $.jqplot('chart_'+chart, [points], {
					title:						'Graphical Result Chart',
					autoscale:					true,
					axes:{
						xaxis:{
					    	renderer:			$.jqplot.DateAxisRenderer,
			                rendererOptions:{
			                   	tickRenderer:	$.jqplot.CanvasAxisTickRenderer
			                },
				            tickOptions:{
				            	fontSize:		'9px',
			    	            fontFamily:		'Tahoma',
			        	        angle:			-30,
						    	formatString:	'%Y-%m-%d'
					    	}
						},
						yaxis:{
							min:				min,
							max:				max,
							tickOptions:{
				            	formatString:	'%.2f'
				            }
						}
				  	},
					highlighter: {
				    	show: 					true,
				    	sizeAdjust: 			7.5
				    },
					cursor: {
				    	show: 					false
				    },
					series:[{lineWidth:1, markerOptions:{style:'square'}}]
				});
            }
		});

    });
})(jQuery);

var data;

</script>
</head>
<body class='body_top<?php if ($printable) echo " wmtPrint" ?>'>
	<span class='title'><?php xl('Report','e'); ?> - <?php xl('Lab Result Analysis','e'); ?></span>

	<form <?php if(!$printable) echo "id='theform'" ?> method='post'
		action='lab_analysis.php' onsubmit='return top.restoreSession()'
		style='width: 100%'>
		<div <?php if(!$printable) echo "id='report_parameters'" ?>>
			<table style='width: 100%'>
				<tr>
					<td style="width: 100%">
<?php ##############################################################################
// some patient data...
$spell  = "SELECT * ";
$spell .= "FROM patient_data ";
$spell .= "WHERE pid = ?";
//---
$myrow = sqlQuery($spell,array($pid));
$lastname = $myrow["lname"];
$firstname  = $myrow["fname"];
$DOB  = $myrow["DOB"];



if($printable || $popup) {
	if ($printable) {
		echo "<div class='no-print' style='float:right;margin-right:6%'>";
		echo "<input type='button' onclick='window.print()' value='print' />";
		echo "</div>\n";
	}
	echo "<table class='text' style='margin-bottom:10px;width:100%'>";
	echo "<tr><td style='width:90px'>" . xlt('Patient') . ": </td><td><b>" . text($lastname) . ", " . text($firstname) . "</b></td></tr>";
	echo "<tr><td>" . xlt('Patient ID') . ": </td><td>" . text($pid) . "</td></tr>";
	echo "<tr><td>" . xlt('Date of birth') . ": </td><td>" . text($DOB) . "</td></tr>";
	echo "<tr><td>" . xlt('Print date') . ": </td><td>" . text(date('Y-m-d H:i:s')) . "</td></tr>";
	if ($from_date || $to_date)
		echo "<tr><td>" . xlt('Date range') . ": </td><td>" . text($from_date) . " - " . text($to_date) ."</td></tr>";
		
	echo "</table>";
}

echo "<div>";
if (!$printable){
	echo "<input name='popup' value='".$popup."' type='hidden'/>\n";
	// What items are there for patient $pid?
	// -----------------------------------------------
	$value_list = array();
	$value_select = $_POST['value_code']; // what items are checkedboxed?
	$tab = 0;
	echo "<div style='font-weight:bold;padding-top:10px;float:left'>".xlt('Select the result items to be included in this report') . ": </div>";
	echo "<div style='float:right;margin:0 10px'><input type='button' id='state' value='Toggle On' /></div>";
	echo "<table class='text' style='clear:both;width:100%;margin-top:20px'>";
	echo "<tr><td style='whitespace:nowrap;vertical-align:top'>";

	$spell  = "SELECT procedure_result.result_code AS value_code, TRIM(procedure_result.result_text) AS value_text ";
	$spell .= "FROM procedure_result ";
	$spell .= "JOIN procedure_report ";
	$spell .= "	ON procedure_result.procedure_report_id = procedure_report.procedure_report_id ";
	$spell .= "JOIN procedure_order ";
	$spell .= "	ON procedure_report.procedure_order_id = procedure_order.procedure_order_id ";
	$spell .= "WHERE procedure_order.patient_id = ? ";
	$spell .= "AND procedure_result.result IS NOT NULL ";
	$spell .= "AND procedure_result.result != '' ";
//	$spell .= "AND ( procedure_result.result_data_type = 'NM' OR procedure_result.result_data_type = 'SN' ) ";
	$spell .= "AND procedure_result.result REGEXP '^[0-9]*[.]{0,1}[0-9]*$' ";
	$spell .= "GROUP BY value_code ORDER BY value_text ";
	$query  = sqlStatement($spell,array($pid));


	// Select which items to view...
	$i = 0;
	$rows = sqlNumRows($query);
	if (!$rows) {
		echo "<h3><br/><center>NO RESULT ITEMS AVAILABLE FOR THIS PATIENT</center></h3></td>";
	}
	else {
		$cols = round($rows/4);
		while($myrow = sqlFetchArray($query)){
			if (! $myrow['value_text']) continue;
			
			$my_key = str_replace('-','_',$myrow['value_code']);
			echo "<input class='include' type='checkbox' name='value_code[]' value=" . attr($myrow['value_code']) . " ";
			if($value_select){
				if (in_array($myrow['value_code'], $value_select)){
					echo "checked='checked' ";
				}
			}
			echo " /> " . text($myrow['value_code']."  :  ".substr($myrow['value_text'],0,32)) . "<br />";
			$value_list[$i][value_code] = $myrow['value_code'];
			$i++;
			$tab++;
			if($tab > $cols) {
				echo "</td><td style='whitespace:nowrap;vertical-align:top'>";
				$tab=0;
			}
		}
	} ?>
			
				
				</tr>
			</table>
		</div>

		<table class='text' style="margin-top: 10px; white-space: nowrap">
			<tr>
				<td class='label'><?php xl('From','e'); ?>: </td>
				<td><input type='text' name='form_from_date' id="form_from_date"
					size='10' value='<?php echo $from_date ?>'
					onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)'
					title='yyyy-mm-dd'> <img src='../../pic/show_calendar.gif'
					align='absbottom' width='24' height='22' id='img_from_date'
					border='0' alt='[?]' style='cursor: pointer'
					title='<?php xl('Click here to choose a date','e'); ?>'></td>
				<td class='label' style='padding-left: 10px'><?php xl('To','e'); ?>: </td>
				<td><input type='text' name='form_to_date' id="form_to_date"
					size='10' value='<?php echo $to_date ?>'
					onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)'
					title='yyyy-mm-dd'> <img src='../../pic/show_calendar.gif'
					align='absbottom' width='24' height='22' id='img_to_date'
					border='0' alt='[?]' style='cursor: pointer'
					title='<?php xl('Click here to choose a date','e'); ?>'></td>
<?php 	
	// Choose output mode [list vs. matrix]
	echo "<td style='padding-left:20px;font-weight:bold'>" . xlt('Select output') . ":</td>";
	echo "<td><input type='radio' name='mode' ";
	$mode = $_POST['mode'];
	if(!$mode || $mode == 'list'){
		echo "checked='checked' ";
	}
	echo " value='list'> " . xlt('List') . "</td>";

	echo "<td><input type='radio' name='mode' ";
	if($mode == 'matrix'){
		echo "checked='checked' ";
	}
	echo " value='matrix'> " . xlt('Matrix') . "</td>";

	echo "<td style='text-align:right;width:100%'><input style='font-size:1em' type='submit' name='submit' value='" . xla('Submit') . "' /></td>";
	echo "</tr></table>";
	echo "</form>";

} // end "if printable"
echo "</td></tr></table></td></tr></table></div><br/>";

// print results of patient's items
//-------------------------------------------
$nothing = true;
$mode = $_POST['mode'];
$value_select = $_POST['value_code'];
$start = ($from_date) ? $from_date.' 00:00:00' : '1961-01-01 00:00:00';
$finish = ($to_date) ? $to_date.' 23:59:59' : date('Y-m-d H:i:s');

// are some Items selected?
if($value_select){

	// print in List-Mode
	if($mode=='list'){

		// process each observation
		foreach($value_select as $this_value){
			$results = "";
			$value_count = 0;
			$norm_top = 0;
			$norm_bot = 0;
			$value_array = array(); // reset local array
			$date_array  = array();//  reset local array
			$this_key = str_replace('-','_',$this_value);

			// get data from db
			$spell  = $main_spell;
			$query  = sqlStatement($spell,array($this_value,$pid,$start,$finish));
			while($myrow = sqlFetchArray($query)){
				$nothing = false;
				if ($last_code != $this_value) {
?>
<div id="chart_<?php echo $this_key ?>"
					style="margin: 10px 30px 30px 30px; height: 200px; width: 90%; display: none"></div>

				<table class="result_table">
					<tr class="result_title wmtCollapseBar"" >
						<td style="width: 30%; text-align: left"><span
							id="spacer_<?php echo $this_key ?>" style="display: none">&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;</span><a
							href="#chart_<?php echo $this_key ?>"
							id="plot_<?php echo $this_key ?>" class="plot"
							chart="<?php echo $this_key ?>">CHART</a>&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;RESULT
							DESCRIPTION</td>
						<td style="width: 10%">VALUE</td>
						<td style="width: 10%">UNITS</td>
						<td style="width: 10%">REFERENCE</td>
						<td style="width: 12%">FLAG</td>
						<td style="width: 12%">REPORTED</td>
						<td style="width: 8%">STATUS</td>
						<td style="width: 10%">ENCOUNTER</td>
					</tr>
<?php }
				$last_code = $this_value;
				$norms = explode("-",$myrow['normal']);
				$abnormal = $myrow['abnormal']; // in case they sneak in a new status
				if ($abnormal == 'H') $abnormal = 'High';
				if ($abnormal == 'L') $abnormal = 'Low';
				if ($abnormal == 'HH') $abnormal = 'Alert High';
				if ($abnormal == 'LL') $abnormal = 'Alert Low';
				if ($abnormal == '>') $abnormal = 'Panic High';
				if ($abnormal == '<') $abnormal = 'Panic Low';
				if ($abnormal == 'A') $abnormal = 'Abnormal';
				if ($abnormal == 'AA') $abnormal = 'Critical';
				if ($abnormal == 'S') $abnormal = 'Susceptible';
				if ($abnormal == 'R') $abnormal = 'Resistant';
				if ($abnormal == 'I') $abnormal = 'Intermediate';
				if ($abnormal == 'NEG') $abnormal = 'Negative';
				if ($abnormal == 'POS') $abnormal = 'Positive';
				?>
	<tr class="result_row" style="font-weight:bold;<?php if ($abnormal) echo 'color:#bb0000' ?>">
						<td class="result" style="text-align: left"><?php echo $myrow['result_code'] ?>
			- <?php echo substr($myrow['result_text'],0,32) ?>
		</td>
<?php 
				if ($myrow['result_data_type']) { // there is an observation
					if ($myrow['result'] == ".") $myrow['result'] = '';
					$results[] = array('date'=>$myrow['result_date'],'value'=>$myrow['result']);
?>
		<td class="result"><?php echo htmlentities($myrow['result'])?>
		</td>
						<td class="result"><?php echo htmlentities($myrow['units']) ?>
		</td>
						<td class="result"><?php echo htmlentities($myrow['range']) ?>
		</td>
						<td class="result"><?php echo $abnormal ?>
		</td>
						<td class="result"><?php echo (strtotime($myrow['result_date']))? date('Y-m-d',strtotime($myrow['result_date'])): '' ?>
		</td>
						<td class="result"><?php echo htmlentities($myrow['result_status']) ?>
		</td>
						<td class="result">
<?php if (!$printable) { 
	    	$link_ref="$rootdir/forms/form_".$row['type']."/update.php?id=".$myrow['form_id']."&pid=".$pid."&enc=".$myrow['encounter_id']."&pop=1";
	    ?>

			<!-- a href="<?php echo $link_ref; ?>" target="_blank" class="link_submit" 
				onclick="top.restoreSession()">Result Form - <?php echo $myrow['order_number']; ?></a>&nbsp; -->
							<!-- a href="#" onclick="parent.left_nav.loadFrame2('ens1', 'RBot', 'patient_file/encounter/encounter_top.php?set_encounter=<?php echo attr($myrow['encounter_id']) ?>')" -->
							<!-- a href="#" onclick="window.opener.setEncounter('<?php echo date('Y-m-d',strtotime($myrow['encounter_date']))?>','<?php echo $myrow['encounter_id'] ?>', '');window.opener.loadCurrentEncounterFromTitle();" -->
							<a href="#"
							onclick="loadEncounter('<?php echo date('Y-m-d',strtotime($myrow['encounter_date']))?>','<?php echo $myrow['encounter_id'] ?>');">
								<!-- a href="<?php echo $web_root; ?>/interface/patient_file/encounter/encounter_top.php?set_encounter=<?php echo attr($myrow['encounter_id']) ?>" target="_blank" -->
			<?php echo htmlentities($myrow['encounter_id']) ?></a>
<?php } else { ?>
			<?php echo htmlentities($myrow['encounter_id']) ?>
<?php } ?>
		</td>
					</tr>
<?php
				} // end if observ
			} // end result while
?>
			</table>
				<script>
<?php 
				$min = 0;
				$max = 0;
				echo "var data_".$this_key." = [];\n";
				if (count($results) < 2) {
					echo "$('#plot_".$this_key."').hide();";
					echo "$('#spacer_".$this_key."').show();";
				}
				else {
					foreach ($results AS $item) {
						if ($item['value'] < $min) $min = $item['value'];
						if ($item['value'] > $max) $max = $item['value'];
						echo "data_".$this_key.".push(['".$item['date']."','".$item['value']."']);\n";
					}
				}
				if (is_numeric($norms[0]) && is_numeric($norms[1])) {
					if ($min > $norms[0]) $min = $norms[0];
					if ($max < $norms[1]) $max = $norms[1];
				}
				echo "var min_".$this_key." = ".$min.";\n";
				echo "var max_".$this_key." = ".$max.";\n";
?>
			</script>
<?php 
		} // end foreach selected value
	}// end if mode = list

		//##########################################################################################################################
		if($mode=='matrix'){

			$value_matrix = array();
			$datelist = array();
			$i = 0;

			// get all data of patient's items
			foreach($value_select as $this_value){

				$spell  = $main_spell;
				$query  = sqlStatement($spell,array($this_value,$pid,$start,$finish));

				while($myrow = sqlFetchArray($query)){
					$value_matrix[$i]['result_id'] 			= $myrow['procedure_result_id'];
					$value_matrix[$i]['result_code'] 		= $myrow['result_code'];
					$value_matrix[$i]['result_text'] 		= $myrow['result_text'];
					$value_matrix[$i]['result'] 			= $myrow['result'];
					$value_matrix[$i]['units'] 				= $myrow['units'];
					$value_matrix[$i]['range'] 				= $myrow['range'];
					$value_matrix[$i]['abnormal'] 			= $myrow['abnormal'];
					$value_matrix[$i]['review_status'] 		= $myrow['review_status'];
					$value_matrix[$i]['encounter_id'] 		= $myrow['encounter_id'];
					$value_matrix[$i]['date_collected'] 	= $myrow['date_collected'];
					$value_matrix[$i]['result_date'] 		= $myrow['result_date'];
					$datelist[]								= $myrow['date_collected'];
					$i++;
				}
			}

			// get unique datetime
			$datelist = array_unique($datelist);

			// sort datetime DESC
			rsort($datelist);

			// sort item-data
			foreach($value_matrix as $key => $row) {
				$result_code[$key] = $row['result_code'];
				$result_date[$key] = $row['result_date'];
			}
			array_multisort(array_map('strtolower',$result_code), SORT_ASC, $result_date, SORT_DESC, $value_matrix);

			$cellcount = count($datelist);
			$itemcount = count($value_matrix);
			
			$nothing = false;

?>
			<div style="width: 100%; overflow: auto">
					<table class="result_table" style="width: 100%">
						<tr class="result_title wmtCollapseBar">
							<td style="width: 30%; min-width: 250px">RESULT NAME</td>
							<td style="width: 10%">UNITS</td>
							<td style="width: 10%">REFERENCE</td>
<?php 
			foreach($datelist as $this_date){
				echo "<td style='width:50px;max-width:50px;text-align:right'>" . date('Y-m-d',strtotime($this_date))."<br/>".date('h:i A',strtotime($this_date)) . "</td>\n";
			}
			if ($cellcount < 10) {
				$width = round((10-$cellcount)*50);
				echo "<td style='width:".$width."px'>&nbsp;</td>\n";
			}
?>
				</tr>
				
<?php
			$nextval = 0;
			$lastcode = 'FIRST';
			while ($itemcount > $nextval) {
				$myrow = $value_matrix[$nextval++];
				$lastcode = $myrow['result_code'];
?>
				<tr class="result_row">
							<td class="result" style="text-align: left"><?php echo $myrow['result_code'] ?>
						- <?php echo $myrow['result_text'] ?>
					</td>
							<td class="result">
						<?php echo htmlentities($myrow['units']) ?>
					</td>
							<td class="result" style="border-right: 3px solid grey">
						<?php echo htmlentities($myrow['range']) ?>
					</td>
<?php 
				$nextdate = 0;
				while ($nextdate < $cellcount) {
?>
						<td class='result'style="font-weight:bold;<?php if ($myrow['abnormal']) echo 'color:#bb0000' ?>">
<?php 
					if ($myrow['date_collected'] == $datelist[$nextdate++]) { 					
						if ($myrow['result'] != ".") echo htmlentities($myrow['result']);
						if ($nextdate < $cellcount) $myrow = $value_matrix[$nextval++];
					}
					echo "</td>\n";
				}
				if ($cellcount < 10) echo "<td style='border:1px solid black;background-color: #fff'>&nbsp;</td>\n";
				echo "</tr>";
			} // end next item
			echo "</table></div>";
		}// end if mode = matrix
	} else { // end of "are items selected"
		$nothing = TRUE;
	}


	if(!$printable){
		if(!$nothing && $mode == 'list'){
			echo "<p>";
			$target = ($popup)? '_self': '_blank';
			echo "<form method='post' action='" . $path_to_this_script . "' target='".$target."' onsubmit='return top.restoreSession()'>";
			echo "<input type='hidden' name='mode' value='". attr($mode) . "'>";
			echo "<input type='hidden' name='form_from_date' value='". attr($from_date) . "'>";
			echo "<input type='hidden' name='form_to_date' value='". attr($to_date) . "'>";
			foreach($_POST['value_code'] as $this_valuecode) {
				echo "<input type='hidden' name='value_code[]' value='". attr($this_valuecode) . "'>";
			}
			echo "<input type='submit' name='print' value='" . xla('Print Preview') . "' />";
			echo "</form>";
		}
		if ($nothing) {
			echo "<p>" . xlt('No qualifying records') . ".</p>";
		}
		if (!$printable && !$popup) {
			echo "<br><a href='../../patient_file/summary/demographics.php' ";
			if (!$GLOBALS['concurrent_layout']){
				echo "target='Main'";
			}
			echo " class='css_button' onclick='top.restoreSession()'>";
			echo "<span>" . xlt('Back to Patient') . "</span></a>";
		}


	} else {
		echo "<p>" . xlt('End of report') . ".</p>";
	} ?>
	
		</span>
<?php 
	if (!$printable) { ?>
		<script language='JavaScript'>
			Calendar.setup({inputField:"form_from_date", ifFormat:"%Y-%m-%d", button:"img_from_date"});
			Calendar.setup({inputField:"form_to_date", ifFormat:"%Y-%m-%d", button:"img_to_date"});
			<?php if ($alertmsg) { echo " alert('$alertmsg');\n"; } ?>
		</script>
<?php 
	} ?>
		
		<br>
							<br>
								</div>

</body>
</html>
