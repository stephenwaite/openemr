<?php
/** **************************************************************************
 *	WMT.CLASS.PHP
 *	This file contains the standard classes for the dermatology implementation
 *	of OpenEMR. The file must be included in each dermatology form file or the
 *	implementation will not function correctly.
 *
 *  NOTES:
 *  1) __CONSTRUCT - always uses the record ID to retrieve data from the database
 *  2) GET - uses alternate selectors to find and return associated object
 *  3) FIND - returns only the object ID without data using alternate selectors
 *  4) LIST - returns an array of IDs meeting specific selector criteria
 *  5) FETCH - returns an array of data meeting specific criteria
 *   
 * 
 *  @package WMT
 *  @version 1.0
 *  @copyright Williams Medical Technologies, Inc.
 *  @author Ron Criswell <info@keyfocusmedia.com>
 * 
 *************************************************************************** */

/** 
 * Provides a partial representation of the patient data record. This object
 * does NOT include all of the fields associated with the core patient data
 * record and should NOT be used for database updates.  It is intended only
 * for retrieval of partial patient information primarily for display 
 * purposes (reports for example).
 *
 * @package WMT
 * @subpackage Standard
 * @category Patient
 * @tutorial This object will vary by implementation
 */
class wmtPatient {
	// generated values
	public $format_name;
	public $birth_date;
	public $age;
	
	/**
	 * Constructor for the 'patient' class which retrieves the requested 
	 * patient information from the database or creates an empty object.
	 * 
	 * @param int $id patient record identifier
	 * @param boolean $update prepare data for sql update
	 * @return object instance of patient class
	 */
	public function __construct($id = false, $update = false) {
		if(!$id) return false;

		$query = "SELECT * FROM patient_data ";
		$query .= "WHERE id = $id ";
		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new Exception('wmtPatient::_construct - no patient record with id ('.$this->id.').');
		}
		
		// preformat commonly used data elements
		$this->format_name = ($this->title)? "$this->title " : "";
		$this->format_name .= ($this->fname)? "$this->fname " : "";
		$this->format_name .= ($this->mname)? substr($this->mname,0,1).". " : "";
		$this->format_name .= ($this->lname)? "$this->lname " : "";

		if ($this->DOB) {
			// Criswell - 2013-07-22 - Correct for mis configured PHP systems with no timezone set
			//$now = new DateTime();
			//$then = new DateTime($this->DOB);
			//$this->age = $then->diff($now)->y;
			$this->age = floor( (strtotime('today') - strtotime($this->DOB)) / 31556926);
			
			$this->birth_date = date('Y-m-d', strtotime($this->DOB));
		}
		
	}	

	/**
	 * Updates database with information from the given object.
	 * 
	 * @return null
	 */
	public function update() {
		// set appropriate date values
		$begdate = ($this->begdate) ? "'$this->begdate'" : "NULL";
		$enddate = ($this->enddate) ? "'$this->enddate'" : "NULL";
		$returndate = ($this->returndate) ? "'$this->returndate'" : "NULL";
		
		$title = ($this->title)? $this->title : 'undefined';
		
		// build query from object
		$query = '';
		$fields = wmtPatient::listFields();
		foreach ($this as $key => $value) {
			if (!in_array($key, $fields)) continue;
			if ($query) $query .= ",";
			$query .= " $key = '$value'";
		}
		
		// run the update		
		sqlInsert("UPDATE patient_data SET $query WHERE id = $this->id");
		
		return;
	}
	
	
	/**
	 * Returns an array of valid database fields for the object.
	 * 
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields() {
		return sqlListFields('patient_data');
	}
	
	/**
	 * Retrieve a patient object by PID value. Uses the base constructor for the 'patient' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @param int $pid patient record identifier
	 * @param boolean $update prepare for sql update
	 * @return object instance of patient class
	 */
	public static function getPidPatient($pid, $update = FALSE) {
		if(! $pid) {
			throw new Exception('wmtPatient::getPidPatient - no patient identifier provided.');
		}
		
		$results = sqlStatement("SELECT id FROM patient_data WHERE pid = '$pid'");
		$data = sqlFetchArray($results);

		return new wmtPatient($data['id'], $update);
	}
}

/** 
 * Provides a partial representation of the insurance data record. This object
 * does NOT include all of the fields associated with the core insurance data
 * record and should NOT be used for database updates.  It is intended only
 * for retrieval of partial insurance information primarily for display 
 * purposes (reports for example).
 *
 * @package WMT
 * @subpackage Standard
 * @category Insurance
 * 
 */
class wmtInsurance {
	// generated values
	public $subscriber_format_name;
	public $subscriber_birth_date;
	public $subscriber_age;
	
	/**
	 * Constructor for the 'patient' class which retrieves the requested 
	 * patient information from the database or creates an empty object.
	 * 
	 * @param int $id patient record identifier
	 * @return object instance of patient class
	 */
	public function __construct($id = false) {
		if(!$id) return false;

		$query = "SELECT a.*, i.*, c.name AS company_name, c.id AS company_id, c.freeb_type AS plan_type FROM insurance_data i ";
		$query .= "LEFT JOIN insurance_companies c ON i.provider = c.id ";
		$query .= "LEFT JOIN addresses a ON a.foreign_id = c.id ";
		$query .= "WHERE i.id = '$id' LIMIT 1 ";
		
		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			// check for empty records
			if (! $data['provider']) return false;
			
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = $value;
			}
		}
		else {
			throw new Exception('wmtInsurance::_construct - no insurance record with id ('.$id.').');
		}
		
		if ($this->subscriber_DOB) {
			$now = new DateTime();
			$then = new DateTime($this->subscriber_DOB);
			$this->subscriber_age = $then->diff($now)->y;
			
			$this->subscriber_birth_date = date('Y-m-d', strtotime($this->subscriber_DOB));
		}
		
	}	

	/**
	 * Retrieve a insurance object by PID value. Uses the base constructor for the 'insurance' class 
	 * to create and return the object. 
	 * 
	 * @static
	 * @param int $id patient record identifier
	 * @return array object list of insurance objects
	 */
	public static function getPidInsurance($pid, $type = null) {
		if(!pid) {
			throw new Exception('wmtPatient::getPidInsurance - no patient identifier provided.');
		}
		
		$list = array();
		$query = "SELECT id, type, date FROM insurance_data WHERE pid = '$pid' ";
		if ($type) $query .= "AND type = '".strtolower($type)."' ";
		$query .= "AND provider != '' AND provider IS NOT NULL "; 
		$query .= "ORDER BY date DESC ";

		/* 2013-08-26 CRISWELL - Modified to support expired insurance data
		$results = sqlStatement($query);
		for ($i = 0; $i < 3; $i++) { // primary, secondary, tertiary
			$id = null; // in case there is no insurance
			if ($data = sqlFetchArray($results)) $id = $data['id'];
			
			$list[$i] = new wmtInsurance($id); // retrieve or create empty object
		}
		*/
		$results = sqlStatement($query);
		while ($data = sqlFetchArray($results)) {
			if ($data['type'] == 'primary' && !$list[0]) $list[0] = new wmtInsurance($data['id']);
			if ($data['type'] == 'secondary' && !$list[1]) $list[1] = new wmtInsurance($data['id']);
			if ($data['type'] == 'tertiary' && !$list[2]) $list[2] = new wmtInsurance($data['id']);
		}
		
		return $list;
	}
	
	/**
	 * Retrieve a list of defined insurance companys and return them as a simple array of
	 * name's and ids;
	 * 
	 * @static
	 * @return array id=>name
	 */
	public static function getCompany($provider) {
		if (!$provider) return;
		
		$record = array();
		if ($provider == 'self') {
			$record['name'] = "Self Insured";
		}
		else {
			$query = "SELECT id, name, cms_id FROM insurance_companies LIMIT 1";
			$results = sqlStatement($query);
			$record = sqlFetchArray($results);
		}
				
		return $record;
	}
}

/**
 * Provides standardized processing for most forms.
 *
 * @package WMT
 * @subpackage Forms
 */
class wmtForm {
	public $id;
	public $date;
	public $pid;
	public $user;
	public $groupname;
	public $authorized;
	public $activity;
	public $status;
	public $priority;

	// control elements
	protected $form_name;
	protected $form_table;
	protected $form_title;

	/**
	 * Constructor for the 'form' class which retrieves the requested
	 * information from the database or creates an empty object.
	 *
	 * @param string $form_table database table
	 * @param int $id record identifier
	 * @param boolean $update prepare data for sql update
	 * @return object instance of form class
	 */
	public function __construct($form_name, $id = false, $update = false) {
		if (!$form_name)
			throw new Exception('wmtForm::_construct - no form name provided.');

		// store table name in object
		$this->form_name = $form_name;
		$this->form_table = 'form_'.$form_name;

		// create empy record or retrieve
		if (!$id) return false;

		// retrieve data
		$query = "SELECT * FROM $this->form_table ";
		$query .= "WHERE id = $id AND activity = 1";
		$results = sqlStatement($query);

		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new Exception('wmtForm::_construct - no record with id ('.$id.').');
		}

		// preformat commonly used data elements
		$this->date = ($this->date)? date('Y-m-d',strtotime($this->date)) : date('Y-m-d');

		return;
	}

	/**
	 * Inserts data from a form object into the database.
	 *
	 * @static
	 * @param wmtForm $object
	 * @return int $id identifier for new object
	 */
	public static function insert(wmtForm $object) {
		if(!$object->form_name || !$object->form_table)
			throw new Exception ("wmtForm::insert - object missing form information");

		if($object->id)
			throw new Exception ("wmtForm::insert - object already contains identifier");

		// set appropriate default values
		$object->date = ($object->date) ? "$object->date" : "NULL";
		$object->activity = 1;

		// build sql insert from object
		$query = '';
		$fields = wmtForm::listFields($object->form_name);
		foreach ($object as $key => $value) {
			if (!in_array($key, $fields) || $key == 'id' || $key == 'created') continue;
			if ($value == 'YYYY-MM-DD') continue;
				
			$query .= ($query)? ", $key = ? " : "$key = ? ";
			$values[] = ($value == 'NULL')? "NULL" : $value;
		}

		// run the insert
		$object->id = sqlInsert("INSERT INTO $object->form_table SET $query",$values);
		
		return $object->id;
	}

	/**
	 * Updates database with information from the given object.
	 *
	 * @return null
	 */
	public function update() {
		// set appropriate default values
		$this->date = ($this->date) ? $this->date : "NULL";
		$this->activity = 1;

		// build sql update from object
		$query = '';
		$fields = wmtForm::listFields($this->form_name);
		foreach ($this as $key => $value) {
			if (!in_array($key, $fields) || $key == 'id') continue;
			if ($value == 'YYYY-MM-DD') continue;
			$query .= ($query)? ", $key = ? " : "$key = ? ";
			$values[] = ($value == 'NULL')? "NULL" : $value;
		}
	
		// run the update
		sqlInsert("UPDATE $this->form_table SET $query WHERE id = $this->id",$values);

		return;
	}
	
	/**
	 * Returns an array list objects associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE is given
	 * then all issues for the ENCOUNTER are returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchEncounterList($form_name, $encounter, $active=TRUE) {
		if (!$form_name || !$encounter)
			throw new Exception('wmtForm::fetchEncounterItem - missing parameters');

		$query = "SELECT form_id FROM forms ";
		$query .= "WHERE formdir = '$form_name' AND encounter = '$encounter' ";
		if ($active) $query .= "AND deleted = 0 ";
		$query .= "ORDER BY date, created";

		$results = sqlStatement($query);

		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new wmtForm($form_name,$data['form_id']);
		}

		return $objectList;
	}

	/**
	 * Returns an array of valid database fields for the object.
	 *
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields($form_name) {
		if (!$form_name)
			throw new Exception('wmtForm::listFields - no form name provided.');

		$form_table = 'form_'.$form_name;
		$fields = sqlListFields($form_table);

		return $fields;
	}

}

/**
 * Provides standardized processing for most forms.
 *
 * @package WMT
 * @subpackage Documents
 */
class wmtDocument {

	private $template_mod;
	private $documents;
	private $document_categories;
	private $tree;
	private $config;
	private $file_path;

	public function __construct($template_mod = "general") {
		$this->documents = array();
		$this->template_mod = $template_mod;

		//get global config options for this namespace
		$this->config = $GLOBALS['oer_config']['documents'];
		if ($GLOBALS['document_storage_method'] == 1) {
			$this->file_path = $GLOBALS['OE_SITE_DIR'].'/documents/temp/';
		}
		else {
			$this->file_path = $this->config['repository'] . preg_replace("/[^A-Za-z0-9]/","_",$_GET['patient_id']) . "/";
		}
		
		// validate the directory
		if (!file_exists($this->file_path)) {
			if (!mkdir($this->file_path,0700)) {
				$error .= "The system was unable to create the directory for this upload, '" . $this->file_path . "'.\n";
			}
		}

		$this->tree = new CategoryTree(1);
	}

	function upload($file, $patient_id, $category_id) {
		$couchDB = false;
		$harddisk = true;

		$fname = "--file name--";
		if (file_exists($this->file_path.$fname)) {
			unlink($this->file_path.$fname); // delete it
				
		}

		$d = new Document();
		$d->storagemethod = $GLOBALS['document_storage_method'];
		$d->url = "file://" .$this->file_path.$fname;
		$d->mimetype = $file['type'];
		$d->size = $file['size'];
		$d->owner = $_SESSION['authUserID'];
		$d->hash = sha1_file( $this->file_path.$fname );
		$d->type = $d->type_array['file_url'];
		$d->set_foreign_id($patient_id);
		$d->persist();
		$d->populate();
			
		if (is_numeric($d->get_id()) && is_numeric($category_id)){
			$sql = "REPLACE INTO categories_to_documents set category_id = '" . $category_id . "', document_id = '" . $d->get_id() . "'";
			$d->_db->Execute($sql);
		}
	}
		
/*
		foreach ($_FILES as $file) {
			$fname = $file['name'];
			$err = "";
			if ($file['error'] > 0 || empty($file['name']) || $file['size'] == 0) {
				$fname = $file['name'];
				if (empty($fname)) {
					$fname = htmlentities("<empty>");
				}
				$error = "Error number: " . $file['error'] . " occured while uploading file named: " . $fname . "\n";
				if ($file['size'] == 0) {
					$error .= "The system does not permit uploading files of with size 0.\n";
				}
				 
			}
			else {
				 
				if (!file_exists($this->file_path)) {
					if (!mkdir($this->file_path,0700)) {
						$error .= "The system was unable to create the directory for this upload, '" . $this->file_path . "'.\n";
					}
				}
				if ( $_POST['destination'] != '' ) {
					$fname = $_POST['destination'];
				}
				$fname = preg_replace("/[^a-zA-Z0-9_.]/","_",$fname);
				if (file_exists($this->file_path.$fname)) {
					$error .= xl('File with same name already exists at location:','','',' ') . $this->file_path . "\n";
					$fname = basename($this->_rename_file($this->file_path.$fname));
					$file['name'] = $fname;
					$error .= xl('Current file name was changed to','','',' ') . $fname ."\n";
				}
				 
				if ( $doDecryption ) {
					$tmpfile = fopen( $file['tmp_name'], "r" );
					$filetext = fread( $tmpfile, $file['size'] );
					$plaintext = $this->decrypt( $filetext, $passphrase );
					fclose($tmpfile);
					unlink( $file['tmp_name'] );
					$tmpfile = fopen( $file['tmp_name'], "w+" );
					fwrite( $tmpfile, $plaintext );
					fclose( $tmpfile );
					$file['size'] = filesize( $file['tmp_name'] );
				}
				$docid = '';
				$resp = '';
				if($couchDB == true){
					$couch = new CouchDB();
					$docname = $_SESSION['authId'].$patient_id.$encounter.$fname.date("%Y-%m-%d H:i:s");
					$docid = $couch->stringToId($docname);
					$tmpfile = fopen( $file['tmp_name'], "rb" );
					$filetext = fread( $tmpfile, $file['size'] );
					fclose( $tmpfile );
					//--------Temporarily writing the file for calculating the hash--------//
					//-----------Will be removed after calculating the hash value----------//
					$temp_file = fopen($this->file_path.$fname,"w");
					fwrite($temp_file,$filetext);
					fclose($temp_file);
					//---------------------------------------------------------------------//

					$json = json_encode(base64_encode($filetext));
					$db = $GLOBALS['couchdb_dbase'];
					$data = array($db,$docid,$patient_id,$encounter,$file['type'],$json);
					$resp = $couch->check_saveDOC($data);
					if(!$resp->id || !$resp->_rev){
						$data = array($db,$docid,$patient_id,$encounter);
						$resp = $couch->retrieve_doc($data);
						$docid = $resp->_id;
						$revid = $resp->_rev;
					}
					else{
						$docid = $resp->id;
						$revid = $resp->rev;
					}
					if(!$docid && !$revid){ //if couchdb save failed
						$error .=  "<font color='red'><b>".xl("The file could not be saved to CouchDB.") . "</b></font>\n";
						if($GLOBALS['couchdb_log']==1){
							ob_start();
							var_dump($resp);
							$couchError=ob_get_clean();
							$log_content = date('Y-m-d H:i:s')." ==> Uploading document: ".$fname."\r\n";
							$log_content .= date('Y-m-d H:i:s')." ==> Failed to Store document content to CouchDB.\r\n";
							$log_content .= date('Y-m-d H:i:s')." ==> Document ID: ".$docid."\r\n";
							$log_content .= date('Y-m-d H:i:s')." ==> ".print_r($data,1)."\r\n";
							$log_content .= $couchError;
							$this->document_upload_download_log($patient_id,$log_content);//log error if any, for testing phase only
						}
					}
				}
				if($harddisk == true){
					$uploadSuccess = false;
					if(move_uploaded_file($file['tmp_name'],$this->file_path.$fname)){
						$uploadSuccess = true;
					}
					else{
						$error .= xl("The file could not be succesfully stored, this error is usually related to permissions problems on the storage system")."\n";
					}
				}
				$this->assign("upload_success", "true");
				$d = new Document();
				$d->storagemethod = $GLOBALS['document_storage_method'];
				if($harddisk == true)
					$d->url = "file://" .$this->file_path.$fname;
				else
					$d->url = $fname;
				if($couchDB == true){
					$d->couch_docid = $docid;
					$d->couch_revid = $revid;
				}
				if ($file['type'] == 'text/xml') {
					$d->mimetype = 'application/xml';
				}
				else {
					$d->mimetype = $file['type'];
				}
				$d->size = $file['size'];
				$d->owner = $_SESSION['authUserID'];
				$sha1Hash = sha1_file( $this->file_path.$fname );
				if($couchDB == true){
					//Removing the temporary file which is used to create the hash
					unlink($this->file_path.$fname);
				}
				$d->hash = $sha1Hash;
				$d->type = $d->type_array['file_url'];
				$d->set_foreign_id($patient_id);
				if($harddisk == true || ($couchDB == true && $docid && $revid)){
					$d->persist();
					$d->populate();
				}
				$this->assign("file",$d);
					
				if (is_numeric($d->get_id()) && is_numeric($category_id)){
					$sql = "REPLACE INTO categories_to_documents set category_id = '" . $category_id . "', document_id = '" . $d->get_id() . "'";
					$d->_db->Execute($sql);
				}
				if($GLOBALS['couchdb_log']==1 && $log_content!=''){
					$log_content .= "\r\n\r\n";
					$this->document_upload_download_log($patient_id,$log_content);
				}
			}
		}
		$this->assign("error", nl2br($error));
		//$this->_state = false;
		$_POST['process'] = "";
		//return $this->fetch($GLOBALS['template_dir'] . "documents/" . $this->template_mod . "_upload.html");
	}
*/
}


/**
 * Miscellaneous helper functions
 *
 */

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















/** 
 * Provides standardized base class for an encounter which
 * is typically extended for specific types of encounters.
 *
 * @package WMT
 * @subpackage Encounter
 */
class wmtEncounter {
	public $id;
	public $date;
	public $reason;
	public $facility;
	public $facility_id;
	public $pid;
	public $encounter;
	public $onset_date;
	public $sensitivity;
	public $billing_note;
	public $pc_catname;
	public $pc_catid;
	public $provider_id;
	public $supervisor_id;
	public $referral_source;
	public $billing_facility;
	
	/**
	 * Constructor for the 'encounter' class which retrieves the requested 
	 * record from the database or creates an empty object.
	 * 
	 * @param int $id record identifier
	 * @param boolean $update prepare data for sql update
	 * @return object instance of class
	 */
	public function __construct($id = false, $update = false) {
		if(!$id) return false;

		$query = "SELECT fe.*, pc.pc_catname FROM form_encounter fe ";
		$query .= "LEFT JOIN openemr_postcalendar_categories pc ON fe.pc_catid = pc.pc_catid ";
		$query .= "WHERE fe.id = $id ";
		$query .= "ORDER BY fe.date, fe.id";
		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				if ($key == 'date' || $key == 'onset_date') {
					$value = date('Y-m-d', strtotime($value));
				}
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new Exception('wmtEncounter::_construct - no encounter record with id ('.$id.').');
		}
	}	
		
	/**
	 * Inserts data from an error object into the database.
	 * 
	 * @static
	 * @param Errors $iderror_object
	 * @return null
	 */
	public static function insert(wmtEncounter $object) {
		if($object->id) {
			throw new Exception ("wmtEncounter::insert - object already contains identifier");
		}

		// get facility name from id
		$fres = sqlQuery("SELECT name FROM facility WHERE id = $object->facility_id");
		$facility = $fres['name'];

		// create basic encounter
		$object->encounter = generate_id(); // in sql.inc
		
		// add base record
		$enc_date = ($object->date) ? "$object->date" : "date('Y-m-d)";
		$onset_date = ($object->onset_date) ? "$object->onset_date" : "$enc_date";

		$object->id = sqlInsert("INSERT INTO form_encounter SET " .
			"date = '$enc_date', " .
			"onset_date = '$onset_date', " .
			"reason = '$object->reason', " .
			"facility = '$facility', " .
			"pc_catid = '$object->pc_catid', " .
			"facility_id = '$object->facility_id', " .
			"billing_facility = '$object->billing_facility', " .
			"sensitivity = '$object->sensitivity', " .
			"referral_source = '$object->referral_source', " .
			"pid = '$object->pid', " .
			"encounter = '$object->encounter', " .
			"provider_id = '$object->provider_id'");

		return $object->id;
	}

	/**
	 * Updates data from an object into the database.
	 * 
	 * @static
	 * @param wmtEncounter $object
	 * @return null
	 */
	public function update() {
		if(!$this->id) {
			throw new Exception ("wmtEncounter::update - object contains no identifier");
		}
		
		// get facility name from id
		$fres = sqlQuery("SELECT name FROM facility WHERE id = $this->facility_id");
		$facility = $fres['name'];

		// update basic encounter
		$enc_date = ($this->date) ? "$this->date" : "date('Y-m-d')";
		$onset_date = ($this->onset_date) ? "$this->onset_date" : "$enc_date";

		sqlInsert("UPDATE form_encounter SET " .
			"date = '$enc_date', " .
			"onset_date = '$onset_date', " .
			"reason = '$this->reason', " .
			"facility = '$facility', " .
			"pc_catid = '$this->pc_catid', " .
			"facility_id = '$this->facility_id', " .
			"billing_facility = '$this->billing_facility', " .
			"sensitivity = '$this->sensitivity', " .
			"referral_source = '$this->referral_source', " .
			"pid = '$this->pid', " .
			"encounter = '$this->encounter', " .
			"provider_id = '$this->provider_id' " .
			"WHERE id = '$this->id'");

		return;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function listPidEncounters($pid) {
		if (!$pid) return FALSE;

		$query = "SELECT fe.encounter, fe.id FROM form_encounter fe ";
		$query .= "LEFT JOIN issue_encounter ie ON fe.id = ie.list_id ";
		$query .= "LEFT JOIN lists l ON ie.list_id = l.id ";
		$query .= "WHERE fe.pid = $pid AND l.enddate IS NULL ";
		$query .= "ORDER BY fe.date, fe.encounter";

		$results = sqlStatement($query);
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = array('id' => $data['id'], 'encounter' => $data['encounter']);
		}
		
		return $txList;
	}

	/**
	 * Retrieve the encounter record by encounter number.
	 * 
	 * @param int $id lists record identifier
	 * @param boolean $update prepare data for sql update
	 * @return object instance of lists class
	 */
	public static function getEncounter($encounter, $update = false) {
		if (!$encounter) return FALSE;

		$query = "SELECT id FROM form_encounter WHERE encounter = '$encounter' ";
		$results = sqlStatement($query);
		$data = sqlFetchArray($results);
		
		return new wmtEncounter($data['id'], $update);
	}
}

/** 
 * Provides standardized processing for most forms.
 *
 * @package WMT
 * @subpackage Forms
 */
class wmtBilling {
	public $id;
	public $date;
	public $code_type;
	public $code;
	public $pid;
	public $provider_id;
	public $user;
	public $groupname;
	public $authorized;
	public $encounter;
	public $code_text;
	public $billed;
	public $activity;
	public $payer_id;
	public $bill_process;
	public $bill_date;
	public $process_date;
	public $process_file;
	public $modifier;
	public $units;
	public $fee;
	public $justify;
	public $target;
	public $x12_partner_id;
	public $ndc_info;
	public $notecodes;
	
	/**
	 * Constructor for the 'billing' class which retrieves the requested 
	 * information from the database or creates an empty object.
	 * 
	 * @param int $id record identifier
	 * @param boolean $update prepate data for sql update
	 * @return object instance of form class
	 *
	 */
	public function __construct($id = false, $update = false) {
		// create empy record or retrieve
		if(!$id) return false;
		
		// retrieve data
		$query = "SELECT * FROM billing ";
		$query .= "WHERE id = $id AND activity = 1";
		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new Exception('wmtBilling::_construct - no record with id ('.$id.').');
		}
		
		// preformat commonly used data elements
		$this->date = ($this->date)? date('Y-m-d',strtotime($this->date)) : date('Y-m-d');
		
		return;		
	}	

	/**
	 * Inserts data from a billing object into the database.
	 * 
	 * @static
	 * @param wmtBilling $object
	 * @return int $id identifier for new object
	 */
	public static function insert(wmtBilling $object) {
		if($object->id)
			throw new Exception ("wmtBilling::insert - object already contains identifier");

		// set appropriate default values
		$object->date = ($object->date) ? $object->date : date('Y-m-d');
		$object->bill_date = ($object->bill_date) ? $object->bill_date : "NULL"; 
		$object->process_date = ($object->process_date) ? $object->process_date : "NULL"; 
		$object->activity = 1;
		
		// build sql insert from object
		$query = '';
		$fields = wmtBilling::listFields();
		foreach ($object as $key => $value) {
			if (!in_array($key, $fields) || $key == 'id') continue;
			if ($value == 'YYYY-MM-DD') continue;
			if ($key == 'units' && $value == '') $value = "0";
			if ($key == 'fee' && $value == '') $value = "0";
			
			$query .= ($query)? ", $key = " : "$key = ";
			$query .= ($value == 'NULL')? "NULL" : "'$value'";
		}

		// run the insert		
		$object->id = sqlInsert("INSERT INTO billing SET $query");
		
		return $object->id;
	}

	/**
	 * Updates database with information from the given object.
	 * 
	 * @return null
	 */
	public function update() {
		// set appropriate default values
		$this->date = ($this->date) ? $this->date : date('Y-m-d');
		$this->activity = 1;
				
		// build sql update from object
		$query = '';
		$fields = wmtBilling::listFields();
		foreach ($this as $key => $value) {
			if (!in_array($key, $fields) || $key == 'id') continue;
			if ($value == 'YYYY-MM-DD') continue;
			$query .= ($query)? ", $key = " : "$key = ";
			$query .= ($value == 'NULL')? "NULL" : "'$value'";
		}
		
		// run the update		
		sqlInsert("UPDATE billing SET $query WHERE id = $this->id");
		
		return;
	}
	
	/**
	 * Returns an array list objects associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE is given
	 * then all issues for the ENCOUNTER are returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchEncounterList($encounter, $type=FALSE, $active=TRUE) {
		if (!$encounter) return FALSE;
	
		$query = "SELECT id FROM billing ";
		$query .= "WHERE encounter = $encounter ";
		if ($active) $query .= "AND activity = 1 ";
		if ($type) $query .= "AND code_type = '$type' ";
		$query .= "ORDER BY code_type, code";
	
		$results = sqlStatement($query);
	
		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new wmtBilling($data['id']);
		}
	
		return $objectList;
	}
	
	/**
	 * Returns a single list object associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE 
	 * is given then nothing is returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return $object list object
	 */
	public static function fetchEncounterItem($encounter, $type=FALSE, $active=TRUE) {
		if (!$encounter || !$type) return FALSE;
	
		$query = "SELECT id FROM billing ";
		$query .= "WHERE ie.encounter = $encounter ";
		if ($active) $query .= "AND activity = 1 ";
		$query .= "AND lists.type = '$type' ";
		$query .= "ORDER BY code_type, code, date ";
		$query .= "LIMIT 1";
	
		$data = sqlQuery($query);

		return new wmtBilling($data['id']);
	}
	
	/**
	 * Deletes all records associated with a given ENCOUNTER.
	 * 
	 * @static
	 * @param encounter unique identifier
	 * @param type billing type
	 * 
	 */
	public static function deleteEncounter($encounter, $type) {
		if(!$encounter || !$type)
			throw new Exception ("wmtBilling::deleteEncounter - missing parameters on call to delete");

		// run delete		
		sqlStatement("DELETE FROM billing WHERE encounter = '$encounter' AND code_type = '$type'");
		
		return;
	}

	/**
	 * Returns an array of valid database fields for the object.
	 * 
	 * @static
	 * @return array list of database field names
	 */
	public static function listFields() {
		$fields = sqlListFields('billing');
		return $fields;
	}
	
}

/**
 */
class wmtAdmission extends wmtForm {
	public $last_street;
	public $last_city;
	public $last_state;
	public $last_zip;
	public $homeless_city;
	public $homeless_zip;
	public $followup_flag;
	public $release_flag;
	public $release_names;
	public $admit_date;
	public $admit_program;
	public $admit_counselor;
	public $staff_notes;
	public $comments;
	
	/**
	 * Constructs a new object instance.
	 *
	 * @param int $id record identifier
	 * @param boolean $update prepare data for sql update
	 * @return object instance of class
	 */
	public function __construct($id = false, $update = false) {
		parent::__construct('hh_admission', $id, $update);

		// preformat commonly used data elements
		if ($this->admit_date)
			$this->admit_date = date('Y-m-d',strtotime($this->admit_date));
		
		return;
	}

	/**
	 * Inserts data from a form object into the database.
	 *
	 * @static
	 * @param wmtForm $object
	 * @return int $id identifier for new object
	 */
	public static function insert(wmtAdmission $object) {

		$object->pid = $_SESSION['pid'];
		$object->date = date('Y-m-d');

		return parent::insert($object);
	}

	/**
	 * Retrieves information for the given encounter.
	 *
	 * @static
	 * @param int encounter number
	 * @return $object
	 */
	public static function fetchEncounter($encounter = false) {
		$form_id = '';

		if ($encounter) {
			// retrieve the id
			$query = "SELECT form_id FROM forms WHERE encounter = $encounter ";
			$query .= "AND formdir = 'hh_admission' AND deleted = 0 LIMIT 1";
			$data = sqlQuery($query);
			if ($data['form_id']) $form_id = $data['form_id'];
		}

		$object = new wmtAdmission($form_id); // retrieve/create record
		return $object;
	}

	/**
	 * Retrieves information for the admission associated with a 
	 * given program.
	 *
	 * @static
	 * @param int encounter number
	 * @return $object
	 */
	public static function fetchProgram($pgmid = false) {
		$object = false;

		if ($pgmid) {
			$query = "SELECT id FROM form_hh_admission ";
			$query .= "WHERE pgmid = $pgmid ";
			$data = sqlQuery($query);
			if ($data['id'])
				$object = new wmtAdmission($data['id']);
		}

		return $object;
	}

}

/** 
 * Provides a base class for records stored in the 'lists' table which includes
 * a large collection of general information. This is a general class which may
 * be used as a standalone object but is typically extended to support specific
 * types of list content.
 *
 * @package WMT
 * @subpackage wmtList
 */
class wmtList {
	public $id;
	public $date;
	public $type;
	public $title;
	public $begdate;
	public $enddate;
	public $returndate;
	public $occurrence;
	public $classification;
	public $referredby;
	public $extrainfo;
	public $diagnosis;
	public $activity;
	public $comments;
	public $pid;
	public $user;
	public $groupname;
	public $outcome;
	public $reaction;
	
	/**
	 * Constructor for the class which retrieves the requested 
	 * 'lists' record from the database or creates an empty object.
	 * 
	 * @param int $id lists record identifier
	 * @param boolean $update prepare object for sql update
	 * @return object instance of lists class
	 */
	public function __construct($id = FALSE, $update = false) {
		if(!$id) return false;

		$query = "SELECT * FROM lists WHERE id = $id";

		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			// load everything returned into object
			foreach ($data as $key => $value) {
				$this->$key = ($update)? formDataCore($value) : $value;
			}
		}
		else {
			throw new Exception('wmtList::_construct - no list record with id ('.$this->id.').');
		}
	}	

	/**
	 * Inserts data from the provided object into the database.
	 * 
	 * @static
	 * @param wmtList $object
	 * @return null
	 */
	public static function insert(wmtList $object) {
		if($object->id) {
			throw new Exception("wmtList::insert - object already contains identifier");
		}

		// add generic record
		$begdate = ($object->begdate) ? "'$object->begdate'" : "NULL";
		$enddate = ($object->enddate) ? "'$object->enddate'" : "NULL";
		$returndate = ($object->returndate) ? "'$object->returndate'" : "NULL";
		
		$title = ($object->title)? $object->title : 'undefined';
		
		$object->id = sqlInsert("INSERT INTO lists SET " .
			"date = NOW(), " .
			"type = '$object->type', " .
			"title = '$title', " .
			"begdate = $begdate, " .
			"enddate = $enddate, " .
			"returndate = $returndate, " .
			"occurrence = '$object->occurrence', " .
			"classification = '$object->classification', " .
			"referredby = '$object->referredby', " .
			"extrainfo = '$object->extrainfo', " .
			"diagnosis = '$object->diagnosis', " .
			"activity = '$object->activity', " . 
			"comments = '$object->comments', " .
			"pid = '$object->pid', " .
			"user = '".$_SESSION['authUser']."', " .
			"groupname = '".$_SESSION['authProvider']."', " .
			"outcome = '$object->outcome', " .
			"reaction = '$object->reaction'");
		
		return $object->id;
	}

	/**
	 * Updates database with information from the given object.
	 * 
	 * @return null
	 */
	public function update() {
		if(! $this->id) 
			throw new Exception("wmtList::update - object does not contain identifier");
		
		// set appropriate date values
		$begdate = ($this->begdate) ? "'$this->begdate'" : "NULL";
		$enddate = ($this->enddate) ? "'$this->enddate'" : "NULL";
		$returndate = ($this->returndate) ? "'$this->returndate'" : "NULL";
		
		$title = ($this->title)? $this->title : 'undefined';
		
		sqlInsert("UPDATE lists SET " .
			"title = '$title', " .
			"begdate = $begdate, " .
			"enddate = $enddate, " .
			"returndate = $returndate, " .
			"occurrence = '$this->occurrence', " .
			"classification = '$this->classification', " .
			"referredby = '$this->referredby', " .
			"extrainfo = '$this->extrainfo', " .
			"diagnosis = '$this->diagnosis', " .
			"activity = '$this->activity', " . 
			"comments = '$this->comments', " .
			"pid = '$this->pid', " .
			"user = '".$_SESSION['authUser']."', " .
			"groupname = '".$_SESSION['authProvider']."', " .
			"outcome = '$this->outcome', " .
			"reaction = '$this->reaction' " .
			"WHERE id = $this->id ");
		
		return;
	}

	/**
	 * Returns a list of record identifiers associated with the
	 * given PID and optionally a given TYPE. If no TYPE is given
	 * then all list record for the PID are returned.
	 * 
	 * @static
	 * @param int $pid patient identifier
	 * @param string $type type of issue to select
	 * @param bool $active restricts results to active items
	 * @return array $itemList list of selected identifiers
	 */
	public static function listPidItems($pid, $type=FALSE, $active=TRUE) {
		if (!$pid) return FALSE;

		$query = "SELECT * FROM lists ";
		$query .= "WHERE pid = $pid ";
		if ($active) $query .= "AND enddate IS NULL AND returndate IS NULL ";
		if ($type) $query .= "AND type = '$type' ";
		$query .= "ORDER BY type, date, id";

		$results = sqlStatement($query);
	
		$itemList = array();
		while ($data = sqlFetchArray($results)) {
			$itemList[] = $data['id'];
		}
		
		return $itemList;
	}

	/**
	 * Returns an array list objects associated with the
	 * given PID and optionally a given TYPE. If no TYPE is given
	 * then all issues for the PID are returned.
	 * 
	 * @static
	 * @param int $pid patient identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchPidItems($pid, $type=FALSE, $active=TRUE) {
		if (!$pid) return FALSE;

		$query = "SELECT * FROM lists ";
		$query .= "WHERE pid = $pid ";
		if ($active) $query .= "AND enddate IS NULL AND returndate IS NULL ";
		if ($type) $query .= "AND type = '$type' ";
		$query .= "ORDER BY type, date, id";

		$results = sqlStatement($query);
	
		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new wmtList($data['id']);
		}
		
		return $objectList;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function linkEncounter($id, $pid, $encounter) {
		if (!$pid || !$encounter || !$id) {
			throw new Exception('wmtList::linkEncounter - missing required data elements');
		}

		// remove old links
		sqlStatement("DELETE FROM issue_encounter WHERE " .
  			"pid = '$pid' AND encounter = '$encounter' AND list_id = '$id' ");
		
		// add new link
		$query = "INSERT INTO issue_encounter SET ";
		$query .= "pid = '$pid', list_id = '$id', encounter = '$encounter' ";
    	sqlStatement ($query);
		
		return;
	}

	/**
	 * Returns an array list objects associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE is given
	 * then all issues for the ENCOUNTER are returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return array $objectList list of selected list objects
	 */
	public static function fetchEncounterList($encounter, $type=FALSE, $active=TRUE) {
		if (!$encounter) return FALSE;
	
		$query = "SELECT lists.id FROM lists ";
		$query .= "LEFT JOIN issue_encounter ie ON ie.list_id = lists.id ";
		$query .= "WHERE ie.encounter = $encounter ";
		if ($active) $query .= "AND lists.enddate IS NULL AND lists.returndate IS NULL ";
		if ($type) $query .= "AND lists.type = '$type' ";
		$query .= "ORDER BY lists.type, lists.date, lists.id";
	
		$results = sqlStatement($query);
	
		$objectList = array();
		while ($data = sqlFetchArray($results)) {
			$objectList[] = new wmtList($data['id']);
		}
	
		return $objectList;
	}
	
	/**
	 * Returns a single list object associated with the
	 * given ENCOUNTER and optionally a given TYPE. If no TYPE 
	 * is given then nothing is returned.
	 *
	 * @static
	 * @param int $encounter encounter identifier
	 * @param string $type type of list to select
	 * @param bool $active active items only flag
	 * @return $object list object
	 */
	public static function fetchEncounterItem($encounter, $type=FALSE, $active=TRUE) {
		if (!$encounter || !$type) return FALSE;
	
		$query = "SELECT lists.id FROM lists ";
		$query .= "LEFT JOIN issue_encounter ie ON ie.list_id = lists.id ";
		$query .= "WHERE ie.encounter = $encounter ";
		if ($active) $query .= "AND lists.enddate IS NULL AND lists.returndate IS NULL ";
		$query .= "AND lists.type = '$type' ";
		$query .= "ORDER BY lists.type, lists.date, lists.id ";
		$query .= "LIMIT 1";
	
		$data = sqlQuery($query);

		return new wmtList($data['id']);
	}
	
}

/**
 * Provides standardized error reporting helper functions for the 'errors'
 * database table.
 *
 * @package Dermatology
 * @subpackage Diagnosiss
 */
class wmtCategory {
	public $id;
	public $name;
	public $color;
	public $description;


	/**
	 * Constructor for the 'category' class which retrieves the requested
	 * category record from the database or creates an empty object.
	 *
	 * @param int $id category record identifier
	 * @return object instance of category class
	 */
	public function __construct($id = false) {
		if(!$id) return false;

		$query = "SELECT * FROM openemr_postcalendar_categories ";
		$query .= "WHERE pc_catid = $id";
		$results = sqlStatement($query);

		if ($data = sqlFetchArray($results)) {
			$this->id = $data['pc_catid'];
			$this->name = $data['pc_catname'];
			$this->color = $data['pc_catcolor'];
			$this->description = $data['pc_catdesc'];
		}
		else {
			throw new Exception('wmtCategory::_construct - no category record with id ('.$this->id.').');
		}
	}

	/**
	 * Returns an array of category IDs which may optionally be limited to
	 * only those records which are displayable.
	 *
	 * @static
	 * @param boolean $display include only displayable categories
	 * @return object instance of lists class
	 */
	public static function listCategories($display = TRUE) {
		$query = "SELECT pc_catid FROM openemr_postcalendar_categories ";
		if ($display) $query .= "WHERE pc_catid > 10 ";
		$query .= "ORDER BY pc_catid";

		$results = sqlStatement($query);

		$catList = array();
		while ($data = sqlFetchArray($results)) {
			$catList[] = $data['pc_catid'];
		}

		return $catList;
	}

	/**
	 * Returns an array of category data which may optionally be limited to
	 * only those records which are displayable.
	 *
	 * @static
	 * @param boolean $display include only displayable categories
	 * @return object instance of lists class
	 */
	public static function fetchCategories($display = TRUE) {
		$query = "SELECT * FROM openemr_postcalendar_categories ";
		if ($display) $query .= "WHERE pc_catid = 10 OR pc_catid > 11 ";
		$query .= "ORDER BY pc_catid";

		$results = sqlStatement($query);

		$catData = array();
		while ($data = sqlFetchArray($results)) {
			$catList[] = new wmtCategory($data['pc_catid']);
		}

		return $catList;
	}
}

/**
 */
class wmtOption {
	public $list_id;
	public $option_id;
	public $title;
	public $seq;
	public $is_default;
	public $option_value;
	public $mapping;
	public $notes;

	/**
	 * Constructor for the 'option' class which retrieves the requested
	 * list_option record from the database or creates an empty object.
	 *
	 * @param int $id option record identifier
	 * @return object instance of category class
	 */
	public function __construct($type,$id) {
		if(!$id || !$type) return false;

		$query = "SELECT * FROM list_options ";
		$query .= "WHERE option_id = '$id' AND list_id = '$type' ";
		$results = sqlStatement($query);

		if ($data = sqlFetchArray($results)) {
			$this->list_id = $data['list_id'];
			$this->option_id = $data['option_id'];
			$this->title = $data['title'];
			$this->seq = $data['seq'];
			$this->is_default = $data['is_default'];
			$this->option_value = $data['option_value'];
			$this->mapping = $data['mapping'];
			$this->notes = $data['notes'];
		}
		else {
			throw new Exception('wmtOption::_construct - no list option record with id ('.$this->id.').');
		}
	}

	/**
	 * Returns an array of category data which may optionally be limited to
	 * only those records which are displayable.
	 *
	 * @static
	 * @param boolean $display include only displayable categories
	 * @return object instance of lists class
	 */
	public static function fetchOptions($type) {
		if (! $type) return false;
		
		$query = "SELECT option_id FROM list_options ";
		$query .= "WHERE list_id = '$type' ";
		$query .= "ORDER BY seq";

		$results = sqlStatement($query);

		$optList = array();
		while ($data = sqlFetchArray($results)) {
			$optList[] = new wmtOption($type,$data['option_id']);
		}

		return $optList;
	}
}

/** 
 * Provides standardized error reporting helper functions for the 'errors'
 * database table.
 *
 * @package WMT
 * @subpackage wmtIssues
 */
class wmtIssue {
	public $id;
	public $date;
	public $type;
	public $title;
	public $begdate;
	public $enddate;
	public $returndate;
	public $occurrence;
	public $classification;
	public $referredby;
	public $extrainfo;
	public $diagnosis;
	public $activity;
	public $comments;
	public $pid;
	public $user;
	public $groupname;
	public $outcome;
	
	/**
	 * Constructor for the 'error' class which retrieves the requested 
	 * error record from the database of creates an empty object.
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public function __construct($id = FALSE) {
		if(!$id) return false;

		$query = "SELECT * FROM lists WHERE id = $id";

		$results = sqlStatement($query);
	
		if ($data = sqlFetchArray($results)) {
			$this->id = $data['id'];
			$this->date = $data['date'];
			$this->type = $data['type'];
			$this->title = $data['title'];
			$this->begdate = ($data['begdate'])? date('Y-m-d', strtotime($data['begdate'])) : '';
			$this->enddate = ($data['enddate'])? date('Y-m-d', strtotime($data['enddate'])) : '';
			$this->returndate = ($data['returndate'])? date('Y-m-d', strtotime($data['enddate'])) : '';
			$this->occurrence = $data['occurrence'];
			$this->classification = $data['classification'];
			$this->referredby = $data['refferredby'];
			$this->extrainfo = $data['extrainfo'];
			$this->diagnosis = $data['diagnosis'];
			$this->activity = $data['activity'];
			$this->comments = $data['comments'];
			$this->pid = $data['pid'];
			$this->user = $data['user'];
			$this->groupname = $data['groupname'];
			$this->outcome = $data['outcome'];
		}
		else {
			throw new Exception('wmtIssue::_construct - no issue record with id ('.$id.').');
		}
	}	

	/**
	 * Inserts data from an error object into the database.
	 * 
	 * @static
	 * @param Errors $iderror_object
	 * @return null
	 */
	public static function insert(wmtIssue $object) {
		if($object->id) {
			throw new Exception("wmtIssue::insert - object already contains identifier");
		}

		// add generic diagnosis record
		$begdate = ($object->begdate) ? "'$object->begdate'" : "NULL";
		$enddate = ($object->enddate) ? "'$object->enddate'" : "NULL";
		$returndate = ($object->returndate) ? "'$object->returndate'" : "NULL";
		
		$title = ($object->title)? $object->title : 'ICD9:'.$object->diagnosis;
		
		$object->id = sqlInsert("INSERT INTO lists SET " .
			"date = NOW(), " .
			"type = 'medical_problem', " .
			"title = '$title', " .
			"begdate = $begdate, " .
			"enddate = $enddate, " .
			"returndate = $returndate, " .
			"occurrence = '$object->occurrence', " .
			"classification = '$object->classification', " .
			"referredby = '$object->referredby', " .
			"extrainfo = '$object->extrainfo', " .
			"diagnosis = '$object->diagnosis', " .
			"activity = '$object->activity', " . 
			"comments = '$object->comments', " .
			"pid = '$object->pid', " .
			"user = '".$_SESSION['authUser']."', " .
			"groupname = '".$_SESSION['authProvider']."', " .
			"outcome = '$object->outcome'");
		
		return $object->id;
	}

	/**
	 * Inserts data from an error object into the database.
	 * 
	 * @static
	 * @param Errors $iderror_object
	 * @return null
	 */
	public function update() {
		// update generic diagnosis record
		$begdate = ($this->begdate) ? "'$this->begdate'" : "NULL";
		$enddate = ($this->enddate) ? "'$this->enddate'" : "NULL";
		$returndate = ($this->returndate) ? "'$this->returndate'" : "NULL";
		
		$title = ($this->title)? $this->title : 'ICD9:'.$this->diagnosis;
		
		sqlInsert("UPDATE lists SET " .
			"title = '$title', " .
			"begdate = $begdate, " .
			"enddate = $enddate, " .
			"returndate = $returndate, " .
			"occurrence = '$this->occurrence', " .
			"classification = '$this->classification', " .
			"referredby = '$this->referredby', " .
			"extrainfo = '$this->extrainfo', " .
			"diagnosis = '$this->diagnosis', " .
			"activity = '$this->activity', " . 
			"comments = '$this->comments', " .
			"pid = '$this->pid', " .
			"user = '".$_SESSION['authUser']."', " .
			"groupname = '".$_SESSION['authProvider']."', " .
			"outcome = '$this->outcome' " .
			"WHERE id = $this->id ");
		
		return;
	}

	/**
	 * Returns a list of issues identifiers associated with the
	 * given PID and optionally a given TYPE. If no TYPE is given
	 * then all issues for the PID are returned.
	 * 
	 * @static
	 * @param int $pid patient identifier
	 * @param string $type type of issue to select
	 * @return array $issList list of selected issue identifiers
	 */
	public static function listPidIssues($pid, $type=FALSE) {
		if (!$pid) return FALSE;

		$query = "SELECT * FROM lists ";
		$query .= "WHERE pid = $pid AND enddate IS NULL AND returndate IS NULL ";
		if ($type) $query = "AND type = '$type' ";
		$query .= "ORDER BY type, date, id";

		$results = sqlStatement($query);
	
		$isuList = array();
		while ($data = sqlFetchArray($results)) {
			$isuList[] = $data['id'];
		}
		
		return $isuList;
	}

	/**
	 * Returns an array issue objects associated with the
	 * given PID and optionally a given TYPE. If no TYPE is given
	 * then all issues for the PID are returned.
	 * 
	 * @static
	 * @param int $pid patient identifier
	 * @param string $type type of issue to select
	 * @return array $issList list of selected issue identifiers
	 */
	public static function fetchPidIssues($pid, $type=FALSE) {
		if (!$pid) return FALSE;

		$query = "SELECT * FROM lists ";
		$query .= "WHERE pid = $pid AND enddate IS NULL AND returndate IS NULL ";
		if ($type) $query = "AND type = '$type' ";
		$query .= "ORDER BY type, date, id";

		$results = sqlStatement($query);
	
		$isuList = array();
		while ($data = sqlFetchArray($results)) {
			$isuList[] = new wmtDiagnosis($data['id']);
		}
		
		return $isuList;
	}
	
	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function linkEncounter($pid, $encounter, $issue) {
		if (!$pid || !$encounter || !issue) {
			throw new Exception('wmtIssue::linkEncounter - missing required data elements');
		}

		// remove old links
		sqlStatement("DELETE FROM issue_encounter WHERE " .
  			"pid = '$pid' AND encounter = '$encounter' AND list_id = '$issue' ");
		
		// add new link
		$query = "INSERT INTO issue_encounter SET ";
		$query .= "pid = '$pid', list_id = '$issue', encounter = '$encounter' ";
	    sqlStatement ($query);
		
		return;
	}

}
	
/** 
 * Provides standardized error reporting helper functions for the 'errors'
 * database table.
 *
 * @package Dermatology
 * @subpackage Diagnosis
 */
class wmtDiagnosis extends wmtIssue {
	public $dx_id;
	public $dx_date;
	public $dx_pid;
	public $dx_list_id;
	public $dx_form_name;
	public $dx_form_id;
	public $dx_form_title;
	
	/**
	 * Constructor for the 'error' class which retrieves the requested 
	 * error record from the database of creates an empty object.
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public function __construct($id = false) {
		if(!$id) return false;

		// get standard part of the issue
		try {
			parent::__construct($id);
		}
		catch (Exception $e) {
			// should log this as an error
			$this->id = NULL;
		}
				
		// try and retrieve extended issue (with diagnosis)
		$query = "SELECT * FROM form_derm_dx_issue WHERE list_id = $id";
		$results = sqlStatement($query);

		// check for child data
		if ($data = sqlFetchArray($results)) {
			if (!$this->id) {
				// found child with no parent, get rid of child
				sqlStatement("DELETE FROM form_derm_dx_issue WHERE id = ".$data['id']);
			}
			else {
				// add child data to object
				$this->dx_id = $data['id'];
				$this->dx_date = $data['date'];
				$this->dx_pid = $data['pid'];
				$this->dx_list_id = $data['list_id'];
				$this->dx_form_name = $data['form_name'];
				$this->dx_form_id = $data['form_id'];
				$this->dx_form_title = $data['form_title'];
			}
		}
	}
		
	/**
	 * Inserts data from an error object into the database.
	 * 
	 * @static
	 * @param Errors $iderror_object
	 * @return null
	 */
	public static function insert(wmtDiagnosis $object) {
		if($object->id) {
			throw new Exception("wmtDiagnosis::insert - object already contains identifier");
		}

		// insert parent record first
		$parent_id = parent::insert($object);
		
		// add generic diagnosis record
		$enc_date = ($object->date) ? "'$object->date'" : "NULL";

		$object->id = sqlInsert("INSERT INTO form_derm_dx_issue SET " .
			"date = '$enc_date', " .
			"pid = '$object->pid', " .
			"list_id = '$parent_id', " .
			"form_name = '$object->dx_form_name', " .
			"form_title = '$object->dx_form_title'");
		
		return $parent_id;
	}

	/**
	 * Delete diagnosis record from the database and unlink.
	 * 
	 * @static
	 * @param Errors $iderror_object
	 * @return null
	 */
	public static function delete($id) {
		if(!$id) {
			throw new Exception("wmtDiagnosis::delete - no identifier provided");
		}

		// insert parent record first
		$parent_id = parent::insert($object);
		
		// add generic diagnosis record
		$enc_date = ($object->date) ? "'$object->date'" : "NULL";

		$object->id = sqlInsert("INSERT INTO form_derm_dx_issue SET " .
			"date = '$enc_date', " .
			"pid = '$object->pid', " .
			"list_id = '$parent_id', " .
			"form_name = '$object->dx_form_name', " .
			"form_title = '$object->dx_form_title'");
		
		return $parent_id;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function linkSingle($pid, $encounter, $issue) {
		if (!$pid || !$encounter || !issue) {
			throw new Exception('wmtDiagnosis::linkSingle - missing required data elements');
		}

		// remove old links
		sqlStatement("DELETE FROM issue_encounter WHERE " .
  			"pid = '$pid' AND encounter = '$encounter' AND list_id = '$issue' ");
		
		// add new link
		$query = "INSERT INTO issue_encounter SET ";
		$query .= "pid = '$pid', list_id = '$issue', encounter = '$encounter' ";
	    sqlStatement ($query);
		
		return;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function linkDiagnosis($pid, $encounter, $issues) {
		if (!$pid || !$encounter || !is_array($issues)) {
			throw new Exception('wmtDiagnosis::linkDiagnosiss - missing required data elements');
		}

		// remove old links
		sqlStatement("DELETE FROM issue_encounter WHERE " .
  			"pid = '$pid' AND encounter = '$encounter'");
		
		// add new links
		foreach ($issues as $issue) {
			$query = "INSERT INTO issue_encounter SET ";
			$query .= "pid = '$pid', list_id = '$issue', encounter = '$encounter' ";
		    sqlStatement ($query);
		}
		
		return;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function getDiagnosis($pid) {
		if (!$pid) return FALSE;

		$query = "SELECT l.id, ie.encounter FROM issue_encounter ie ";
		$query .= "LEFT JOIN lists l ON ie.list_id = l.id ";
		$query .= "WHERE ie.pid = $pid AND l.enddate IS NULL AND l.returndate IS NULL ";
		$query .= "ORDER BY l.date, l.id";

		$results = sqlStatement($query);
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = array('id' => $data['id'], 'encounter' => $data['encounter']);
		}
		
		return $txList;
	}
	
	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public function getTxEncounter($encounter) {
		if (!$encounter) {
			throw new Exception('wmtDiagnosis::getTxEncounter - no encounter identifier provided');
		}

		$query = "SELECT l.id FROM issue_encounter ie ";
		$query .= "LEFT JOIN lists l ON ie.list_id = l.id ";
		$query .= "WHERE ie.pid = $pid AND l.enddate IS NULL AND l.returndate IS NULL ";
		$query .= "AND ie.encounter = '$this->encounter' ";
		$query .= "ORDER BY l.date, l.id";

		$results = sqlStatement($query);
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = new wmtDiagnosis($data['id']);
		}
		
		return $txList;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public function getTxForm($form_id) {
		if (!$form_id) {
			throw new Exception('wmtDiagnosis::getTxForm - no form identifier provided');
		}

		$query = "SELECT dx.list_id FROM form_derm_dx_issue dx ";
		$query .= "LEFT JOIN lists l ON dx.list_id = l.id ";
		$query .= "WHERE dx.form_id = $form_id AND l.enddate IS NULL AND l.returndate IS NULL ";
		$query .= "ORDER BY l.date, l.id";

		$results = sqlStatement($query);
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = $data['id'];
		}
		
		return $txList;
	}
}
		
/** 
 * Provides specialized class for screening encounters by
 * extending the base class and including additional data.
 *
 * @package WMT
 * @subpackage wmtScreening
 */
class XXXXwmtScreening extends wmtEncounter {
	public $id; // screening specific record
	public $parent_id; // base encounter record
	public $encounter; // encounter number
		
	public $date;
	public $pid;
	public $user;
	public $groupname;
	public $authorized;		
	public $activity;
	public $status;
	public $priority;

	public $referral;
	public $previous_flag;
	public $previous_when;
	public $program;
	public $available_date;
	public $got_w2_flag;
	public $has_w2_flag;
	public $mental_flag;
	public $mental_notes;
	public $suicide_flag;
	public $suicide_notes;
	public $pregnant_flag;
	public $pregnant_notes;
	public $physician_flag;
	public $physician_name;
	public $meds_flag;
	public $meds_notes;
	public $drug1;
	public $drug2;
	public $drug3;
	public $drug4;
	public $drug5;
	public $drug6;
	public $drug7;
	public $drug8;
	public $tx_flag;
	public $tx_notes;
	public $tx_where;
	public $sober_flag;
	public $sober_notes;
	public $detox_flag;
	public $rent_flag;
	public $felony_flag;
	public $felony_time;
	public $felony_notes;
	public $warrant_flag;
	public $sex_flag;
	public $sex_check;
	public $comments;
	public $interviewer;
	public $interviewed;
	public $disposition;
	public $staff_notes;
		
	/**
	 * Constructor for the 'encounter' class which retrieves the requested 
	 * record from the database or creates an empty object.
	 * 
	 * @param int $parent_id base record identifier
	 * @return object instance of class
	 */
	public function __construct($parent_id = false) {
		if(!$parent_id) return false;

		// get standard part of the issue
		try {
			parent::__construct($parent_id);
		}
		catch (Exception $e) {
			// should log this as an error
			$this->id = NULL;
		}
				
		// try and retrieve extended encounter screening data
		$query = "SELECT * FROM form_hh_screening WHERE parent_id = $parent_id";
		$results = sqlStatement($query);

		// check for child data
		if ($data = sqlFetchArray($results)) {
			if (!$this->id) {
				// found child with no parent, get rid of child
				sqlStatement("DELETE FROM form_hh_screening WHERE id = ".$data['id']);
			}
			else {
				// load everything returned into object
				foreach ($data as $key => $value) {
					$this->$key = $value;
				}
			}
		}
	}
		
	/**
	 * Inserts data from an object into the database by first loading
	 * the base data then adding the additional data and linking.
	 * 
	 * @static
	 * @param wmtScreening $object
	 * @return $id
	 */
	public static function insert(wmtScreening $object) {
		if($object->id) {
			throw new Exception ("wmtScreening::insert - object already contains identifier");
		}

		// create basic encounter
		$object->encounter = generate_id(); // in sql.inc
		
		// validate dates
		$object->date = ($object->date) ? "$object->date" : "date('Y-m-d)";
		$object->onset_date = ($object->onset_date) ? "$object->onset_date" : "$object->date";

		// store standard part of encounter
		$parent_id = parent::insert($object);

		// build screening part of encounter
		$query = "INSERT INTO form_hh_screening SET ";
		$query .= "parent_id = '$parent_id', ";
		$query .= "encounter = '$object->encounter', ";
		
		$query .= "date = '$object->date', ";
		$query .= "pid = '$object->pid', ";
		$query .= "user = '".$_SESSION['authUser']."', ";
		$query .= "groupname = '".$_SESSION['authProvider']."', ";
		$query .= "authorized = '".$_SESSION['userauthorized']."', ";		
		$query .= "activity = 1, ";
		$query .= "status = '$object->status', ";
		$query .= "priority = '$object->priority', ";

		$query .= "referral = '$object->referral', ";
		$query .= "previous_flag = '$object->previous_flag', ";
		$query .= "previous_when = '$object->previous_when', ";
		$query .= "program = '$object->program', ";
		$query .= "available_date = '$object->available_date', ";
		$query .= "got_w2_flag = '$object->got_w2_flag', ";
		$query .= "has_w2_flag = '$object->has_w2_flag', ";
		$query .= "mental_flag = '$object->mental_flag', ";
		$query .= "mental_notes = '$object->mental_notes', ";
		$query .= "suicide_flag = '$object->suicide_flag', ";
		$query .= "suicide_notes = '$object->suicide_notes', ";
		$query .= "pregnant_flag = '$object->pregnant_flag', ";
		$query .= "pregnant_notes = '$object->pregnant_notes', ";
		$query .= "physician_flag = '$object->physician_flag', ";
		$query .= "physician_name = '$object->physician_name', ";
		$query .= "meds_flag = '$object->meds_flag', ";
		$query .= "meds_notes = '$object->meds_notes', ";
		$query .= "drug1 = '$object->drug1', ";
		$query .= "drug2 = '$object->drug2', ";
		$query .= "drug3 = '$object->drug3', ";
		$query .= "drug4 = '$object->drug4', ";
		$query .= "drug5 = '$object->drug5', ";
		$query .= "drug6 = '$object->drug6', ";
		$query .= "drug7 = '$object->drug7', ";
		$query .= "drug8 = '$object->drug8', ";
		$query .= "tx_flag = '$object->tx_flag', ";
		$query .= "tx_notes = '$object->tx_notes', ";
		$query .= "tx_where = '$object->tx_where', ";
		$query .= "sober_flag = '$object->sober_flag', ";
		$query .= "sober_notes = '$object->sober_notes', ";
		$query .= "detox_flag = '$object->detox_flag', ";
		$query .= "rent_flag = '$object->rent_flag', ";
		$query .= "felony_flag = '$object->felony_flag', ";
		$query .= "felony_time = '$object->felony_time', ";
		$query .= "felony_notes = '$object->felony_notes', ";
		$query .= "warrant_flag = '$object->warrant_flag', ";
		$query .= "sex_flag = '$object->sex_flag', ";
		$query .= "sex_check = '$object->sex_check', ";
		$query .= "comments = '$object->comments', ";
		$query .= "interviewer = '$object->interviewer', ";
		$query .= "interviewed = '$object->interviewed', ";
		$query .= "disposition = '$object->disposition', ";
		$query .= "staff_notes = '$object->staff_notes'";
		
		// do the insert
		$object->id = sqlInsert($query);

		// always work with the 'root' record identifier
		return $parent_id;
	}

	/**
	 * Inserts data from the current object into the base encounter and the
	 * extended data table for the screening process.
	 * 
	 * @static
	 * @return null
	 */
	public function update() {
		if(!$this->id) {
			throw new Exception ("wmtScreening::update - object contains no identifier");
		}
		
		// get facility name from id
		$fres = sqlQuery("SELECT name FROM facility WHERE id = $this->facility_id");
		$facility = $fres['name'];

		// make sure the dates are vaild
		$enc_date = ($this->date) ? "$this->date" : "date('Y-m-d')";
		$onset_date = ($this->onset_date) ? "$this->onset_date" : "$enc_date";

		// update standard part of encounter
		parent::update();

		// build screening part of encounter
		$query = "UPDATE form_hh_screening SET ";
		$query .= "date = '$this->date', ";
		$query .= "pid = '$this->pid', ";
		$query .= "user = '".$_SESSION['authUser']."', ";
		$query .= "groupname = '".$_SESSION['authProvider']."', ";
		$query .= "authorized = '".$_SESSION['userauthorized']."', ";		
		$query .= "activity = 1, ";
		$query .= "status = '$this->status', ";
		$query .= "priority = '$this->priority', ";

		$query .= "referral = '$this->referral', ";
		$query .= "previous_flag = '$this->previous_flag', ";
		$query .= "previous_when = '$this->previous_when', ";
		$query .= "program = '$this->program', ";
		$query .= "available_date = '$this->available_date', ";
		$query .= "got_w2_flag = '$this->got_w2_flag', ";
		$query .= "has_w2_flag = '$this->has_w2_flag', ";
		$query .= "mental_flag = '$this->mental_flag', ";
		$query .= "mental_notes = '$this->mental_notes', ";
		$query .= "suicide_flag = '$this->suicide_flag', ";
		$query .= "suicide_notes = '$this->suicide_notes', ";
		$query .= "pregnant_flag = '$this->pregnant_flag', ";
		$query .= "pregnant_notes = '$this->pregnant_notes', ";
		$query .= "physician_flag = '$this->physician_flag', ";
		$query .= "physician_name = '$this->physician_name', ";
		$query .= "meds_flag = '$this->meds_flag', ";
		$query .= "meds_notes = '$this->meds_notes', ";
		$query .= "drug1 = '$this->drug1', ";
		$query .= "drug2 = '$this->drug2', ";
		$query .= "drug3 = '$this->drug3', ";
		$query .= "drug4 = '$this->drug4', ";
		$query .= "drug5 = '$this->drug5', ";
		$query .= "drug6 = '$this->drug6', ";
		$query .= "drug7 = '$this->drug7', ";
		$query .= "drug8 = '$this->drug8', ";
		$query .= "tx_flag = '$this->tx_flag', ";
		$query .= "tx_notes = '$this->tx_notes', ";
		$query .= "tx_where = '$this->tx_where', ";
		$query .= "sober_flag = '$this->sober_flag', ";
		$query .= "sober_notes = '$this->sober_notes', ";
		$query .= "detox_flag = '$this->detox_flag', ";
		$query .= "rent_flag = '$this->rent_flag', ";
		$query .= "felony_flag = '$this->felony_flag', ";
		$query .= "felony_time = '$this->felony_time', ";
		$query .= "felony_notes = '$this->felony_notes', ";
		$query .= "warrant_flag = '$this->warrant_flag', ";
		$query .= "sex_flag = '$this->sex_flag', ";
		$query .= "sex_check = '$this->sex_check', ";
		$query .= "comments = '$this->comments', ";
		$query .= "interviewer = '$this->interviewer', ";
		$query .= "interviewed = '$this->interviewed', ";
		$query .= "disposition = '$this->disposition', ";
		$query .= "staff_notes = '$this->staff_notes' ";
		$query .= "WHERE id = $this->id";
		
		// do the update
		sqlInsert($query);
		
		return;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function listPidEncounters($pid) {
		if (!$pid) return FALSE;

		$query = "SELECT fe.encounter, fe.id FROM form_encounter fe ";
		$query .= "LEFT JOIN issue_encounter ie ON fe.id = ie.list_id ";
		$query .= "LEFT JOIN lists l ON ie.list_id = l.id ";
		$query .= "WHERE fe.pid = $pid AND l.enddate IS NULL ";
		$query .= "ORDER BY fe.date, fe.encounter";

		$results = sqlStatement($query);
	
		$txList = array();
		while ($data = sqlFetchArray($results)) {
			$txList[] = array('id' => $data['id'], 'encounter' => $data['encounter']);
		}
		
		return $txList;
	}

	/**
	 * 
	 * @param int $id lists record identifier
	 * @return object instance of lists class
	 */
	public static function getEncounter($encounter) {
		if (!$encounter) return FALSE;

		$query = "SELECT id FROM form_encounter WHERE encounter = '$encounter' ";
		$results = sqlStatement($query);
		$data = sqlFetchArray($results);
		
		return new wmtEncounter($data['id']);
	}
}




/** 
 * Provides standardized error reporting helper functions for the 'errors'
 * database table.
 *
 * @package Dermatology
 * @subpackage Treatment
 */
class wmtTreatment {
	public $id;
	public $date;
	public $pid;
	public $user;
	public $groupname;
	public $authorized;
	public $activity;
	public $status;
	public $priority;

	public static function findPrevious($form_name, $pid) {
		if (!$form_name) {
			throw new Exception("wmtTreatment::findPrevious - no form name provided");
		}
		if (!$pid) {
			throw new Exception("wmtTreatment::findPrevious - no patient identifier provided");
		}

		$id = '';
		try {
			$query = "SELECT id FROM form_".$form_name." WHERE pid = '".$pid."' ORDER BY date DESC LIMIT 1";
			$results = sqlStatement($query);
			if ($data = sqlFetchArray($results)) {
				$id = $data['id'];
			}
		}
		catch(Exception $e) {
			$id = '';
		}
		
		return $id;
	}
}
	
?>
