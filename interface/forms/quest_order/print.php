<?php
/** **************************************************************************
 *	QUEST_ORDER/PRINT.PHP
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
 *  @uses quest_order/report.php
 * 
 *************************************************************************** */
require_once("../../globals.php");
require_once("{$GLOBALS['incdir']}/forms/quest_order/report.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.class.php");
require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");

// grab inportant stuff
$pid = $_SESSION['pid'];
$encounter = $_SESSION['encounter'];
$id = $_REQUEST['id'];

// Retrieve content
$enc_data = wmtEncounter::getEncounter($encounter);
$order_data = new wmtForm('quest_order',$id);

?>
<!DOCTYPE HTML>
<html>
	<head>
		<?php html_header_show();?>
		<title>Quest Lab Order for <?php echo $order_data->pat_last; ?>", " <?php echo $order_data->pat_first; ?><?php echo ($order_data->pat_middle)?" ".$order_data->pat_middle:''; ?> on <?php echo $order_data->order_date; ?></title>
		<link rel="stylesheet" href="<?php echo $css_header;?>" type="text/css">
		<link rel="stylesheet" type="text/css" href="<?php echo $GLOBALS['webroot'] ?>/interface/forms/quest_order/style_wmt.css" media="screen" />
	</head>

	<body class="wmtPrint" style="width:650pt">
	    <h1><?php echo $enc_data->facility ?></h1>
	    <h2>Quest Order</h2>

		<table class="wmtHeader">
			<tr>
 				<td class="wmtHeaderLabel" style="width: 10%; text-align: left">Date:<input type="text" class="wmtHeaderOutput" readonly value="<?php echo date('Y-m-d'); ?>"></td>
				<td class="wmtHeaderLabel" style="width: auto; text-align: center">Patient Name:<input type="text" class="wmtHeaderOutput" style="width: 70%" readonly value="<?php echo $order_data->pat_first.' '.$order_data->pat_middle.' '.$order_data->pat_last; ?>"></td>
				<td class="wmtHeaderLabel" style="width: 10%; text-align: right">Patient ID:<input type="text" class="wmtHeaderOutput" readonly value="<?php echo $order_data->pid; ?>"></td>
			</tr>
		</table>
<?php /* 
		<div class="wmtSection">
			<div class="wmtSectionTitle">
				Patient Information
		  	</div>
		  	<div class="wmtSectionBody">
				<table>
					<tr>
						<!-- Left side -->
						<td style="width:250pt">
							<table style="border-right: solid 1px black; width:100%">
								<tr>
									<td colspan="2">
										<table width="100%">
											<tr>
												<td class="wmtTitle" style="width:70pt">Birth Date</td>
												<td class="wmtTitle" style="width:40pt">Age</td>
												<td class="wmtTitle" style="width:50pt">Sex</td>
											</tr>
											<tr>
												<td class="wmtOutput"><?php echo $order_data->pat_DOB;?></td>
												<td class="wmtOutput"><?php echo $order_data->pat_age;?>&nbsp;</td>
												<td class="wmtOutput"><?php echo ListLook($order_data->pat_sex, 'sex'); ?></td>
											</tr>
										</table>
									</td>
								</tr>
								<tr>
									<td class="wmtTitle">Occupation</td>
									<td class="wmtTitle">Education</td>
								</tr>
								<tr>
									<td class="wmtOutput"><?php echo $order_data->occupation;?></td>
									<td class="wmtOutput"><?php echo $order_data->education;?>&nbsp;</td>
								</tr>
								<tr>
									<td class="wmtTitle">Language</td>
									<td class="wmtTitle">Ethnicity</td>
								</tr>
								<tr>
									<td class="wmtOutput"><?php echo ListLook($order_data->language, 'language'); ?>&nbsp;</td>
									<td class="wmtOutput"><?php echo ListLook($order_data->ethnicity, 'ethnicity'); ?></td>
								</tr>
								<tr>
									<td class="wmtTitle">Emergency Contact</td>
									<td class="wmtTitle">Emergency Phone</td>
								</tr>
								<tr>
									<td class="wmtOutput"><?php echo $order_data->contact_name;?></td>
									<td class="wmtOutput"><?php echo $order_data->phone_contact;?>&nbsp;</td>
								</tr>
					      	</table>
						</td>

						<!-- Right side -->
						<td style="width: 50%;padding-left:5px;vertical-align:top">
							<table>
								<tr>
									<td colspan="2" class="wmtTitle">Address</td>
								</tr>
								<tr>
									<td colspan="2" class="wmtOutput">
										<?php echo $order_data->street;?>&nbsp;<br/>
										<?php echo $order_data->city?>&nbsp;&nbsp;<?php echo ListLook($order_data->state,'state')?>&nbsp;&nbsp;<?php echo $order_data->postal_code?>
									</td>
								</tr>
								<tr>
									<td class="wmtTitle" style="width: 120pt">Home Phone</td>
									<td class="wmtTitle">Work Phone</td>
								</tr>
								<tr>
									<td class="wmtOutput"><?php echo $order_data->phone_home;?>&nbsp;</td>
									<td class="wmtOutput"><?php echo $order_data->phone_biz;?>&nbsp;</td>
								</tr>
								<tr>
									<td class="wmtTitle">Insurance Carrier</td>
									<td class="wmtTitle">Policy #</td>
								</tr>
								<tr>
									<td class="wmtOutput"><?php echo $ins_data->plan_name;?>&nbsp;</td>
									<td class="wmtOutput"><?php echo $ins_data->policy_number;?>&nbsp;</td>
								</tr>
							</table>
						</td>
					</tr>
				</table>
			</div>				
		</div>
*/ ?>
		<?php quest_order_report($pid, $encounter, '*', $id); ?>
	</body>
</html>