<?php
/** **************************************************************************
 *	QuestModelHL7v2.PHP
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

class Request_HL7v2 {
	public $id; // also used for MSH.10
	public $pid; // PID.02
	public $name; // PID.05
	public $dob; // PID.07
	public $sex; // PID.08
	public $guarantor; // GT1.03
	public $datetime; // MSH.07
	public $application; // PSC or blank
	public $facility; // Quest facility code
	public $verified_id; // ORC.11
	public $provider_id; // ORC.12
	public $request_notes; // NTE
	public $lab_notes = array(); // array of notes objects
	public $diagnosis = array(); // array of diagnosis objects
	public $orders = array(); // array of orders objects
	public $documents = array(); // array of document objects
}

class Order_HL7v2 {
	public $id;
	public $set_id; // OBR.01 - sequence
	public $order_control; // ORC.01 - CDC defined O119
	public $order_number; // ORC.02
	public $lab_number; // result ORC.03
	public $lab_status;	// result ORC.05
	public $service_id; // OBR.04
	public $key_1; // OBR.18 store & forward
	public $key_2; // OBR.19 store & forward
	public $specimen_datetime; // OBR.07
	public $received_datetime; // result OBR.14
	public $result_datetime; // result OBR.22
	public $service_section; // result OBR.24
	public $result_status; // result OBR.25
	public $reflex_order; // result OBR.26 order which caused relex
	public $aoe = array(); // array of AOE objects
	public $notes = array(); // array of Note objects
	public $results = array(); // array of result objects
}

class Result_HL7v2 {
	public $id;
	public $set_id; // OBX.01 - sequence
	public $value_type; // OBX.02
	public $observation_id; // OBX.03
	public $observation_value; // OBX.05
	public $observation_units; // OBX.06
	public $reference_range; // OBX.07
	public $abnormal_flags; // OBX.08
	public $observation_status; // OBX.11
	public $observation_datetime; // OBX.14
	public $producer_id; // OBX.15
	public $notes = array(); // array of notes objects
}

class Note_HL7v2 {
	public $set_id; // NTE.01 - sequence
	public $source; // NTE.02
	public $comment; // NTE.03
}

class Aoe_HL7v2 {
	public $set_id; // OBX.01
	public $value_type; // OBX.02
	public $observation_id; // OBX.03
	public $observation_value; // OBX.05
}

class Diagnosis_HL7v2 {
	public $set_id; // DG1.01
	public $coding_method; // DG1.02
	public $diagnosis_code; // DG1.03
	public $diagnosis_text; // DG1.04
}

class Insurance_HL7v2 {
	public $set_id; // IN1.01
	public $company_id; // IN1.03
	public $company_name; // IN1.04
	public $group; // IN1.08
	public $subscriber; // IN1.16
	public $relation; // IN1.17
	public $address; // IN1.19
	public $policy; // IN1.36
	public $type; // IN1.47
}