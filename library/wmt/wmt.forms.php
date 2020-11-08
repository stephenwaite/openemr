<?php

function ListCheck($prefix, $checkList, $thisList) {
	$rlist= sqlStatement("SELECT * FROM list_options WHERE list_id = '".$thisList."' ORDER BY seq");
	
	if (is_string($checkList)) $checkList = explode('+', $checkList);
	while ($rrow= sqlFetchArray($rlist)) {
		$name = str_replace(" ", "_", $prefix."_".$rrow['option_id']);
		$name = $prefix."[]";
		echo "<span style='white-space:nowrap'><input type='checkbox' class='wmtCheck' name='".$name."' value='".$rrow['option_id']."' ";
		if (in_array($rrow['option_id'], $checkList)) echo "checked ";
		echo " />";
		echo "<label for='".$name."' class='wmtCheck' style='padding-right:10px'>".$rrow['title']."</label></span> ";
	}
}

function CheckLook($checkList, $thisList) {
	if (!$checkList || $checkList == '') return '';
	
	$rres=sqlStatement("SELECT * FROM list_options WHERE list_id='".$thisList."' ORDER BY seq");

	$dispValue = '';
	if (is_string($checkList)) $checkList = explode('+', $checkList);
	while ($rrow= sqlFetchArray($rres)) {
		if (in_array($rrow['option_id'], $checkList)) {
			if ($dispValue) $dispValue .= ", ";
			$dispValue .= "<span style='white-space:nowrap'>".$rrow['title']."</span>";
		}
	}
	
	return $dispValue;
}


