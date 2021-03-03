<?php

// Copyright (C) 2012-2016 Mark Kuperman <mkuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//require_once('../globals.php');

use OpenEMR\Billing\Claim;

require_once (dirname(__FILE__) . '/../../custom/code_types.inc.php');
require_once ('labCustom.inc.php');

Class ProcedureOrder extends Claim {

    var $procOrder;     //row from procedure_order table
    var $lab;           //row from provider table
    var $labUser;       //row from user table for the lab
    var $provider;      //row from user table for ordering provider
    var $codes;         //array of rows from procedure_order_code table

    function ProcedureOrder($orderId) {
        $sql = "select * from procedure_order where procedure_order_id = $orderId";
        $this->procOrder = sqlQuery($sql);
        $pid = $this->procOrder['patient_id'];
        $encounterId = $this->procOrder['encounter_id'];
        parent::__construct($pid, $encounterId);

        $labId = $this->procOrder['lab_id'];
        $sql = "select * from procedure_providers where ppid = $labId";
        $this->lab = sqlQuery($sql);

        $npi = $this->lab['npi'];
        $sql = "select * from users where abook_type = 'ord_lab' and npi = '$npi'";
        $this->labUser = sqlQuery($sql);

        $providerId = $this->procOrder['provider_id'];
        $sql = "select * from users where id = $providerId";
        $this->provider = sqlQuery($sql);

        $sql = "SELECT * FROM procedure_order_code where procedure_order_id = $orderId" .
            " ORDER BY procedure_order_seq ASC";
        $cres = sqlStatement($sql);
        while ($crow = sqlFetchArray($cres)) {
            $this->codes[] = $crow;
        }

        $sql = "SELECT * FROM insurance_data WHERE pid = $pid AND " .
            "date <= '{$this->procOrder['date_collected']}' " .
            "ORDER BY type ASC, date DESC";
        $dres = sqlStatement($sql);
        while ($drow = sqlFetchArray($dres)) {
            if (empty($drow['provider']))
                continue;
            $ins = count($this->payers);
            $crow = sqlQuery("SELECT * FROM insurance_companies WHERE id = '{$drow['provider']}'");
            $orow = new InsuranceCompany($drow['provider']);
            $this->payers[$ins] = array();
            $this->payers[$ins]['data'] = $drow;
            $this->payers[$ins]['company'] = $crow;
            $this->payers[$ins]['object'] = $orow;
        }
    }

    function orderId() {
        return $this->procOrder['procedure_order_id'];
    }

    function labName() {
        return $this->labUser['organization'];
    }

    function labStreet() {
        return $this->labUser['street'];
    }

    function labCity() {
        return $this->labUser['city'];
    }

    function labState() {
        return $this->labUser['state'];
    }

    function labZip() {
        return $this->labUser['zip'];
    }

    function labPhone() {
        return $this->labUser['phonew1'];
    }

    function labFax() {
        return $this->labUser['fax'];
    }

    function acctAtLab() {
        return $this->lab['send_fac_id'];
    }

    function patientPubid() {
        return $this->patient_data['pubpid'];
    }

    function collected() {
        return strtotime($this->procOrder['date_collected']);
    }

    function facilityPhone() {
        return $this->facility['phone'];
    }

    function facilityFax() {
        return $this->facility['fax'];
    }

    function orderingProviderFirst() {
        return $this->provider['fname'];
    }

    function orderingProviderLast() {
        return $this->provider['lname'];
    }

    function patientDOB() {
        return $this->patient_data['DOB'];
    }

    function comments() {
        return $this->procOrder['patient_instructions'];
    }

    function clinicalHx() {
        return $this->procOrder['clinical_hx'];
    }

    function getCodes() {
        $c = array();
        foreach ($this->codes as $r) {
            $c[$r['procedure_code']] = $r['procedure_name'];
        }
        return $c;
    }

    function getDiags() {
        $d = array();
        foreach ($this->codes as $r) {
            $ds = $r['diagnoses'];
            $descAr = explode(';', lookup_code_descriptions($ds));
            $ddAr = explode(';', $ds);
            $i = 0;
            foreach ($descAr as $dt) {
                $t = explode(':', $ddAr[$i]);
                $k = $t[1];
                $d[$k] = $dt;
                $i++;
            }
        }
        return $d;
    }

    function printOrderId() {
        $r =  externalOrderId($this->orderId(), $this->lab);
        return $r ? $r : $this->orderId();
    }
}

?>
