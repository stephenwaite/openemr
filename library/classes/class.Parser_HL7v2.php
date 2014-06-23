<?php
	// $Id$
	// $Author$
	// HL7 Parser

class Parser_HL7v2 {

	var $field_separator;
	var $map;
	var $message;
	var $message_type;

	var $MSH;
	var $PID;
	var $EVN;
	var $OBX;
	var $OBR;
	
	function Parser_HL7v2 ( $message, $_options = NULL ) {
		// Assume separator is a pipe
		$this->message = $message;
		$this->field_separator = '|';
		if (is_array($_options)) {
			$this->options = $_options;
		}
	}
	function parse () {
		$message = $this->message;
		// Split HL7v2 message into lines
		$segments = explode("\n", $message);
		// Fail if there are no or one segments
		if (count($segments) <= 1) {
			return false;
		}

		// Loop through messages
		$count = 0;
		foreach ($segments AS $__garbage => $segment) {
			$count++;

			// Determine segment ID
			$type = substr($segment, 0, 3);
			switch ($type) {
				case 'OBR':
				case 'OBX':
				case 'MSH':
				case 'PID':
				case 'EVN':
				$this->message_type = trim($type);
				call_user_func_array(
					array(&$this, '_'.$type),
					array(
						// All but type
						substr(
							$segment,
							-(strlen($segment)-3)
						)
					)
				);
				$this->map[$count]['type'] = $type;
				$this->map[$count]['position'] = 0;
				break;

				default:
				$this->message_type = trim($type);
				$this->__default_segment_parser($segment);
				$this->map[$count]['type'] = $type;
				$this->map[$count]['position'] = count($this->message[$type]);
				break;
			} // end switch type
		}
		
		// Depending on message type, handle differently
		switch ($this->message_type) {
			default:
			return ('Message type '.$this->message_type.' is '.
				'currently unhandled'."<br/>\n");
			break;
		} // end switch
	} // end constructor Parser_HL7v2

	function Handle() {
		// Set to handle current method
		$type = str_replace('^', '_', $this->MSH['message_type']);

		// Check for an appropriate handler
		$handler = CreateObject('_FreeMED.Handler_HL7v2_'.$type, $this);

		// Error out if the handler doesn't exist
		if (!is_object($handler)) {
			if ($this->options['debug']) {
				print "<b>Could not load class ".
					"_FreeMED.Handler_HL7v2_".$type.
					"</b><br/>\n";
			}
			return false;
		}

		// Run appropriate handler
		return $handler->Handle();
	} // end method Handle

	//----- All handlers go below here

	function _EVN ($segment) {
		$composites = $this->__parse_segment ($segment);
		if ($this->options['debug']) {
			print "<b>EVN segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
			}
		}

		list (
			$this->EVN['event_type_code'],
			$this->EVN['event_datetime'],
			$this->EVN['event_planned'],
			$this->EVN['event_reason'],
			$this->EVN['operator_id']
		) = $composites;
	} // end method _EVN

	function _MSH ($segment) {
		// Get separator
		$this->field_separator = substr($segment, 0, 1);
		$composites = $this->__parse_segment ($segment);
		if ($this->options['debug']) {
			print "<b>MSH segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
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
			$this->MSH['security'],
			$this->MSH['message_type'],
			$this->MSH['message_control_id'],
			$this->MSH['processing_id'],
			$this->MSH['version_id'],
			$this->MSH['sequence_number'],
			$this->MSH['confirmation_pointer'],
			$this->MSH['accept_ack_type'],
			$this->MSH['application_ack_type'],
			$this->MSH['country_code']
		) = $composites;

		// TODO: Extract $this->MSH['encoding_characters'] and use
		// it instead of assuming the defaults.
	} // end method _MSH

		function _OBX ($segment) {
		$composites = $this->__parse_segment ($segment);
		if ($this->options['debug']) {
			print "<b>OBX segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
			}
		}

		list (
			$__garbage, // Skip index [0], it's the segment id
			$this->OBX['set_id_obx'],
			$this->OBX['value_type'],			
			$this->OBX['set_observation_id'],
			$this->OBX['set_observation_sub_id'],
			$this->OBX['set_observation_value'],
			$this->OBX['units'],
			$this->OBX['reference_range'],
			$this->OBX['abnormal_flags'],
			$this->OBX['probability'],
			$this->OBX['nature_of_abnormal_test'],
			$this->OBX['observ_result_status'],
			$this->OBX['data_last_obs_normal_values'],
			$this->OBX['user_defined_access_checks'],
			$this->OBX['date/time_of_the_observation'],
			$this->OBX['producers_id'],
			$this->OBX['responsible_observer'],
			$this->OBX['observation_method']
			
		) = $composites;
	} // end method _OBX
	
		function _OBR ($segment) {
		$composites = $this->__parse_segment ($segment);
		if ($this->options['debug']) {
			print "<b>OBR segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
			}
		}

		list (
			$__garbage, // Skip index [0], it's the segment id
			$this->OBR['set_id_obR'],
			$this->OBR['placer_order_number'],			
			$this->OBR['filler_order_number'],
			$this->OBR['universal_service_id'],
			$this->OBR['priority'],
			$this->OBR['requested_date_time'],
			$this->OBR['observation_date_time'],
			$this->OBR['observation_end_date_time'],
			$this->OBR['collection_volume'],
			$this->OBR['collector_identifier'],
			$this->OBR['specimen_action_code'],
			$this->OBR['danger_code'],
			$this->OBR['relevant_clinical_info'],
			$this->OBR['specimen_received_datetime'],
			$this->OBR['specimen_source'],
			$this->OBR['ordering_provider'],
			$this->OBR['order_callback_phone_number'],
			$this->OBR['placer_field_1'],
			$this->OBR['placer_field_2'],
			$this->OBR['filler_field_1'],
			$this->OBR['filler_field_2'],
			$this->OBR['results_rptstatus_chng_datetime'],
			$this->OBR['charge_to_practice'],
			$this->OBR['diagnostic_serv_sect_id'],
			$this->OBR['result_status'],
			$this->OBR['patient_result'],
			$this->OBR['quantity_timing'],
			$this->OBR['result_copies_to'],
			$this->OBR['parent'],
			$this->OBR['transportation_mode'],
			$this->OBR['reason_for_study'],
			$this->OBR['principal_result_interpreter'],
			$this->OBR['assistant_result_interpreter'],
			$this->OBR['technician'],
			$this->OBR['transcriptionist'],
			$this->OBR['scheduled_datetime'],
			$this->OBR['number_of_sample_containers'],
			$this->OBR['transport_logistics_of_collected_sample'],
			$this->OBR['collectors_comment'],
			$this->OBR['collectors_comment'],
			$this->OBR['transport_arrangement_responsibility'],
			$this->OBR['transport_arranged'],
			$this->OBR['escort_required'],
			$this->OBR['planned_patient_transport_comment'],
			
		) = $composites;
	} // end method _OBR
	
	function _PID ($segment) {
		$composites = $this->__parse_segment ($segment);
		if ($this->options['debug']) {
			print "<b>PID segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
			}
		}

		list (
			$__garbage, // Skip index [0], it's the segment id		
			$this->PID['set_id_patient_id'],
			$this->PID['patient_id_external_id'],
			$this->PID['patient_id_internal_id'],
			$this->PID['patient_id_alternate_id'],
			$this->PID['patient_name'],
			$this->PID['mothers_maiden_name'],
			$this->PID['datetime_of_birth'],
			$this->PID['sex'],
			$this->PID['patient_alias'],
			$this->PID['race'],
			$this->PID['patient_address'],
			$this->PID['country_code'],
			$this->PID['phone_number_home'],
			$this->PID['phone_number_business'],
			$this->PID['primary_language'],
			$this->PID['marital_status'],
			$this->PID['religion'],
			$this->PID['patient_account_number'],
			$this->PID['ss_number'],
			$this->PID['drivers_license_number'],
			$this->PID['mothers_identifier'],
			$this->PID['ethnic_group'],
			$this->PID['birth_place'],
			$this->PID['multiple_birth_indicator'],
			$this->PID['birth_order'],
			$this->PID['citizenship'],
			$this->PID['veterans_military_status'],
			$this->PID['nationality'],
			$this->PID['patient_death_datetime'],
			$this->PID['patient_death_indicator'],
		) = $composites;
	} // end method _OBX
//----- Truly internal functions
	function __default_segment_parser ($segment) {
		$composites = $this->__parse_segment($segment);

		// The first composite is always the message type
		$type = $composites[0];

		// Debug
		if ($this->options['debug']) {
			print "<b>".$type." segment</b><br/>\n";
			foreach ($composites as $k => $v) {
				print "composite[$k] = ".prepare($v)."<br/>\n";
			}
		}

		// Try to parse composites
		foreach ($composites as $key => $composite) {
			// If it is a composite ...
			if (!(strpos($composite, '^') === false)) {
				$composites[$key] = $this->__parse_composite($composite);
			}
		}
		
		$pos = 0;

		// Find out where we are
		if (is_array($this->message[$type])) {
			$pos = count($this->message[$type]);
		}
		
		//Ramesh Nagul - EnSoftek commented line out as it is throwing an error in parsing.
		//$this->message[$type][$pos] = $composites;
		// Add parsed segment to message
		//Fix is below
		$this->map[$pos][$type] = $composites;
	} // end method __default_segment_parser

	function __parse_composite ($composite) {
		return explode('^', $composite);
	} // end method __parse_composite

	function __parse_segment ($segment) {
		return explode($this->field_separator, $segment);
	} // end method __parse_segment
	
	function composite_array() {
		$cmp = array();
		$cmp["MSH"] = $this->MSH;
		$cmp["EVN"] = $this->EVN;	
		$cmp["OBX"]=  $this->OBX;
		$cmp["OBR"]=  $this->OBR;
		$cmp["PID"]=  $this->PID;
		
		return $cmp;
	}
} // end class Parser_HL7v2

?>
