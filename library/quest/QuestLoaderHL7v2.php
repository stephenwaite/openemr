<?php
/** **************************************************************************
 *	QuestLoaderHL7v2.PHP
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

class ParseException extends Exception {
}

class LoaderException extends Exception {
}

class Loader_HL7v2 {

	var $field_separator;
	var $file;
	var $debug;
	var $file_type;

	var $MSH;
	var $CDC;

	function Loader_HL7v2( $file, $debug ) {
		if (!file_exists($file)) 
			throw new LoaderException("The file (".$file.") can not be found.");
		
		$this->file = $file;
		$this->debug = $debug;		
		$this->field_separator = '|'; // default
	}
	
	function load($clean = FALSE) {
		$count = 0;
		$handle = @fopen($this->file, 'r');

		if (! $handle) {
			throw new LoaderException("The file (".$file.") could not be openned.");
		}
		else {
			// get header record
			if (($segment = fgets($handle)) !== false) {
				if ('MSH' == substr($segment, 0, 3)) {
					call_user_func_array(
						array(&$this, '_MSH'),
						array($segment)
					);
				}
			}
			else {
				throw new LoaderException("The file (".$file.") could not be read.");
			}
			
			switch ($this->file_type) {
				case 'MFN^OC':
					$code = "OC";
					$table = "cdc_order_codes";
					break;
			
				case 'MFN^OP':
					$code = "OP";
					$table = "cdc_profiles";
					break;
			
//				case 'MFN^BT':
//					$code = "BT";
//					$table = "cdc_bill_to";
//					break;
			
				case 'MFN^OA':
					$code = "OA";
					$table = "cdc_order_aoe";
					break;
			
//				case 'MFN^CI':
//					$code = "CI";
//					$table = "cdc_icd_payable";
//					break;
			
//				case 'MFN^OT':
//					$code = "OT";
//					$table = "cdc_orderable_cpt";
//					break;
			
				case 'MFN^DC':
					$code = "DC";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DM':
					$code = "DM";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DV':
					$code = "DV";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DP':
					$code = "DP";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DH':
					$code = "DH";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DL':
					$code = "DL";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DT':
					$code = "DT";
					$table = "cdc_dos_info";
					break;
			
				case 'MFN^DS':
					$code = "DS";
					$table = "cdc_dos_info";
					break;
					
				case 'MFN^DX':
					$code = "DX";
					$table = "cdc_dos_info";
					break;
					
				default:
					throw new LoaderException("Unrecognized table code (". $this->file_type .").");
			}						

			// prepare table
			$records = 0;
			$total = count(sqlListFields($table));
			if ($clean) sqlStatement("TRUNCATE TABLE $table");
			
			// loop through all file records
			while (($segment = fgets($handle)) !== false) {
				$count = 0;
				$values = "";
				$elements = $this->__parse_composite($segment);
				
				// all dos records go in one table with type code
				if ($table == "cdc_dos_info") {
					$count++;
					$values = "'$code'";
				}
				
				if (count($elements) > 0) { // skips blank records
					foreach ($elements as $field) {
						$count++;
							
						// fix dates
						if ($code == 'OC' && ($count == 6 || $count == 11)) $field = date('Y-m-d H:i:s', strtotime($field));
						if ($code == 'OA' && ($count == 9 || $count == 15)) $field = date('Y-m-d H:i:s', strtotime($field));
						if ($code == 'OT' && $count == 3) $field = date('Y-m-d H:i:s', strtotime($field));
						
						// string together values
						if ($values) $values .= ", ";
						$values .= "'".mysql_real_escape_string($field)."'";
					}
			
					// process record
					if ($total == $count) {
						$query = "INSERT INTO $table VALUES ($values)";
						sqlInsert($query);
						$records++;
					}

					// Debug
					if ($this->debug) {
						print "<b>New record</b> ($values)\n";
					}
				}
			} // record loop
			
			fclose($handle);
			
			return $records;
		}
	}

	//----- All handlers go below here

	function _MSH($segment) {
		// Get separator
		$this->field_separator = substr($segment, 3, 1);
		
		// Decompose segment
		$composites = $this->__parse_segment($segment);
		if ($this->debug) {
			print "<b>MSH segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".$v."\n";
			}
		}
		
		// Assign values
		list (
			$__garbage, // Skip index [0], it's the separator
			$this->MSH['encoding_characters'],
			$this->MSH['sending_application'],
			$this->MSH['sending_facility'] ,
			$this->MSH['receiving_application'],
			$this->MSH['receiving_facility'],
			$this->MSH['message_datetime'],
			$this->MSH['message_type'],
			$this->MSH['message_control_id'],
			$this->MSH['processing_id'],
			$this->MSH['version_id']
		) = $composites;
		
		$this->file_type = trim($this->MSH['message_control_id']);

	} // end method _MSH
	
	//----- Truly internal functions

	function __default_segment_parser ($segment) {
		$composites = $this->__parse_segment($segment);

		// Try to parse composites
		foreach ($composites as $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		// Debug
		if ($this->debug) {
			print "<b>New segment</b>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".$v."\n";
			}
		}

		$pos = 0;
		// Find out where we are
		if (is_array($this->CDC)) {
			$pos = count($this->CDC);
		}
		
		return $composites[0];

	} // end method __default_segment_parser

	function __parse_composite($composite) {
		return explode('^', $composite);
	} // end method __parse_composite

	function __parse_segment($segment) {
		return explode($this->field_separator, $segment);
	} // end method __parse_segment
	
} // end class Loader_HL7v2

?>
