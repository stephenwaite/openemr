<?php
/** **************************************************************************
 *	QuestAjax.PHP
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
 *  @subpackage library
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */

// SANITIZE ALL ESCAPES
$sanitize_all_escapes = true;

// STOP FAKE REGISTER GLOBALS
$fake_register_globals = false;

require_once("../../interface/globals.php");
require_once("{$GLOBALS['srcdir']}/classes/Document.class.php");

// Get request type
$type = $_REQUEST['type'];

if ($type == 'icd9') {
	$code = strtoupper($_REQUEST['code']);

	$query = "SELECT formatted_dx_code AS code, short_desc, long_desc FROM icd9_dx_code ";
	$query .= "WHERE formatted_dx_code LIKE '".$code."%' ";
	if (!is_numeric($code)) $query .= "OR short_desc LIKE '%".$code."%' ";
	$query .= "ORDER BY dx_code";
	$result = sqlStatement($query);

	$count = 1;
	$data = array();
	while ($record = sqlFetchArray($result)) {
		$data[$count++] = array('code'=>$record['code'],'short_desc'=>$record['short_desc'],'long_desc'=>$record['long_desc']);		
	}
	
	echo json_encode($data);
}

if ($type == 'lab') {
	$code = strtoupper($_REQUEST['code']);

	$query = "SELECT DISTINCT test_cd AS code, specimen_type AS type, description, profile_ind, specimen_type FROM cdc_order_codes ";
	$query .= "WHERE active_ind = 'A' AND (test_cd LIKE '".$code."%' ";
	if (!is_numeric($code)) $query .= "OR description LIKE '%".$code."%' ";
	$query .= ") ORDER BY test_cd";
	$result = sqlStatement($query);

	$count = 1;
	$data = array();
	while ($record = sqlFetchArray($result)) {
		$data[$count++] = array('code'=>$record['code'],'type'=>$record['type'],'description'=>$record['description'],'specimen'=>$record['specimen_type'],'profile'=>$record['profile_ind']);
	}

	echo json_encode($data);
}

if ($type == 'details') {
	$code = strtoupper($_REQUEST['code']);

	$query = "SELECT oc.test_cd AS code, oc.state, oc.pap_ind, oc.unit_cd FROM cdc_order_codes oc ";
	$query .= "WHERE oc.active_ind = 'A' AND oc.test_cd = '".$code."' ";
	$result = sqlStatement($query);

	$state = null;
	$pap = null;
	$unit = null;
	if ($record = sqlFetchArray($result)) {
		$state = $record['state'];
		$pap = $record['pap_ind'];
		$unit = $record['unit_cd'];
	}
	if ($state == 'FZ' || $state == 'F') $state = 'FROZEN';
	if ($state == 'A' || $state == 'R' || $state == 'RT' || $state == 'RF' || $state == 'G') $state = 'ROOM TEMP/REFRIGERATED';
	if ($pap == 'P' || $state == 'S') $state = 'PAP';
	if ($state == 'H') $state = 'HANDWRITTEN ONLY';
	
/*
• A = Ambient/room temperature
• F= Frozen
• FZ=Frozen
• G = Groupable or refrigerated
• H = Handwritten requisition only
• M = Multiple specimen transport types possible
• R = Room temperature
• RF = Refrigerated
• RT = Room temperature
• S = Split requisition (anatomic pathology items)
 */
	
	$query = "SELECT oc.test_cd AS code, oc.state, oc.pap_ind, p.component_unit_cd AS component, p.description FROM cdc_order_codes oc ";
	$query .= "JOIN cdc_profiles p ON oc.test_cd = p.test_cd ";
	$query .= "WHERE oc.active_ind = 'A' AND oc.test_cd = '".$code."' ";
	$query .= "ORDER BY p.component_unit_cd";
	$result = sqlStatement($query);

	$profile = array();
	$aoe = array();
	while ($record = sqlFetchArray($result)) {
		$query = "SELECT analyte_cd, unit_cd, aoe_question_desc, result_filter FROM cdc_order_aoe ";
		$query .= "WHERE active_ind = 'A' AND unit_cd = '".$record['component']."' ";
		$query .= "ORDER BY analyte_cd";
		$result2 = sqlStatement($query);
		
		$aoe2 = array();
		while ($record2 = sqlFetchArray($result2)) {
			$aoe2[] = array('code'=>$record2['analyte_cd'],'unit'=>$record2['unit_cd'],'question'=>$record2['aoe_question_desc'],'prompt'=>$record2['result_filter']);
		}

		$profile[] = array('code'=>$record['code'],'component'=>$record['component'],'description'=>$record['description'],'aoe'=>$aoe2);
	}

	if (! count($profile)) { // not profile
		$query = "SELECT oc.test_cd AS code, analyte_cd, aoe_question_desc, result_filter, description FROM cdc_order_codes oc ";
		$query .= "JOIN cdc_order_aoe aoe ON oc.test_cd = aoe.test_cd ";
		$query .= "WHERE oc.active_ind = 'A' AND aoe.active_ind = 'A' AND oc.test_cd = '".$code."' ";
		$query .= "ORDER BY aoe.analyte_cd";
		$result = sqlStatement($query);

		while ($record = sqlFetchArray($result)) {
			$aoe[] = array('code'=>$record['analyte_cd'],'question'=>$record['aoe_question_desc'],'prompt'=>$record['result_filter']);
		}
	}

	$data = array('unit'=>$unit,'state'=>$state,'profile'=>$profile,'aoe'=>$aoe);
	echo json_encode($data);
}

if ($type == 'overview') {
	$code = strtoupper($_REQUEST['code']);

	$dos = array();
	
	$query = "SELECT * FROM cdc_dos_info ";
	$query .= "WHERE test_cd = '".$code."' ";
	$query .= "ORDER BY record_type, sequence_no ";
	$result = sqlStatement($query);

	$data = array();
	while ($record = sqlFetchArray($result)) {
		$data[$record['record_type']][] = $record['comment_text'];
	}

	echo "<div style='width:450px;text-align:center;padding:10px;font-weight:bold;font-size:18px;background-color:orange;color:white'>DIRECTORY OF SERVICE INFORMATION</div>\n";
	echo "<div style='overflow-y:auto;overflow-x:hidden;height:350px;width:850p;margin-top:10px'>\n";
	
	$dp = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DP'] as $comment) {
		$dp = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($dp) {
		echo "<h4 style='margin-bottom:0'>PREFERRED COLLECTION METHOD</h4>";
		echo $output;
		echo "</div>";
	}

	$dv = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DV'] as $comment) {
		$dv = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($dv) {
		echo "<h4 style='margin-bottom:0'>MINIMUM VOLUME REQUIREMENT</h4>";
		echo $output;
		echo "</div>";
	}

	$dt = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DT'] as $comment) {
		$dt = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($dt) {
		echo "<h4 style='margin-bottom:0'>TRANSPORT CONTAINER</h4>";
		echo $output;
		echo "</div>";
	}

	$dx = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DX'] as $comment) {
		$dx = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($dx) {
		echo "<h4 style='margin-bottom:0'>TRANSPORT TEMPERATURE</h4>";
		echo $output;
		echo "</div>";
	}

	$ds = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DS'] as $comment) {
		$ds = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($ds) {
		echo "<h4 style='margin-bottom:0'>SPECIMEN STORAGE</h4>";
		echo $output;
		echo "</div>";
	}

	$dm = false;
	$output = "<div class='wmtOutput'>";
	foreach ($data['DM'] as $comment) {
		$dm = true;
		if (preg_match('/^\(/',$comment)) {
			$comment = preg_replace('/\([^\)]*\)/', '', $comment);
			$comment = "<br/><b>".$comment."</b>";
		}
		$output .= $comment."<br/>";
	}
	if ($dm) {
		echo "<h4 style='margin-bottom:0'>METHODOLOGY</h4>";
		echo $output;
		echo "</div>";
	}

	//echo "<tr><td>".$record['record_type']."</td><td>".$record['test_cd']."</td><td>".$record['comment_text']."</td></tr>\n";
	echo "<br/><br/></div>";
}

if ($type == 'label') {
	require_once("{$GLOBALS['srcdir']}/wmt/wmt.include.php");

	$address = $_REQUEST['printer'];
	$printer = ($address == 'file')? 'file' : ListLook($address, 'Quest_Label_Printers');
	$order = $_REQUEST['order'];
	$patient = strtoupper($_REQUEST['patient']);
	$client = $_REQUEST['siteid'];
	$pid = $_REQUEST['pid'];
	
	$count = 1;
	if ($_REQUEST['count']) $count = $_REQUEST['count'];
	
	require_once("{$GLOBALS['srcdir']}/tcpdf/config/lang/eng.php");
	require_once("{$GLOBALS['srcdir']}/tcpdf/tcpdf.php");
	
	// create new PDF document
	$pdf = new TCPDF('L', 'pt', array(54,144), true, 'UTF-8', false);
	
	// remove default header/footer
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	
	//set margins
	$pdf->SetMargins(15,5,20);
	$pdf->SetAutoPageBreak(FALSE, 35);
	
	//set some language-dependent strings
	$pdf->setLanguageArray($l);
	
	// define barcode style
	$style = array(
		'position' => '',
		'align' => 'L',
		'stretch' => true,
		'fitwidth' => false,
		'cellfitalign' => '',
		'border' => false,
		'hpadding' => 4,
		'vpadding' => 2,
		'fgcolor' => array(0,0,0),
		'bgcolor' => false, //array(255,255,255),
		'text' => false,
		'font' => 'helvetica',
		'fontsize' => 8,
		'stretchtext' => 4
	);
	
	// ---------------------------------------------------------
	
	do {
		$pdf->AddPage();
	
		$pdf->SetFont('times', '', 7);
		$pdf->Cell(0,5,'Client #: '.$client,0,1);
		$pdf->Cell(0,5,'Order #: '.$order,0,1);
	
		$pdf->SetFont('times', 'B', 8);
		$pdf->Cell(0,0,$patient,0,1,'','','',1);
	
		$pdf->write1DBarcode($client.'-'.$order, 'C39', '', '', 110, 25, '', $style, 'N');
		
		$count--;
		
	} while ($count > 0);

	// ---------------------------------------------------------
	if ($printer == 'file') {
		$repository = $GLOBALS['oer_config']['documents']['repository'];
		$label_file = $repository . preg_replace("/[^A-Za-z0-9]/","_",$pid) . "/" . $order . "_LABEL.pdf";

		$pdf->Output($label_file, 'F'); // force display download
		
		// register the new document
		$d = new Document();
		$d->name = $order."_LABEL.pdf";
		$d->storagemethod = 0; // only hard disk sorage supported
		$d->url = "file://" .$label_file;
		$d->mimetype = "application/pdf";
		$d->size = filesize($label_file);
		$d->owner = 'quest';
		$d->hash = sha1_file( $label_file );
		$d->type = $d->type_array['file_url'];
		$d->set_foreign_id($pid);
		$d->persist();
		$d->populate();
		
		echo $GLOBALS['web_root'].'/controller.php?document&retrieve&patient_id='.$pid.'&document_id='.$d->get_id();
	}
	else {
		$label = $pdf->Output('label.pdf','S'); // return as variable
		$CMDLINE = "lpr -P $printer ";
		$pipe = popen("$CMDLINE" , 'w' );
		if (!$pipe) {
			echo "Label printing failed...";
		}
		else {
			fputs($pipe, $label);
			pclose($pipe);
			echo "Labels printing at $printer ...";
		}
	}
}


?>