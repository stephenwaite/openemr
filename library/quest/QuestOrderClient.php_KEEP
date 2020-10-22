<?php
/** **************************************************************************
 *	QuestOrderClient.PHP
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
require_once 'OrderService.php';
require_once 'QuestModelHL7v2.php';
require_once("{$GLOBALS['srcdir']}/classes/Document.class.php");

if (!class_exists("QuestOrderClient")) {
	/**
	 * The class QuestOrderClient submits lab order (HL7 messages) to the MedPlus Hub
	 * platform.  Encapsulates the sending of an HL7 order to a Quest Lab
	 * via the Hub’s SOAP Web service.
	 *	
	 */
	class QuestOrderClient {
		/**
		 * Will pass the username and password to establish a service connection to
		 * the hub. Facilitates packaging the order in a proper HL7 format. Performs
		 * the transmission of the order to the Hub's SOAP Web Service. Provides
		 * method calls to the Results Web Service to retrieve lab results.
		 * 
		 */
		private $STATUS = "D"; // development (T=training, P=production)
		private $ENDPOINT = "https://cert.hub.care360.com/orders/service?wsdl";
		private $USERNAME = "";
		private $PASSWORD = "";
		
		// SENDING_APPLICATION designates the application that is sending the order
		// message to Hub
		private $SENDING_APPLICATION = "";
		
		// SENDING_FACILITY designates the account number provided to you by Quest
		// for the businessunit you are ordering tests with
		private $SENDING_FACILITY = "";

		// RECEIVING_FACILITY designates the business unit within Quest from which
		// the labs are being ordered
		private $RECEIVING_FACILITY = "";		
		
		// Document storage directory
		private $DOCUMENT_CATEGORY = ""; // quest
		private $repository;
		
		private $insurance = array();
		private $orders = array();
		private $service = null;
		private $request = null;
		private $response = null;
		private $documents = array();

		/**
		 * Constructor for the 'order client' class which initializes a reference 
		 * to the Quest Hub web service.
		 *
		 * @package QuestWebService
		 * @access public
		 */
		public function __construct($site) {
			$this->DOCUMENT_CATEGORY = $GLOBALS['lab_quest_catid'];
			$this->RECEIVING_FACILITY = $GLOBALS['lab_quest_facilityid'];
			$this->SENDING_APPLICATION = $GLOBALS['lab_quest_hubname'];
			$this->SENDING_FACILITY = $site;
			$this->USERNAME = $GLOBALS['lab_quest_username'];
			$this->PASSWORD = $GLOBALS['lab_quest_password'];
			$this->STATUS = $GLOBALS['lab_quest_status'];
			if ($this->STATUS == 'P')
				$this->ENDPOINT = 'https://hubservices.medplus.com/orders/service?wsdl';
				
			$options = array();
			$options['wsdl_local_copy'] = 'wsdl_quest_orders';
			$options['login'] = $this->USERNAME;
			$options['password'] = $this->PASSWORD;
			$this->service = new OrderService($this->ENDPOINT,$options);
			$this->request = new OrderSupportServiceRequest();
			$this->response = new OrderSupportServiceResponse();	

			$this->repository = $GLOBALS['oer_config']['documents']['repository'];
			
			// sanity check
			if ( !$this->DOCUMENT_CATEGORY ||
					!$this->RECEIVING_FACILITY ||
					!$this->SENDING_APPLICATION ||
					!$this->SENDING_FACILITY ||
					!$this->USERNAME ||
					!$this->PASSWORD ||
					!$this->ENDPOINT ||
					!$this->STATUS ||
					!$this->repository )
				throw new Exception ('Quest Interface Not Properly Configured!!');
			
			return;
		}

		public function addInsurance($ins) {
			$orderMessage = "IN1|$ins->set_id|||$ins->company_name|$ins->company_address|||$ins->group||||||||$ins->subscriber|$ins->relation||$ins->address||||||||||||||||$ins->plan|$ins->policy|||||||||||$ins->type|\r";
			$this->insurance[] = $orderMessage;
		}
		
		public function addOrder($request,$order) {
			$orderMessage = null;

			// common order segment
			$orderMessage .= "ORC|$order->request_control|$order->request_number||||||||||$request->provider_id|\r";

			// observation request segment
			$orderMessage .= "OBR|1|$order->request_number||$order->service_id|||$order->specimen_datetime|||||||||$request->provider_id||||||||||||\r";
			
			if ($request->fasting) $orderMessage .= "NTE|1|I|".$request->fasting."|\r";
			
			// diagnosis segments
			$dx_count = 1;
			foreach ($order->diagnosis as $dx_data) {
				$orderMessage .= "DG1|$dx_count|ICD|$dx_data->diagnosis_code|$dx_data->diagnosis_text|\r";
				$dx_count++;
			}
				
			// aoe segments
			$aoe_count = 1;
			foreach ($order->aoe as $aoe_data) {
				$orderMessage .= "OBX|$aoe_count|ST|^^^$aoe_data->observation_code^$aoe_data->observation_label||$aoe_data->observation_text||||||||||||\r";
				$aoe_count++;
			}
				
			// add order to request message
			$this->orders[] = $orderMessage;
		}
		
		/**
		 * Helper to break comment into line array with max of 60 characters each line
		 * @param string $text
		 * @return array $lines
		 * 
		 */
		private function breakText($text) {
			$lines = array();
			if ($text) {
				$text = str_replace(array("\r\n", "\r", "\n"), " ", $text); // strip newlines
				$text = wordwrap($text,60,'^'); // mark breaks
				$lines = explode('^', $text); // make array
			}
			return $lines;
		}
		
		/**
		 * buildOrderMessage() constructs a valid HL7 Order message string
		 * for the patient and order provided.
	 	 *
	 	 * @access public
	 	 * @param int $pid patient identifier
	 	 * @param string $type order type identifier
	 	 * @param string[] $data array of order data
	 	 * @return Order $order
	 	 * 
		 */
		public function buildRequest($request) {
			// order type
			$type = 'D'; //development default
			if ($GLOBALS['lab_quest_status']) $type = $GLOBALS['lab_quest_status'];

			// generate message
			$MSH = "MSH|^~\\&|%s|%s|$request->application|%s|$request->datetime||ORM^O01|$request->request_number|$type|2.3.1\r";
			$orderMessage = sprintf($MSH, $this->SENDING_APPLICATION, $this->SENDING_FACILITY, $this->RECEIVING_FACILITY);

			$orderMessage .= "PID|1|$request->pid|$request->pid||$request->name||$request->dob|$request->sex|||$request->address||$request->phone||||||$request->ss|\r";
			if ($request->order_notes) {
				$notes = $this->breakText($request->order_notes);
				$seq = 1;
				foreach ($notes AS $note) {
					if ($note) $orderMessage .= "NTE|".$seq++."|I|".$note."|\r"; 
				}	
			}
				
			foreach ($this->insurance as $ins) {
				$orderMessage .= $ins;
			}
			
			$orderMessage .="GT1|1||$request->guarantor||$request->guarantor_address|$request->guarantor_phone||||\r";
			
			foreach ($this->orders as $order) {
				$orderMessage .= $order;
			}
			
			$this->request->hl7Order = $orderMessage;
//DEBUG			echo $orderMessage . "\n";
				
			return;
		}
		
		/**
		 *
	 	 * The validateOrder() method will:
	 	 *
		 * 1. Create a proxy for making SOAP calls
		 * 2. Create an Order request object which contains a valid HL7 Order message
		 * 3. Submit a Lab Order calling submitOrder().
		 * 4. Output response valuse to console.
		 *
		 */
		public function validateOrder() {
			$response = null;
			try {
				$response = $this->service->validateOrder($this->request);
				echo "Status: " . $response->status .
					"\nControl ID: " . $response->messageControlId .
					"\nTransaction ID: " . $response->orderTransactionUid;
				
				if ($response->responseMsg) 
					echo "\nResponse Message: " . $response->responseMsg;

				$valErrors = $response->validationErrors;
				if ($valErrors) {
					for ($ndx = 0; $ndx < count($valErrors); $ndx++) {
						echo "\tValidation Error: " . $valErrors[$ndx] . ".";
					}
				}
			} 
			catch (Exception $e) {
				echo($e->getMessage());
			}
		}
		
		/**
		 *
	 	 * The submitOrder() method will:
	 	 *
		 * 1. Create a proxy for making SOAP calls
		 * 2. Create an Order request object which contains a valid HL7 Order message
		 * 3. Submit a Lab Order calling submitOrder().
		 * 4. Output response valuse to console.
		 *
		 */
		public function submitOrder() {
			$response = null;
			if ($GLOBALS['lab_quest_status'] == 'D') { // don't send development orders
				echo "Status: DEVELOPMENT \n";
				echo "Message: Order not sent to Quest interface \n";
			}
			else {
				try {
					$response = $this->service->submitOrder($this->request);
					echo "Status: " . $response->status .
						"\nControl ID: " . $response->messageControlId .
						"\nTransaction ID: " . $response->orderTransactionUid;
				
					if ($response->responseMsg) 
						echo "\nResponse Message: " . $response->responseMsg;
				
					$valErrors = $response->validationErrors;
					if ($valErrors) {
						for ($ndx = 0; $ndx < count($valErrors); $ndx++) {
							echo "\nValidation Error: " . $valErrors[$ndx] . ".";
						}
					}
				} 
				catch (Exception $e) {
					echo($e->getMessage());
				}
			}
		}

		/**
		 *
	 	 * The getOrderDocuments() method will:
	 	 *
		 * 1. Create a proxy for making SOAP calls
		 * 2. Create an Order request object which contains a valid HL7 Order message
		 * 3. Submit a Lab Order calling submitOrder().
		 * 4. Output response valuse to console.
		 *
		 */
		public function getOrderDocuments($pid,$type='REQ') {
			// validate the respository directory
			$file_path = $this->repository . preg_replace("/[^A-Za-z0-9]/","_",$pid) . "/";
			if (!file_exists($file_path)) {
				if (!mkdir($file_path,0700)) {
					throw new Exception("The system was unable to create the directory for this upload, '" . $file_path . "'.\n");
				}
			}
		
			$type_array = array('REQ');
			if ($type == 'ABN') $type_array = array('ABN');
			if ($type == 'ABN-REQ') $type_array = array('ABN','REQ');
			$this->request->orderSupportRequests = $type_array;
					
			$doc_list = array();
			$response = null;
			
			if ($GLOBALS['lab_quest_status'] == 'D') { // don't send development orders
				echo "Status: DEVELOPMENT \n";
				echo "Message: Order not sent to Quest interface \n";
			}
			else {
				try {
					$response = $this->service->getOrderDocuments($this->request);
					echo "Status: " . $response->status .
						"\nControl ID: " . $response->messageControlId .
						"\nTransaction ID: " . $response->orderTransactionUid;
				
					if ($response->responseMsg) 
						echo "\nResponse Message: " . $response->responseMsg;
				
					$valErrors = $response->validationErrors;
					if ($valErrors) {
						for ($ndx = 0; $ndx < count($valErrors); $ndx++) {
							echo "\nValidation Error: " . $valErrors[$ndx] . ".";
						}
					}
					else {
						foreach ($response->orderSupportDocuments as $document) {
							echo "\nDocument Status: " . $document->requestStatus .
								"\nDocument Type: " . $document->documentType .
								"\nDocument Response: " . $document->responseMessage;

							if ($document->documentData) {
								$type = ($document->documentType == 'ABN')?'ABN':'ORDER';
								$unique = date('y').str_pad(date('z'),3,0,STR_PAD_LEFT); // 13031 (year + day of year)
								$docName = $response->messageControlId . "_" . $type;
			
								$docnum++;
								$file = $docName."_".$unique.".pdf";
								while (file_exists($file_path.$file)) { // don't overlay duplicate file names
									$docName = $response->messageControlId . "_" . $type . "_".$docnum++;
									$file = $docName."_".$unique.".pdf";
								}
			
								if (($fp = fopen($file_path.$file, "w")) == false) {
									throw new Exception('\nERROR: Could not create local file ('.$file_path.$file.')');
								}
								fwrite($fp,$document->documentData);
								fclose($fp);
								echo "\nDocument Name: " . $file;

								// register the new document
								$d = new Document();
								$d->name = $docName;
								$d->storagemethod = 0; // only hard disk sorage supported
								$d->url = "file://" .$file_path.$file;
								$d->mimetype = "application/pdf";
								$d->size = filesize($file_path.$file);
								$d->owner = $_SESSION['authUserID'];
								$d->hash = sha1_file( $file_path.$file );
								$d->type = $d->type_array['file_url'];
								$d->set_foreign_id($pid);
								$d->persist();
								$d->populate();

								$doc_list[] = $d; // save for later
							
								// update cross reference
								$query = "REPLACE INTO categories_to_documents set category_id = '".$this->DOCUMENT_CATEGORY."', document_id = '" . $d->get_id() . "'";
								sqlStatement($query);
							}
						}
					}
				} 
				catch (Exception $e) {
					echo($e->getMessage());
				}
			
				return $doc_list;
			}
		}
	}
}
