<?php
/** **************************************************************************
 *	QUEST_ORDER/COMMON.PHP
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

// grab inportant stuff
$id = '';
if ($viewmode) $id = $_GET['id'];
//$pid = ($pid)? $pid : $_SESSION['pid'];
//$encounter = ($encounter)? $encounter : $_SESSION['encounter'];
if (! $pid) die ("Missing patient identifier!!");
if (! $encounter) die ("Missing current encounter identifier!!");

$form_name = 'quest_order';
$form_title = 'Quest Lab Order';
$save_url = $rootdir.'/forms/'.$form_name.'/save.php';
$validate_url = $rootdir.'/forms/'.$form_name.'/validate.php';
$submit_url = $rootdir.'/forms/'.$form_name.'/submit.php';
$print_url = $rootdir.'/forms/'.$form_name.'/print.php?id='.$id;
$abort_url = $rootdir.'/patient_file/summary/demographics.php';
$reload_url = $rootdir.'/patient_file/encounter/view_form.php?formname=quest_order&id=';
$cancel_url = $rootdir.'/patient_file/encounter/encounter_top.php';
$document_url = $GLOBALS['web_root'].'/controller.php?document&retrieve&patient_id='.$pid.'&document_id=';

/* RETRIEVE FORM DATA */
try {
	$form_data = new wmtForm($form_name,$id);
	$pat_data = wmtPatient::getPidPatient($pid);
	$ins_list = wmtInsurance::getPidInsurance($pid);
	$enc_data = wmtEncounter::getEncounter($encounter);
}
catch (Exception $e) {
	print "FATAL ERROR ENCOUNTERED: ";
	print $e->getMessage();
	exit;
}

// get quest site id
$GLOBALS['lab_quest_siteid'] = ListLook($enc_data->facility_id, 'Quest_Site_Identifiers');

// set form status
$completed = FALSE;
if ($form_data->id && $form_data->status != 'i') $completed = TRUE;

// VALIDATE INSTALL
$invalid = "";
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
<h1>Quest Diagnostic Interface Not Available</h1>
The interface is not enabled, not properly configured, or required components are missing!!
<br/><br/>
For assistance with implementing this service contact:
<br/><br/>
<a href="http://www.williamsmedtech.com/page4/page4.html" target="_blank"><b>Williams Medical Technologies Support</b></a>
<br/><br/>
<table style="border:2px solid red;padding:20px"><tr><td style="white-space:pre;color:red"><h3>DEBUG OUTPUT</h3><?php echo $invalid ?></td></tr></table>
<?php
exit; 
}

// test items for the order
$test_list = array();
if ($form_data->id) {
	$query = "SELECT  * FROM form_quest_order_item WHERE parent_id = '$form_data->id' ORDER BY id";
	$result = sqlStatement($query);
	while ($row = sqlFetchArray($result)) {
		$test_list[] = $row;
	}
}

// retrieve diagnosis quick list
if ($GLOBALS['wmt_lab_icd10']) {
	$query = "SELECT title, notes, formatted_dx_code AS code, short_desc, long_desc FROM list_options l ";
	$query .= "JOIN icd10_dx_order_code c ON c.formatted_dx_code = l.option_id AND c.active = 1 ";
	$query .= "WHERE l.list_id LIKE 'Lab\_ICD10%' ";
	$query .= "ORDER BY l.title, l.seq";
	$result = sqlStatement($query);
} else {
	$query = "SELECT title, notes, formatted_dx_code AS code, short_desc, long_desc FROM list_options l ";
	$query .= "JOIN icd9_dx_code c ON c.formatted_dx_code = l.option_id AND c.active = 1 ";
	$query .= "WHERE l.list_id LIKE 'Quest\_Diagnosis%' ";
	$query .= "ORDER BY l.title, l.seq";
	$result = sqlStatement($query);
}

$dlist = array();
while ($data = sqlFetchArray($result)) {
	// create array ('tab title','icd9 code','short title','long title')
	$dlist[] = $data;
}

// retrieve order quick list
$query = "SELECT title, notes, test_cd AS code, description FROM list_options l ";
$query .= "JOIN cdc_order_codes c ON c.test_cd = l.option_id ";
$query .= "WHERE l.list_id LIKE 'Quest_Laboratory%' ";
$query .= "ORDER BY l.title, l.seq";
$result = sqlStatement($query);

$olist = array();
while ($data = sqlFetchArray($result)) {
	// create array ('tab title','icd9 code','short title','long title')
	$olist[] = $data;
}

if (!function_exists('UserIdLook')) {
	function UserIdLook($thisField) {
	  if(!$thisField) return '';
	  $ret = '';
	  $rlist= sqlStatement("SELECT * FROM users WHERE id='" .
	           $thisField."'");
	  $rrow= sqlFetchArray($rlist);
	  if($rrow) {
	    $ret = $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
	  }
	  return $ret;
	}
}

function getLabelers($thisField) {
	$rlist= sqlStatement("SELECT * FROM list_options WHERE list_id = 'Quest_Label_Printers' ORDER BY seq, title");
	
	$active = '';
	$default = '';
	$labelers = array();
	while ($rrow= sqlFetchArray($rlist)) {
		if ($thisField == $rrow['option_id']) $active = $rrow['option_id'];
		if ($rrow['is_default']) $default = $rrow['option_id'];
		$labelers[] = $rrow; 
	}

	if (!$active) $active = $default;
	
	echo "<option value=''";
	if (!$active) echo " selected='selected'";
	echo ">&nbsp;</option>\n";
	foreach ($labelers AS $rrow) {
		echo "<option value='" . $rrow['option_id'] . "'";
		if ($active == $rrow['option_id']) echo " selected='selected'";
		echo ">" . $rrow['title'];
		echo "</option>\n";
	}
}

?>

<!DOCTYPE HTML>
<html>
	<head>
		<?php html_header_show();?>
		<title><?php echo $form_title; ?></title>

		<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
		<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.css" media="screen" />
		<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/interface/forms/quest_order/style_wmt.css" media="screen" />
		<!-- link rel="stylesheet" type="text/css" href="http://code.jquery.com/ui/1.10.0/themes/base/jquery-ui.css" media="screen" / -->
		
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-1.7.2.min.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/jquery-ui-1.10.0.custom.min.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/common.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/js/fancybox-1.3.4/jquery.fancybox-1.3.4.pack.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/dialog.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/overlib_mini.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/textformat.js"></script>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/wmt/wmtstandard.js"></script>
		
		<!-- pop up calendar -->
		<style type="text/css">@import url(<?php echo $GLOBALS['webroot'] ?>/library/dynarch_calendar.css);</style>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/dynarch_calendar.js"></script>
		<?php include_once("{$GLOBALS['srcdir']}/dynarch_calendar_en.inc.php"); ?>
		<script type="text/javascript" src="<?php echo $GLOBALS['webroot'] ?>/library/dynarch_calendar_setup.js"></script>
	
<style>
.Calendar tbody .day { border: 1px solid inherit; }

.wmtMainContainer table { font-size: 12px; }
.wmtMainContainer fieldset { margin-top: 0; }

.css_button_small { background: transparent url( '../../../images/bg_button_a_small.gif' ) no-repeat scroll top right; }
.css_button_small span { background: transparent url( '../../../images/bg_button_span_small.gif' ) no-repeat; }
.css_button { background: transparent url( '../../../images/bg_button_a.gif' ) no-repeat scroll top right; }
.css_button span { background: transparent url( '../../../images/bg_button_span.gif' ) no-repeat; }
</style>

		<script language="JavaScript">
			var mypcc = '<?php echo $GLOBALS['phone_country_code'] ?>';

			// validate data and submit form
			function saveClicked() {
				var f = document.forms[0];
				$resp = confirm("Your order will be saved but will NOT be submitted.\n\nClick 'OK' to save and exit.");
				if ($resp) {
					if (top.frames.length > 0) top.restoreSession();
					f.submit();
				}
 			}

			function submitClicked() {
				// minimum validation
				notice = '';
				$('.aoe').each(function() {
					if (!$(this).val()) notice = "\n- All order questions must be answered."; 
				});
				if ($('.code').length < 1) notice += "\n- At least one diagnosis code required.";
				if ($('.test').length < 1) notice += "\n- At least one profile / test code required.";
				if ($('#request_provider').val() == '_blank') notice += "\n- An ordering physician is required.";

				if (notice) {
					notice = "PLEASE CORRECT THE FOLLOWING:\n" + notice;
					alert(notice);
					return;
				}

				$.fancybox.showActivity();
				
				$('#process').val('1'); // flag doing submit
				
				$.ajax ({
					type: "POST",
					url: "<?php echo $save_url ?>",
					data: $("#<?php echo $form_name; ?>").serialize(),
					success: function(data) {
			            $.fancybox({
			                'content' 				: data,
							'overlayOpacity' 		: 0.6,
							'showCloseButton' 		: false,
							'width'					: '800px',
							'height' 				: '400px',
							'centerOnScroll' 		: false,
							'autoScale'				: false,
							'autoDimensions'		: false,
							'hideOnOverlayClick' 	: false
						});
					}
				});
			}

 			function openPrint() {
				<?php if ($mode=='single') { ?>
				location.href="<?php echo $print_url ?>";
				<?php } else { ?>
				top.restoreSession();
				window.open('<?php echo $print_url ?>','_blank');
				return;
				<?php } ?>
 			}

			function doClose() {
				<?php if ($mode=='single') { ?>
				window.close();
				<?php } else { ?>
				top.restoreSession();
				window.location='<?php echo $cancel_url ?>';
				<?php } ?>
			}
			
			function doReturn(id) {
				<?php if ($mode=='single') { ?>
				window.close();
				<?php } else { ?>
				top.restoreSession();
				window.location= '<?php echo $reload_url?>'+id;
				<?php } ?>
			}
			
 			 // define ajax error handler
			$(function() {
			    $.ajaxSetup({
			        error: function(jqXHR, exception) {
			            if (jqXHR.status === 0) {
			                alert('Not connect to network.');
			            } else if (jqXHR.status == 404) {
			                alert('Requested page not found. [404]');
			            } else if (jqXHR.status == 500) {
			                alert('Internal Server Error [500].');
			            } else if (exception === 'parsererror') {
			                alert('Requested JSON parse failed.');
			            } else if (exception === 'timeout') {
			                alert('Time out error.');
			            } else if (exception === 'abort') {
			                alert('Ajax request aborted.');
			            } else {
			                alert('Uncaught Error.\n' + jqXHR.responseText);
			            }
			        }
			    });

			    return false;
			});

			// search for the provided icd9 code
			function searchDiagnosis() {
				var output = '';
				var f = document.forms[0];
				var code = f.searchIcd.value;
				if ( code == '' ) { 
					alert('You must enter a diagnosis search code.');
					return;
				}
				
				// retrieve the diagnosis array
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/quest/QuestAjax.php",
					dataType: "json",
					data: {
						type: 'icd9',
						code: code
					},
					success: function(data) {
				    	$.each(data, function(key, val) {
					    	id = val.code.replace('.','_');
					    	code = val.code.replace('ICD10:','');
				    		output += "<tr><td style='white-space:nowrap;width:60px'><input class='wmtCheck' type='checkbox' name='check_"+id+"' code='"+val.code+"' desc='"+val.long_desc+"'/> <b>"+code+"</b> - </td><td style='padding-top:3px'>"+val.short_desc+"<br/></td>\n";
						});
					},
					async:   false
				});

				if (output == '') {
					output = '<table><tr><td><h4>NO MATCHES</h4></td></tr></table>';
				}
				else{
					output = '<table>' + output + '</table>';
				}
				
				$('#dc_Search').html(output);
				$("#dc_tabs").tabs( "option", "active", 0 );	
				f.searchIcd.value = '';
			}

			function addCodes() {
				var count = 0;
				$('#dc_tabs').tabs('option','active');
				$("#dc_tabs div[aria-hidden='false'] input:checked").each(function() {
					success = addCodeRow($(this).attr('code'), $(this).attr('desc'));
					$(this).attr('checked',false);
					if (success) count++;
				});
// PER RICK		if (count) alert("Requested items added to order.");
			}
			
			function addCodeRow(code,text) {
				$('#codeEmptyRow').remove();

				id = code.replace('.','_');
				if ($('#code_'+id).length) {
					alert("Code "+code+" has already been added.");
					return false;
				}

				if ($('#codeTable tr').length > 10) {
					alert("Maximum number of diagnosis codes exceeded.");
					return false;
				}
				
				var newRow = "<tr id='code_" +id + "'>";
				newRow += "<td><input type='button' class='wmtButton' value='remove' style='width:60px' onclick=\"removeCodeRow('code_"+id+"')\" /></td>\n";
				newRow += "<td class='wmtLabel'><input name='dx_code[]' class='wmtFullInput code' style='font-weight:bold' readonly value='";
				newRow += code;
				newRow += "'/></td><td class='wmtLabel'><input name='dx_text[]' class='wmtFullInput name' readonly value='";
				newRow += text;
				newRow += "'/></td></tr>\n";
				
				$('#codeTable').append(newRow);

				return true;
			}

			function removeCodeRow(id) {
				$('#'+id).remove();
				// there is always the header and the "empty" row
				if ($('#codeTable tr').length == 1) $('#codeTable').append('<tr id="CodeEmptyRow"><td colspan="3"><b>NO PROFILES / TESTS SELECTED</b></td></tr>');
			}

			// search for the provided test code
			function searchTest() {
				var output = '';
				var f = document.forms[0];
				var code = f.searchCode.value;
				if ( code == '' ) { 
					alert('You must enter a profile or lab test search code.');
					return;
				}
				
				// retrieve the diagnosis array
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/quest/QuestAjax.php",
					dataType: "json",
					data: {
						type: 'lab',
						code: code
					},
					success: function(data) {
				    	$.each(data, function(key, val) {
					    	id = val.code.replace('.','_');
					    	text = val.description;
					    	if (val.type != '') text += " [" + val.type + "]";
				    		output += "<tr><td style='white-space:nowrap'><nowrap><input class='wmtCheck' type='checkbox' name='check_"+id+"' code='"+val.code+"' desc='"+text+"' prof='"+val.profile+"' /> ";
				    		if (val.profile == 'Y') {
					    		output += "<span style='font-weight:bold;color:#c00;vertical-align:middle'>"+val.code+"</span>";
				    		}
				    		else { 	
					    		output += "<span style='font-weight:bold;vertical-align:middle'>"+val.code+"</span>";
				    		}
				    		output += " - </nowrap></td><td style='width:auto;text-align:left'>"+val.description+"<br/></td>\n";
				    	});
					},
					async:   false
				});

				if (output == '') {
					output = '<table><tr><td><h4>NO MATCHES</h4></td></tr></table>';
				}
				else{
					output = '<table>' + output + '</table>';
				}
				
				$('#oc_Search').html(output);
				$("#oc_tabs").tabs( "option", "active", 0 );	
				f.searchCode.value = '';
			}

			// search for the provided test code
			function fetchDetails(code) {
				var output = '';
				
				// retrieve the test details
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/quest/QuestAjax.php",
					dataType: "json",
					data: {
						type: 'details',
						code: code
					},
					success: function(data) {
						output = data; // process later
					},
					async:   false
				});

				return output;
			}

			function addTests() {
				var count = 0;
				var errors = 0;
				$('#oc_tabs').tabs('option','active');
				$("#oc_tabs div[aria-hidden='false'] input:checked").each(function() {
					success = addTestRow($(this).attr('code'),$(this).attr('desc'),$(this).attr('prof'));
					$(this).attr('checked',false);
					if (success) {
						count++;
					}
					else {
						errors++;
					}
				});
				if (count) {
					if (errors) {
						alert("Some items were not added to order.");
					}
					else {
// PER RICK				alert("Requested items added to order.");
					}
				}
			}
			
			function addTestRow(code,text,flag) {
				$('#orderEmptyRow').remove();

				id = code.replace('.','_');
				if ($('#test_'+id).length) {
					alert("Test "+code+" has already been added.");
					return false;
				}

				if ($('#order0_table tr').length > 35) {
					alert("Maximum number of profile/test requests exceeded.");
					return false;
				}

				var data = fetchDetails(code);
				var unit = data.unit; // json data from ajax
				var state = data.state; // json data from ajax
				var profile = data.profile; // json data fron ajax
				var aoe = data.aoe; // json data from ajax

				if (state != '') {
					current = $('#order0_type').val();
					if (current == '') {
						$('#order0_type').val(state);
					}
//                                        else if (current != state && state == 'PAP') {

					else if (current != state) {
						alert("SPECIMEN PATHOLOGY MISMATCH: \n"+current+" and "+state+"\n\nAnatomic pathology test ["+code+"] requires different processing\nand must be entered on a separate request.");
						return false;
					}
<?php if (! $GLOBALS['lab_quest_psc']) : ?>
					else if (current != state) {
						alert("SPECIMEN TRANSPORT MISMATCH: \n"+current+" and "+state+"\n\nTest ["+code+"] requires a different specimen transport\ntype and must be entered on a separate request.");
						return false;
					}
<?php endif; ?>
				}

				var success = true;
				$('.component').each(function() {
					if ($(this).attr('unit') == unit) {
						alert("Test "+code+" has already been added as profile component.");
						success = false;
					} 					
				});

				if (!success) return false;

				var newRow = "<tr id='test_" +id + "'>";
				newRow += "<td style='vertical-align:top'><input type='button' class='wmtButton' value='remove' style='width:60px' onclick=\"removeTestRow('test_"+id+"')\" /> ";
				newRow += "<input type='button' class='wmtButton' value='details' style='width:60px' onclick=\"testOverview('"+id+"')\" /></td>\n";
				newRow += "<td class='wmtLabel' style='vertical-align:top;padding-top:5px'><input name='test_code[]' class='wmtFullInput test' readonly value='"+code+"' ";
				if (flag == 'Y') { // profile test
					newRow += "style='font-weight:bold;color:#c00' /><input type='hidden' name='test_profile[]' value='1' />";
				}
				else {
					newRow += "style='font-weight:bold' /><input type='hidden' name='test_profile[]' value='0' />";
				} 
 				newRow += "</td><td colspan='2' class='wmtLabel' style='text-align:right;vertical-align:top;padding-top:5px'><input name='test_text[]' class='wmtFullInput component' readonly unit='"+unit+"' value='"+text+"'/>\n";
  				
				// add profile tests if necessary
				success = true;
				for (var key in profile) {
					var obj = profile[key];

					$('.component').each(function() {
						if ($(this).attr('unit') == obj.component) {
							alert("Component of test "+code+" has already been added.");
							success = false;
						} 					
					});
						
					if (obj.description)  newRow += "<input class='wmtFullInput component' style='margin-top:5px' readonly unit='"+obj.component+"' value='     -  "+obj.description+"'/>\n";
					
					// add component AOE questions if necessary
					var aoe2 = obj.aoe;
					for (var key2 in aoe2) {
						var obj2 = aoe2[key2];
					   
						var test_code = obj2.code;
						var test_unit = obj2.unit;
						var question = obj2.question.replace(':','');
						if (obj2.description) question = obj2.description.replace(':',''); // use longer if available
						var prompt = obj2.prompt;
						if (test_code) {
							newRow += '<input type="hidden" name="aoe'+id+'_label[]" value="'+question+'" />'+"\n";
//CRISWELL 20130621			newRow += "<input type='hidden' name='aoe"+id+"_label[]' value='"+question+"' />\n";
//Necessary to allow for single quote in label
							newRow += "<input type='hidden' name='aoe"+id+"_code[]' value='"+test_code+"' />\n";
					   		newRow += "<input type='hidden' name='aoe"+id+"_unit[]' value='"+test_unit+"' />\n";
					   		newRow += "<div style='margin-top:5px'>" + question + ": <input name='aoe"+id+"_text[]' title='" + test_code + ": " + prompt + "' class='wmtFullInput aoe' value='' style='width:300px' /></div>\n";
						}	
					}
				}

				if (!success) return false;
				
				// add order AOE questions if necessary
				for (var key in aoe) {
					var obj = aoe[key];
				   
					var test_code = obj.code;
					var question = obj.question.replace(':','');
					if (obj.description) question = obj.description.replace(':',''); // use longer if available
					var prompt = obj.prompt;
					if (test_code) {
						newRow += '<input type="hidden" name="aoe'+id+'_label[]" value="'+question+'" />'+"\n";
//CRISWELL 20130621		newRow += "<input type='hidden' name='aoe"+id+"_label[]' value='"+question+"' />\n';
//Necessary to allow for single quote in label
						newRow += "<input type='hidden' name='aoe"+id+"_code[]' value='"+test_code+"' />\n";
						newRow += "<div style='margin-top:5px'>" + question + ": <input name='aoe"+id+"_text[]' title='" + test_code + ": " + prompt + "' class='wmtFullInput aoe' value='' style='width:300px' /></div>\n";
					}	
				}

				newRow += "</td></tr>\n"; // finish up order row
				
				$('#order0_table').append(newRow);

				return true;
			}

			function removeTestRow(id) {
				$('#'+id).remove();
				// there is always the header and the "empty" row
				if ($('#order0_table tr').length == 1) {
					 $('#order0_table').append('<tr id="orderEmptyRow"><td colspan="3"><b>NO PROFILES / TESTS SELECTED</b></td></tr>');
					 $('#order0_type').val('');
				}
			}

			// display test overview pop up
			function testOverview(code) {
				$.fancybox.showActivity();
				
				// retrieve the overview details
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/quest/QuestAjax.php",
					dataType: "html",
					data: {
						type: 'overview',
						code: code
					},
					success: function(data) {
			            $.fancybox({
			                'content' 				: data,
							'overlayOpacity' 		: 0.6,
							'showCloseButton' 		: true,
							'width'					: '800px',
							'height' 				: '400px',
							'centerOnScroll' 		: false,
							'autoScale'				: false,
							'autoDimensions'		: false,
							'hideOnOverlayClick' 	: true,
							'scrolling'				: 'auto'
						});
										},
					async:   false
				});

				return;
			}

			
			// print labels
			function printLabels(item) {
				var f = document.forms[0];
				var fl = document.forms[item];
				var printer = fl.labeler.value;
				if ( printer == '' ) { 
					alert('Unable to determine default label printer.\nPlease select a label printer.');
					return;
				}

				var count = fl.count.value;
				var order = f.order0_number.value;
				var patient = "<?php echo $pat_data->lname; ?>, <?php echo $pat_data->fname; ?> <?php echo $pat_data->mname; ?>";
				var pid = "<?php echo $pat_data->pid  ?>";
				
				// retrieve the label
				$.ajax ({
					type: "POST",
					url: "<?php echo $GLOBALS['webroot'] ?>/library/quest/QuestAjax.php",
					dataType: "text",
					data: {
						type: 'label',
						printer: printer,
						count: count,
						order: order,
						patient: patient,
						pid: pid,
						siteid: '<?php echo $GLOBALS['lab_quest_siteid'] ?>'
					},
					success: function(data) {
						if (printer == 'file') {
							window.open(data,"_blank");
						}
						else {
							alert(data);
						}
					},
					async:   false
				});

			}

			// setup jquery processes
			$(document).ready(function(){
				$('#dc_tabs').tabs().addClass('ui-tabs-vertical ui-helper-clearfix');
				$('#oc_tabs').tabs().addClass('ui-tabs-vertical ui-helper-clearfix');

				$("#searchIcd").keyup(function(event){
				    if(event.keyCode == 13){
				        searchDiagnosis();
				    }
				});

				$("#searchCode").keyup(function(event){
				    if(event.keyCode == 13){
				        searchTest();
				    }
				});
				
<?php if ($completed) { // disable everything ?>
				$("#<?php echo $form_name; ?> :input").attr("disabled", true);
				$(".nolock").attr("disabled", false);
<?php } ?>
			});
				
			
		</script>
	</head>

	<body class="body_top">

		<!-- Required for the popup date selectors -->
		<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>
		
		<form method='post' action="<?php echo $save_url ?>" id='<?php echo $form_name; ?>' name='<?php echo $form_name; ?>' > 
			<input type='hidden' name='process' id='process' value='' />
			<input type='hidden' name='lab_quest_siteid' id='lab_quest_siteid' value='<?php echo $GLOBALS['lab_quest_siteid'] ?>' />
			<div class="wmtTitle">
<?php if ($viewmode) { ?>
				<input type=hidden name='mode' value='update' />
				<input type=hidden name='id' value='<?php echo $_GET["id"] ?>' />
				<span class=title><?php echo $form_title; ?> <?php echo ($form_data->status == 'p')? 'View Only': 'Update' ?></span>
<?php } else { ?>
				<input type='hidden' name='mode' value='new' />
				<span class='title'>New <?php echo $form_title; ?></span>
<?php } ?>
			</div>

<!-- BEGIN ORDER -->
			<!-- Client Information -->
			<div class="wmtMainContainer" style="width:99%">
				<div class="wmtCollapseBar" id="ReviewCollapseBar" onclick="togglePanel('ReviewBox','ReviewImageL','ReviewImageR','ReviewCollapseBar')">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">	
						<tr>
							<td>
								<img id="ReviewImageL" align="left" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
							<td class="wmtChapter" style="text-align: center">
								Patient Information</span>
							</td>
							<td style="text-align: right">
								<img id="ReviewImageR" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
						</tr>
					</table>
				</div>
				
				<div class="wmtCollapseBox" id="ReviewBox">
					<table width="100%"	border="0" cellspacing="0" cellpadding="0">
						<tr>
							<!-- Left Side -->
							<td style="width:50%" class="wmtInnerLeft">
								<table width="99%">
							        <tr>
										<td style="width:20%" class="wmtLabel">
											Patient First
											<input name="pat_first" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_first:$pat_data->fname; ?>">
											<input name="pat_ss" type="hidden" value="<?php echo $pat_data->ss; ?>">
										</td>
										<td style="width:10%" class="wmtLabel">
											Middle
											<input name="pat_middle" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_middle:$pat_data->mname; ?>">
										</td>
										<td class="wmtLabel">
											Last Name
											<input name="pat_last" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_last:$pat_data->lname; ?>">
										</td>
										<td style="width:20%" class="wmtLabel">
											Birth Date
											<input name="pat_DOB" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_DOB:$pat_data->birth_date; ?>">
										</td>
										<td style="width:5%" class="wmtLabel">
											Age
											<input name="pat_age" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_age:$pat_data->age; ?>">
										</td>
										<td style="width:15%" class="wmtLabel">
											Gender
											<input name="pat_sex" type"text" class="wmtFullInput" readonly value="<?php echo ListLook(($completed)?$form_data->pat_sex:$pat_data->sex, 'sex') ?>">
										</td>
									</tr>

									<tr>
										<td colspan="3" class="wmtLabel">Email Address<input name="pat_email" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_email:$pat_data->email; ?>"></td>
										<td class="wmtLabel">Mobile Phone<input name="pat_mobile" id="ex_phone_mobile" type="text" class="wmtFullInput" readonly onchange="phoneNumber(this)" value="<?php echo ($completed)?$form_data->pat_mobile:$pat_data->phone_cell; ?>"></td>
										<td colspan="2" class="wmtLabel">Home Phone<input name="pat_phone" type="text" class="wmtFullInput" readonly onchange="phoneNumber(this)" value="<?php echo ($completed)?$form_data->pat_phone:$pat_data->phone_home; ?>"></td>
									</tr>
									
									<tr>
										<td colspan="6" class="wmtLabel">
											Primary Address
											<input name="pat_street" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_street:$pat_data->street; ?>"></td>
									</tr>

									<tr>
										<td colspan="3" class="wmtLabel" style="width:50%">
											City
											<input name="pat_city" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_city:$pat_data->city; ?>">
										</td>
										<td class="wmtLabel">
											State
											<input type="text" class="wmtFullInput" readonly value="<?php echo ListLook(($completed)?$form_data->pat_state:$pat_data->state, 'state'); ?>">
											<input type="hidden" name="pat_state" value="<?php echo ($completed)?$form_data->pat_state:$pat_data->state ?>" />
										</td>
										<td colspan="2" class="wmtLabel">
											Postal Code
											<input name="pat_zip" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->pat_zip:$pat_data->postal_code; ?>">
										</td>
									</tr>
								</table>
							</td>
							
							<!-- Right Side -->
							<td style="width:50%" class="wmtInnerRight">
								<table width="99%" border="0" cellspacing="0" cellpadding="1">
									<tr>
										<td style="width:20%" class="wmtLabel">
											Insured First
											<input name="ins_first" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_first:$ins_list[0]->subscriber_fname; ?>">
										</td>
										<td style="width:10%" class="wmtLabel">
											Middle
											<input name="ins_middle" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_middle:$ins_list[0]->subscriber_mname; ?>">
										</td>
										<td class="wmtLabel">
											Last Name
											<input name="ins_last" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_last:$ins_list[0]->subscriber_lname; ?>">
										</td>
										<td style="width:20%" class="wmtLabel">
											Birth Date
											<input name="ins_DOB" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?($form_data->ins_DOB != '0000-00-00')?$form_data->ins_DOB:'':$ins_list[0]->subscriber_birth_date; ?>">
										</td>
										<td style="width:20%" class="wmtLabel">
											Relationship
											<input name="ins_relation" type="text" class="wmtFullInput" readonly value="<?php echo ListLook(($completed)?$form_data->ins_relation:$ins_list[0]->subscriber_relationship, 'sub_relation'); ?>">
											<input name="ins_ss" type="hidden" value="<?php echo ($completed)?$form_data->ins_ss:$ins_list[0]->subscriber_ss ?>" />
											<input name="ins_sex" type="hidden" value="<?php echo ($completed)?$form_data->ins_sex:$ins_list[0]->subscriber_sex ?>" />
										</td>
									</tr>
									<tr>
										<td colspan="3" class="wmtLabel">
											Primary Insurance
											<input name="ins_primary" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_primary:($ins_list[0]->company_name)?$ins_list[0]->company_name:'No Insurance'; ?>">
											<input name="ins_primary_id" type="hidden" value="<?php echo $ins_list[0]->id ?>"/>
											<input name="ins_primary_plan" type="hidden" value="<?php echo $ins_list[0]->plan_name ?>"/>
											<input name="ins_primary_type" type="hidden" value="<?php echo $ins_list[0]->plan_type ?>"/>
										</td>
										<td class="wmtLabel">Policy #<input name="ins_primary_policy" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_primary_policy:$ins_list[0]->policy_number; ?>"></td>
										<td class="wmtLabel">Group #<input name="ins_primary_group" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_primary_group:$ins_list[0]->group_number; ?>"></td>
									</tr>
									<tr>
										<td colspan="3" class="wmtLabel">
											Secondary Insurance
											<input name="ins_secondary" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_secondary:$ins_list[1]->company_name; ?>">
											<input name="ins_secondary_id" type="hidden" value="<?php echo $ins_list[1]->id ?>"/>
											<input name="ins_secondary_plan" type="hidden" value="<?php echo $ins_list[1]->plan_name ?>"/>
										</td>
										<td class="wmtLabel">Policy #<input name="ins_secondary_policy" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_secondary_policy:$ins_list[1]->policy_number; ?>"></td>
										<td class="wmtLabel">Group #<input name="ins_secondary_group" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->ins_secondary_group:$ins_list[1]->group_number; ?>"></td>
									</tr>
									<tr>
										<td style="width:20%" class="wmtLabel">
											Guarantor First
											<input name="guarantor_first" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->guarantor_first:($ins_list[0]->subscriber_lname)?$ins_list[0]->subscriber_fname:$pat_data->fname; ?>">
											<input name="guarantor_phone" type="hidden" value="<?php echo ($ins_list[0]->subscriber_phone)?$ins_list[0]->subscriber_phone:$pat_data->phone_home ?>" />
											<input name="guarantor_street" type="hidden" value="<?php echo ($ins_list[0]->subscriber_street)?$ins_list[0]->subscriber_street:$pat_data->street ?>" />
											<input name="guarantor_city" type="hidden" value="<?php echo ($ins_list[0]->subscriber_street)?$ins_list[0]->subscriber_city:$pat_data->city ?>" />
											<input name="guarantor_state" type="hidden" value="<?php echo ($ins_list[0]->subscriber_street)?$ins_list[0]->subscriber_state:$pat_data->state ?>" />
											<input name="guarantor_zip" type="hidden" value="<?php echo ($ins_list[0]->subscriber_street)?$ins_list[0]->subscriber_postal_code:$pat_data->postal_code ?>" />
										</td>
										<td style="width:10%" class="wmtLabel">
											Middle
											<input name="guarantor_middle" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->guarantor_middle:($ins_list[0]->subscriber_lname)?$ins_list[0]->subscriber_mname:$pat_data->mname; ?>">
										</td>
										<td style="width:20%" class="wmtLabel">
											Last Name
											<input name="guarantor_last" type"text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->guarantor_last:($ins_list[0]->subscriber_lname)?$ins_list[0]->subscriber_lname:$pat_data->lname; ?>">
										</td>
										<td class="wmtLabel">SS#<input name="guarantor_ss" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?$form_data->guarantor_ss:($ins_list[0]->subscriber_ss)?$ins_list[0]->subscriber_ss:$pat_data->ss; ?>"></td>
										<td class="wmtLabel">
											Relationship
											<input name="guarantor_relation" type="text" class="wmtFullInput" readonly value="<?php echo ($completed)?ListLook($form_data->guarantor_relation, 'sub_relation'):($ins_list[0]->subscriber_relationship)?ListLook($ins_list[0]->subscriber_relationship, 'sub_relation'):'Self'; ?>">
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<!-- End Information Review -->
			
			<!--  Start of Order Entry -->
			<div class="wmtMainContainer" style="width:99%">
				<div class="wmtCollapseBar" id="EntryCollapseBar" onclick="togglePanel('EntryBox','EntryImageL','EntryImageR','EntryCollapseBar')">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">	
						<tr>
							<td>
								<img id="EntryImageL" align="left" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
							<td class="wmtChapter" style="text-align: center">
								Order Entry
							</td>
							<td style="text-align: right">
								<img id="EntryImageR" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
						</tr>
					</table>
				</div>
				
				<div class="wmtCollapseBox" id="EntryBox">
					<table width="100%"	border="0" cellspacing="2" cellpadding="0">
						<tr>
							<!-- Left Side -->
							<td style="width:50%" class="wmtInnerLeft">
								<table class="wmtLabBox" >
							        <tr>
										<td class="wmtLabHeader">
											<div style="float:left;vertical-align:bottom;">
												DIAGNOSIS CODES
											</div>
										
											<div style="float:right">
												<input class="wmtInput" type="text" name="searchIcd" id="searchIcd" />
												<input class="wmtButton" type="button" value="search" onclick="searchDiagnosis()" />
											</div>
										</td>
									</tr>
									<tr>
										<td class="wmtLabBody">
											<div id="dc_tabs">
												<div class="wmtLabMenu" style="width:auto">
													<ul style="margin:0;padding:0">
<?php 
$title = 'Search';
echo "<li><a href='#dc_Search'>Search</a></li>\n";
foreach ($dlist as $data) {
	if ($data['title'] != $title) {
		$title = $data['title']; // new tab
		$link = strtolower(str_replace(' ', '_', $title));
		echo "<li><a href='#dc_".$link."'>".$title."</a></li>\n";
	}
}
?>
													</ul>
													<center><input type="button" style="margin-top:20px" onclick="addCodes()" value="add selected"/></center>
												</div>
												
<?php 
$title = 'Search';
echo "<div class='wmtQuick' id='dc_Search' style='display:none;margin-left:0'><table width='100%'><tr><td style='text-align:center;padding-top:30px'><h3>Select profile at left or<br/>search using search box at top.</h3></tr></td>\n";
foreach ($dlist as $data) {
	if ($data['title'] != $title) {
		if ($title) echo "</table></div>\n"; // end previous section
		$title = $data['title']; // new section
		$link = strtolower(str_replace(' ', '_', $title));
		echo "<div class='wmtQuick' id='dc_".$link."' style='display:none'><table>\n";
	}
	$text = ($data['notes']) ? $data['notes'] : $data['short_desc'];
	$id = str_replace('.', '_', $data['code']);
	echo "<tr><td style='white-space:nowrap'><nowrap><input class='wmtCheck' type='checkbox' id='check_".$id."' code='".$data['code']."' desc='".htmlspecialchars($text)."' > <b>".$data['code']."</b></input> - </nowrap></td><td style='padding-top:0'>".$text."</td></tr>\n";
}
if ($title) echo "</table></div>\n"; // end if at least one section
?>
										</td>
									</tr>
								</table>
							</td>
							
							<!-- Right Side -->
							<td style="width:50%" class="wmtInnerRight">
								<table class="wmtLabBox">
							        <tr>
										<td class="wmtLabHeader">
											<div style="float:left;vertical-align:bottom;">
												ORDER CODES
											</div>
										
											<div style="float:right">
												<input class="wmtInput" type="text" name="searchCode" id="searchCode" />
												<input class="wmtButton" type="button" value="search" onclick="searchTest()" />
											</div>
										</td>
									</tr>
									<tr>
										<td class="wmtLabBody">
											<div id="oc_tabs">
												<div class="wmtLabMenu">
													<ul style="margin:0;padding:0">
<?php 
$title = 'Search';
echo "<li><a href='#oc_Search'>Search</a></li>\n";
foreach ($olist as $data) {
	if ($data['title'] != $title) {
		$title = $data['title']; // new tab
		$link = strtolower(str_replace(' ', '_', $title));
		echo "<li><a href='#oc_".$link."'>".$title."</a></li>\n";
	}
}
?>
													</ul>
													<center><input type="button" style="margin-top:20px" onclick="addTests()" value="add selected"/></center>
												</div>
												
<?php 
$title = 'Search';
echo "<div class='wmtQuick' id='oc_Search' style='display:none'><table width='100%'><tr><td style='text-align:center;padding-top:30px'><h3>Select panel at left or<br/>search using search box at top.</h3>\n";
foreach ($olist as $data) {
	if ($data['title'] != $title) {
		if ($title) echo "</table></div>\n"; // end previous section
		$title = $data['title']; // new section
		$link = strtolower(str_replace(' ', '_', $title));
		echo "<div class='wmtQuick' id='oc_".$link."' style='display:none'><table>\n";
	}
	$text = ($data['notes']) ? $data['notes'] : $data['description'];
	$id = str_replace('.', '_', $data['code']);
	echo "<tr><td style='white-space:nowrap'><nowrap><input class='wmtCheck' type='checkbox' id='mark_".$id."' code='".$data['code']."' desc='".htmlspecialchars($text)."' > <b>".$data['code']."</b></input> - </nowrap></td><td style='padding-top:0'>".$text."</td></tr>\n";
}
if ($title) echo "</table></div>\n"; // end if at least one section
?>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
			</div>
			<!-- End Order Entry -->
								
			<!--  Start of Review Review -->
			<div class="wmtMainContainer" style="width:99%">
				<div class="wmtCollapseBar" id="OrderCollapseBar" onclick="togglePanel('OrderBox','OrderImageL','OrderImageR','OrderCollapseBar')">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">	
						<tr>
							<td>
								<img id="OrderImageL" align="left" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
							<td class="wmtChapter" style="text-align: center">
								Order Review
							</td>
							<td style="text-align: right">
								<img id="OrderImageR" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
						</tr>
					</table>
				</div>
				
				<div class="wmtCollapseBox" id="OrderBox">
					<table width="100%"	border="0" cellspacing="2" cellpadding="0">
						<tr>
							<td>
								<fieldset>
									<legend>Diagnosis Codes</legend>

									<table id="codeTable" width="100%" border="0" cellspacing="0" cellpadding="2">
										<tr>
											<th class="wmtHeader" style="width:60px">Action</th>
											<th class="wmtHeader" style="width:150px">Diagnosis</th>
											<th class="wmtHeader">Description</th>
										</tr>

<?php 
// load the existing diagnosis codes
$newRow = '';
for ($d = 0; $d < 10; $d++) {
	$codekey = "dx".$d."_code";
	$dx_code = $form_data->$codekey;
	$textkey = "dx".$d."_text";
	$dx_text = $form_data->$textkey;
	
	if (empty($dx_code)) continue;

	if (strpos($dx_code,":") !== false)	
		list($dx_type,$dx_code) = explode(":", $dx_code);

	if (!$dx_type) $dx_type = 'ICD9';
 
	$id = str_replace('.', '_', $dx_code);
	$dx_code = $dx_type.":".$dx_code;
	
	// add new row
	$newRow .= "<tr id='code_".$id."'>";
	$newRow .= "<td><input type='button' class='wmtButton' value='remove' style='width:60px' onclick=\"removeCodeRow('code_".$id."')\" /></td>\n";
	$newRow .= "<td class='wmtLabel'><input name='dx_code[]' class='wmtFullInput code' style='font-weight:bold' readonly value='".$dx_code."'/>\n";
	$newRow .= "</td><td class='wmtLabel'><input name='dx_text[]' class='wmtFullInput name' readonly value='".$dx_text."'/>\n";
	$newRow .= "</td></tr>\n";
}

// anything found
if ($newRow) {
	echo $newRow;
}
else { // create empty row
?>
										<tr id="codeEmptyRow">
											<td colspan="3">
												<b>NO DIAGNOSIS CODES SELECTED</b>
											</td>
										</tr>
<?php } ?>
									</table>
								</fieldset>
							
							</td>
						</tr>

<?php 
// create unique identifier for order number
if ($viewmode) {
	$ordnum = $form_data->order0_number;
}
else {
	$ordnum = generate_id();
}
?>
						<tr>
							<td>
								<fieldset>
									<legend>Order Requisition - <?php echo $ordnum ?></legend>
									<input type="hidden" name="order0_number" value="<?php echo $ordnum ?>" />

									<table id="sample0_table" border="0" cellspacing="0" cellpadding="2">
										<tr style="<?php if (!$GLOBALS['lab_quest_psc']) echo 'display:none'; ?>">
											<td style="padding-bottom:10px">
												<label class="wmtLabel" style="vertical-align:middle">Transport:</label>
											</td><td style="padding-bottom:10px">
												<input type="text" class="wmtInput" id="order0_type" name="order0_type" readonly style="width:220px" value="<?php echo $form_data->order0_type ?>" />
											</td>
										</tr>
										<tr style="<?php if (!$GLOBALS['lab_quest_psc']) echo 'display:none'; ?>">
											<td style='width:100px'>
												<label class="wmtLabel">Collection Date: </label>
											</td><td>
												<input class="wmtInput" type='text' size='10' name='order0_date' id='order0_date' 
													value='<?php echo $viewmode ? ($form_data->order0_datetime == 0)? '' : date('Y-m-d',strtotime($form_data->order0_datetime)) : date('Y-m-d'); ?>'
													title='<?php xl('yyyy-mm-dd Date sample taken','e'); ?>'
													onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' />
												<img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22'
													id='img_order0_date' border='0' alt='[?]' style='cursor:pointer;cursor:hand'
													title='<?php xl('Click here to choose a date','e'); ?>'>
											</td>
											<td style='text-align:right'>
												<label class="wmtLabel">Time: </label>
											</td><td>
												<input type="input" class="wmtInput" style="width:65px" name='order0_time' id='order0_time' 
												value='<?php echo $viewmode ? ($form_data->order0_datetime == 0)? '' : date('h:ia',strtotime($form_data->order0_datetime)) : date('h:ia'); ?>' />
											</td>
											<td style='padding-left:60px'>
												<input type="checkbox" name="order0_fasting" value="1" class="wmtCheck" <?php if ($form_data->order0_fasting) echo 'checked' ?>/> <label class="wmtLabel" style="vertical-align:middle">Patient Fasting</label>
											</td>
											<td style='text-align:right;width:130px'>
												<label class="wmtLabel">Duration (hours): </label>
											</td><td>
												<input type="input" name="order0_duration" class="wmtInput" style="width:65px" value='<?php echo $form_data->order0_duration; ?>'/>
											</td>
										</tr>
										<tr>
											<td>
												<label class="wmtLabel" >Scheduled Date: </label>
											</td><td>
												<input class="wmtInput" type='text' size='10' name='order0_pending_date' id='order0_pending_date' 
													value='<?php echo $viewmode ? ($form_data->order0_pending == 0)? '' : date('Y-m-d',strtotime($form_data->order0_pending)) : ''; ?>'
													title='<?php xl('yyyy-mm-dd Date sample scheduled','e'); ?>'
													onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' />
												<img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22'
													id='img_pending0_date' border='0' alt='[?]' style='cursor:pointer;cursor:hand'
													title='<?php xl('Click here to choose a date','e'); ?>'>
											</td>
											<td style='width:40px;text-align:right'>
												<label class="wmtLabel">Time: </label>
											</td><td>
												<input type="input" id="order0_pending_time" name="order0_pending_time" class="wmtInput" style="width:65px" 
												value='<?php echo $viewmode ? ($form_data->order0_pending == 0)? '' : date('h:ia',strtotime($form_data->order0_pending)) : ''; ?>' />
											</td>
											<td colspan="4" style="padding-left:60px">
											<?php if ($GLOBALS['lab_quest_psc']) { ?>
												<input type="checkbox" class="wmtCheck" id="order0_psc" name="order0_psc" value="1"	<?php if ($form_data->order0_psc || !$GLOBALS['lab_quest_psc']) echo "checked" ?> />
												 <label class="wmtLabel" style="vertical-align:middle">Specimen Not Collected [ PSC Hold Order ]</label>
											<?php } else { ?>
												<input type="hidden" id="order0_psc" name="order0_psc" value="1" />
											<?php } ?>
											</td>
										</tr>
									</table>
									<br/>
									<hr style="border-color:#eee"/>
									<br/>
									<table id="order0_table" width="100%">
										<tr>
											<th class="wmtHeader" style="width:125px">Actions</td>
											<th class="wmtHeader" style="width:100px">Profile / Test</td>
											<th class="wmtHeader">General Description</td>
											<th class="wmtHeader" style="width:300px">Order Entry Questions</td>
										</tr>
<?php 
// load the existing requisition codes
$newRow = '';
foreach ($test_list as $test) {
	if (!$test['test_code']) continue;
	$newRow .= "<tr id='test_".$test['test_code']."'>";
	$newRow .= "<td style='vertical-align:top'><input type='button' class='wmtButton' value='remove' style='width:60px' onclick=\"removeTestRow('test_".$test['test_code']."')\" /> ";
	$newRow .= "<input type='button' class='wmtButton' value='details' style='width:60px' onclick=\"testOverview('".$test['test_code']."');return false;\" /></td>\n";
	$newRow .= "<td class='wmtLabel' style='vertical-align:top;padding-top:5px'><input name='test_code[]' class='wmtFullInput test' readonly value='".$test['test_code']."' ";
	if ($test['test_profile'] == '1') { // profile test
		$newRow .= "style='font-weight:bold;color:#c00' /><input type='hidden' name='test_profile[]' value='1' />";
	}
	else {
		$newRow .= "style='font-weight:bold' /><input type='hidden' name='test_profile[]' value='0' />";
	}
	$newRow .= "</td><td colspan='2' class='wmtLabel' style='text-align:right;vertical-align:top;padding-top:5px'><input name='test_text[]' class='wmtFullInput' readonly value='".$test['test_text']."'/>\n";

	// add profile tests if necessary
	if ($test['test_profile'] == '1') {
		$query = "SELECT p.description, p.component_unit_cd AS component FROM cdc_order_codes oc ";
		$query .= "JOIN cdc_profiles p ON oc.test_cd = p.test_cd ";
		$query .= "WHERE oc.active_ind = 'A' AND oc.test_cd = '".$test['test_code']."' ";
		$query .= "ORDER BY p.component_unit_cd";
		$result = sqlStatement($query);

		$aoe_count = 0;
		while ($profile = sqlFetchArray($result)) {
			if ($profile['description']) $newRow .= "<input class='wmtFullInput' style='margin-top:5px' readonly value='     -  ".$profile['description']."'/>\n";
		
			// add component AOE questions if necessary
			$query = "SELECT analyte_cd, unit_cd, aoe_question_desc, result_filter FROM cdc_order_aoe ";
			$query .= "WHERE active_ind = 'A' AND unit_cd = '".$profile['component']."' ";
			$query .= "ORDER BY analyte_cd";
			$result2 = sqlStatement($query);
		
			while ($aoe2 = sqlFetchArray($result2)) {
				$question = str_replace(':','',$aoe2['aoe_question_desc']);
				if ($aoe2['analyte_cd']) {
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_label[]' value='".$question."' />\n";
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_code[]' value='".$aoe2['analyte_cd']."' />\n";
					$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_unit[]' value='".$aoe2['unit_cd']."' />\n";
					$newRow .= "<div style='margin-top:5px'>".$question.": <input name='aoe".$aoe2['code']."_text[]' title='".$aoe2['result_filter']."' class='wmtFullInput aoe' value='".$test["aoe{$aoe_count}_text"]."' style='width:300px' /></div>\n";
					$aoe_count++;
				}
			}
		}
	}
	else {
		// add AOE questions if necessary
		$query = "SELECT oc.test_cd AS code, analyte_cd, aoe_question_desc, result_filter, description FROM cdc_order_codes oc ";
		$query .= "JOIN cdc_order_aoe aoe ON oc.test_cd = aoe.test_cd ";
		$query .= "WHERE oc.active_ind = 'A' AND aoe.active_ind = 'A' AND oc.test_cd = '".$test['test_code']."' ";
		$query .= "ORDER BY analyte_cd";
		$result = sqlStatement($query);

		$aoe_count = 0;
		while ($aoe = sqlFetchArray($result)) {
			$question = str_replace(':','',$aoe['aoe_question_desc']);
			if ($aoe['code']) {
				$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_label[]' value='".$question."' />\n";
				$newRow .= "<input type='hidden' name='aoe".$aoe['code']."_code[]' value='".$aoe['analyte_cd']."' />\n";
				$newRow .= "<div style='margin-top:5px'>".$question.": <input name='aoe".$aoe['code']."_text[]' title='".$aoe['result_filter']."' class='wmtFullInput aoe' value='".$test["aoe{$aoe_count}_text"]."' style='width:300px' /></div>\n";
				$aoe_count++;
			}
		}
	}
	
	$newRow .= "</td></tr>\n"; // finish up order row

}

// anything found
if ($newRow) {
	echo $newRow;
}
else { // create empty row
?>
										
										<tr id="orderEmptyRow">
											<td colspan="3">
												<b>NO PROFILES / TESTS SELECTED</b>
											</td>
										</tr>
<?php } ?>
																			
									</table>
									
									<table style="width:100%;margin-top:15px">
										<tr>
											<td>
												<label class="wmtLabel">Lab Notes / Comments:  <small style='font-weight:normal;padding-left:20px'>[ Sent to lab but not printed on requisition ]</small></label>
												<textarea id="order0_notes" name="order0_notes" rows="2" class="wmtFullInput"><?php echo htmlspecialchars($form_data->order0_notes) ?></textarea>	
											</td>
										</tr>
									</table>
								</fieldset>
							
							</td>
						</tr>
						
						
					</table>
				</div>
			</div>
			<!-- End Review Review -->
			
			<!--  Start of Order Submission -->
			<div class="wmtMainContainer" style="width:99%">
				<div class="wmtCollapseBar" id="InfoCollapseBar" onclick="togglePanel('InfoBox','InfoImageL','InfoImageR','InfoCollapseBar')">
					<table width="100%" border="0" cellspacing="0" cellpadding="0">
						<tr>
							<td style="text-align:left">
								<img id="InfoImageL" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
							<td class="wmtChapter" style="text-align:center">
								Additional Information
							</td>
							<td style="text-align:right">
								<img id="InfoImageR" src="<?php echo $webroot;?>/library/wmt/fill-090.png" border="0" alt="Show/Hide" title="Show/Hide" />
							</td>
						</tr>
					</table>
				</div>
				
				<div class="wmtCollapseBox" id="InfoBox">
					<table width="100%"	border="0" cellspacing="2" cellpadding="0">
						<tr>
							<td style="width:50%">
								<table style="width:100%">
									<tr>
										<td class="wmtLabel" nowrap style="text-align:right">Order Date: </td>
										<td nowrap>
											<input class="wmtInput" type='text' size='10' name='request_date' id='request_date' 
												value='<?php echo $viewmode ? date('Y-m-d',strtotime($form_data->request_datetime)) : date('Y-m-d'); ?>'
												title='<?php xl('yyyy-mm-dd Date of order','e'); ?>'
												onkeyup='datekeyup(this,mypcc)' onblur='dateblur(this,mypcc)' />
											<img src='../../pic/show_calendar.gif' align='absbottom' width='24' height='22'
												id='img_request_date' border='0' alt='[?]' style='cursor:pointer;cursor:hand'
												title='<?php xl('Click here to choose a date','e'); ?>'>
										</td>
										<td class="wmtLabel" nowrap style="text-align:right">Physician: </td>
										<td>
											<select class="wmtInput" name='request_provider' id='request_provider' style="min-width:200px">
												<option value='_blank'>-- select --</option>
<?php 
	$rlist= sqlStatement("SELECT * FROM users WHERE authorized=1 AND active=1 AND npi != '' ORDER BY lname");
	while ($rrow= sqlFetchArray($rlist)) {
    	echo "<option value='" . $rrow['id'] . "'";
		if ($form_data->request_provider == $rrow['id']) echo " selected";
		echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
    	echo "</option>";
  	}
?>
											</select>
										</td>
									</tr>
									
									<tr>
										<td class="wmtLabel" nowrap style="text-align:right">Process Date: </td>
										<td nowrap>
											<input class="wmtInput" readonly style="width:100px" value="<?php echo ($form_data->request_processed > 0)?date('Y-m-d H:i:s',strtotime($form_data->request_processed)):''?>" />
										</td>
										<td class="wmtLabel" nowrap style="text-align:right">Entered By: </td>
										<td nowrap>
											<input class="wmtInput" readonly value="<?php echo ($form_data->user)?UserLook($form_data->user):UserIdLook($_SESSION['authId'] )?>" />
										</td>
									</tr>

									<tr>
										<td>&nbsp;</td>
										<td>&nbsp;</td>
										<td class="wmtLabel" nowrap style="text-align:right">Special: </td>
										<td nowrap>
											<select class="wmtInput" name="request_handling" id="request_handling">
											<?php ListSel($form_data->request_handling, 'Quest_Special_Handling') ?>
											</select>
										</td>
									</tr>
								</table>
							</td>
							
							<td>
								<table style="width:100%">
									<tr>
										<td class="wmtLabel" colspan="3">
											Clinic Notes:  <small style='font-weight:normal;padding-left:20px'>[ Not sent to lab or printed on requisition ]</small>
											<textarea name="request_notes" id="request_notes" class="wmtFullInput" rows="4"><?php echo htmlspecialchars($form_data->request_notes) ?></textarea>
										</td>
									</tr>
								</table>
							</td>
						</tr>
					</table>
				</div>
			</div><!-- End of Problem -->
			
<!-- END ENCOUNTER -->

			</br>

			<!-- Start of Buttons -->
			<table width="99%" border="0">
<?php if ($viewmode && $form_data->status != 'i') { ?>
				<tr>
					<td class="wmtLabel" colspan="4" style="padding-bottom:10px;padding-left:8px">
						Label Printer: 
						<select class="nolock" id="labeler" name="labeler" style="margin-right:10px">
							<?php getLabelers($_SERVER['REMOTE_ADDR'])?>
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

						<input class="nolock" type="button" tabindex="-1" onclick="printLabels(0)" value="Print Labels" />

					</td>
				</tr>
<?php } ?>
				<tr>
<?php if(!$viewmode || $form_data->status == 'i') { ?>
				<td class="wmtLabel" style="vertical-align:top;float:left">
					<a class="css_button" tabindex="-1" href="javascript:saveClicked()"><span>Save Work</span></a>
				</td>
				
				<td class="wmtLabel" style="vertical-align:top;float:left">
					<a class="css_button" tabindex="-1" href="javascript:submitClicked()"><span>Submit Order</span></a>
				</td>
				
<?php } if($viewmode) { ?>
				<td class="wmtLabel">
					<a class="css_button" tabindex="-1" href="javascript:openPrint()"><span>Printable Form</span></a>
				</td>
<?php } ?>
<?php if ($form_data->order0_abn_id) { ?>
					<td class="wmtLabel">
						<a class="css_button" tabindex="-1" href="<?php echo $document_url . $form_data->order0_abn_id ?>"><span>ABN Documents</span></a>
					</td>
<?php } ?>
<?php if ($form_data->order0_req_id) { ?>
					<td class="wmtLabel">
						<a class="css_button" tabindex="-1" href="<?php echo $document_url . $form_data->order0_req_id ?>"><span>Lab Document</span></a>
					</td>
<?php } ?>
<td class="wmtLabel" style="vertical-align:top;float:right">
<?php if(!$viewmode) { ?>
					<a class="css_button" tabindex="-1" href="javascript:doClose()"><span>Don't Save</span></a>
<?php } else { ?>
					<a class="css_button" tabindex="-1" href="javascript:doClose()"><span>Cancel</span></a>
<?php } ?>
					</td>
				</tr>
			</table>
			<!-- End of Buttons -->
			
			<input type="hidden" name="status" value="<?php echo ($form_data->status)?$form_data->status:'i' ?>" />
			<input type="hidden" name="priority" value="<?php echo ($form_data->priority)?$form_data->priority:'n' ?>" />
		</form>
	</body>

	<script language="javascript">
		/* required for popup calendar */
		Calendar.setup({inputField:"request_date", ifFormat:"%Y-%m-%d", button:"img_request_date"});
		Calendar.setup({inputField:"order0_date", ifFormat:"%Y-%m-%d", button:"img_order0_date"});
		Calendar.setup({inputField:"order0_pending_date", ifFormat:"%Y-%m-%d", button:"img_pending0_date"});
	</script>

</html>
