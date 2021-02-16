<?php

// Copyright (C) 2012-2016 Mark Kuperman <mkuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

function externalOrderId($id, $pprow) {
    //id - procedure order id
    //ppRow - row from procedure_providers table
    $ar = getCustomVars($pprow);
    return empty($ar['prefix'])? false: sprintf($ar['prefix'] . '%05d', $id);
}

function stripPrefix($idStr, $pprow) {
    //id - procedure order id
    //ppRow - row from procedure_providers table
    $ar = getCustomVars($pprow);
    return empty($ar['prefix'])? $idStr: str_replace($ar['prefix'], '', $idStr);
}

function getEligLabs($pid) {
    $sql = "select lab_id from lab_insurance l " .
        "join insurance_data i on l.id = i.provider " .
        "where i.type='primary' and i.pid=$pid";
    $insLabs = sqlStatement($sql);
    // there is a bug: search_array skips the first element of the array ???
    $labsAr = array('xxx');          //array of lab IDs supporting patient's insurance
    while ($lid = sqlFetchArray($insLabs))
        $labsAr[] = $lid['lab_id'];

    $sql = "select * from procedure_providers";
    $rows = sqlStatement($sql);
    $retAr = array();
    // count() = 1 means Patient without insurance
    $c = count($labsAr);
    while ($row =  sqlFetchArray($rows)) {
        if (($c = 1) OR (array_search($row['ppid'], $labsAr)) OR (ignoreInsurance($row)))
            $retAr[$row['ppid']] = $row['name'];
    }
    return $retAr;
}

function ignoreInsurance($pprow) {
    $ar = getCustomVars($pprow);
    return !empty($ar['ignoreInsurance']);
}

function getCustomVars($pprow){
    $ar = array();
    parse_str($pprow['notes'],$ar);
    return $ar;
}

function getLabInsuranceCode($labId, $insId) {
    $sql = "select lab_code from lab_insurance " .
        "where id = ? and lab_id = ?";
    $res = sqlQuery($sql, array($insId, $labId));
    return $res['lab_code'];
}

?>
