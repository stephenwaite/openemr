<?php

if (file_exists("$srcdir/wmt/wmtstandard.inc")) {
	// if standard WMT install use default library
	require_once("$srcdir/wmt/wmtstandard.inc");
}
else {
	// if non-standard WMT define appropriate functions
	function ListSel($thisField, $thisList) {
		$rlist= sqlStatement("SELECT * FROM list_options WHERE " .
                    "list_id = '".$thisList."' ORDER BY seq, title");
		echo "<option value=''";
		if(!$thisField) echo " selected='selected'";
		echo ">&nbsp;</option>";
		while ($rrow= sqlFetchArray($rlist)) {
			echo "<option value='" . $rrow['option_id'] . "'";
			if($thisField == $rrow['option_id']) echo " selected='selected'";
			echo ">" . $rrow['title'];
			echo "</option>";
		}
	}

	function ListLook($thisData, $thisList) {
		if($thisList == 'occurrence') {
			if(!$thisData || $thisData == '') return 'Unknown or N/A'; 
		}
		if(!$thisData || $thisData == '') return ''; 
		$fres=sqlStatement("SELECT * FROM list_options WHERE list_id='".$thisList."' AND option_id='".$thisData."'");
		if($fres) {
			$rret=sqlFetchArray($fres);
			$dispValue= $rret{'title'};
			if($thisList == 'occurrence' && $dispValue == '') {
				$dispValue = 'Unknown or N/A';
    		}
		}
  		else {
   			$dispValue= '*Not Found*';
		}
  		return $dispValue;
	}

	function GetList($pid, $type, $cols='*') {
		$sql = "SELECT $cols FROM lists WHERE pid='$pid' AND type='$type' ORDER BY begdate";

		$all=array();
		$res = sqlStatement($sql);
		$iter=0;
		while($row = sqlFetchArray($res)) {
		  	$all[$iter] = $row;
			$iter++;
		}
		return $all;
	}

	function GetCodeDescription($type='ICD9', $code) {
		$ret = "Invalid Key [$type] - No Description Found";
		$sql = "SELECT ct_id FROM code_types WHERE ct_key='$type'";
		$res = sqlStatement($sql);
		$row = sqlFetchArray($res);
		$key = $row{'ct_id'};
		if($key == '' || !$key) return $ret;

		$sql = "SELECT code_text, code_text_short FROM codes WHERE code_type='$key' AND code='$code'";
		$res = sqlStatement($sql);
		$row = sqlFetchArray($res);
		$ret = trim($row{'code_text'});
		return $ret;
	}

	function DiagSel($thisField) {
		$rlist= sqlStatement("SELECT * FROM codes WHERE code_type='2' ".
           "AND active='1' ORDER BY code");
		echo "<option value=''";
		if(!$thisField) echo " selected='selected'";
		echo ">&nbsp;</option>";
		while ($rrow= sqlFetchArray($rlist)) {
			echo "<option value='" . $rrow['code']. "'";
			if($thisField == $rrow['code']) echo " selected='selected'";
			echo ">" . $rrow['code'] . " - " . $rrow['code_text'];
			echo "</option>";
		}
	}

	function ICD9DiagSel($thisField) {
		$rlist= sqlStatement("SELECT * FROM codes WHERE code_type='2' ".
           "AND active='1' ORDER BY code");
		echo "<option value=''";
		if(!$thisField) echo " selected='selected'";
		echo ">&nbsp;</option>";
		while ($rrow= sqlFetchArray($rlist)) {
			echo "<option value='ICD9: " . $rrow['code']. "'";
			if($thisField == $rrow['code']) echo " selected='selected'";
			echo '>' . $rrow['code'] . ' - ' . $rrow['code_text'];
			echo '</option>';
		}
	}

	function DiagLook($thisData) {
		if(!$thisData || $thisData == '') {
			return ''; 
		}
	  	$fres= sqlStatement("SELECT * FROM codes WHERE code_type='2' ".
           "AND active='1' AND code='".$thisData."'");
		$rret=sqlFetchArray($fres);
		$dispValue= $rret{'code'}.'-'.$rret{'code_text'};
		return $dispValue;
	}
	
	function DiagDescLook($thisData) {
		if(!$thisData || $thisData == '') {
			return ''; 
		}
		$fres= sqlStatement("SELECT code_text FROM codes WHERE code_type='2' ".
           "AND active='1' AND code='".$thisData."'");
		$rret=sqlFetchArray($fres);
		$dispValue= $rret{'code_text'};
		return $dispValue;
	}

	function UserSelect($thisField) {
		$rlist= sqlStatement("SELECT * FROM users WHERE authorized=1 AND " .
           "active=1 ORDER BY lname");
		echo "<option value=''";
		if(!$thisField) echo " selected='selected'";
		echo ">&nbsp;</option>";
		while ($rrow= sqlFetchArray($rlist)) {
			echo "<option value='" . $rrow['username'] . "'";
			if($thisField == $rrow['username']) echo " selected='selected'";
			echo ">" . $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
			echo "</option>";
		}
	}

	function UserLook($thisField) {
		if(!$thisField) return '';
		$ret = '';
		$rlist= sqlStatement("SELECT * FROM users WHERE username='" .
           $thisField."'");
		$rrow= sqlFetchArray($rlist);
		if($rrow) {
			$ret = $rrow['lname'].', '.$rrow['fname'].' '.$rrow['mname'];
		}
		return $ret;
	}

	function FacilitySelect($thisField) {
		$flist= sqlStatement("SELECT id, name FROM facility WHERE " .
          "service_location != 0 ORDER BY name");
		while ($frow= sqlFetchArray($flist)) {
			echo "<option value='" . $frow['id'] . "'";
			if($thisField == $frow['id']) echo " selected='selected'";
			echo ">" . $frow['name'];
			echo "</option>\n";
		}
	}

	function FacilityLook($thisField) {
		if(!$thisField) return '';
		$flist= sqlStatement("SELECT name FROM facility WHERE id='".$thisField."'");
		$frow= sqlFetchArray($flist);
		return($frow{'name'});
	}

}

?>
