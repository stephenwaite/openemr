<?php

// Copyright (C) 2013 Mark Kuperman <mkuperman@mi-10.com>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

//require_once(dirname(__FILE__) . "../globals.php");
require_once("ProcedureOrder.class.php");


function getOrderPdf($orderId) {
    $po = new ProcedureOrder($orderId);

    $map = populateOrder($po);

    $fdf = '%FDF-1.2
1 0 obj
<</FDF<</Fields[';
    foreach ($map as $k => $v) {
        $fdf .= "<</T(" . $k . ")/V(" . $v . ")>>";
    }
    $fdf .= ']>>/Type/Catalog>>
endobj
trailer
<</Root 1 0 R>>
%%EOF';

    // create temp FDF file and pdf output
    $fdfOut = $GLOBALS['temporary_files_dir'] . "/po1-" . date("Y-m-d-Hi", time()) . ".fdf";
    $fileOut = fopen($fdfOut, "w");
    fputs($fileOut, $fdf);
    fclose($fileOut);

    $pdfForm = $GLOBALS['webserver_root'] . "/templates/transforms/requisition.pdf";
    $pdfOut = $GLOBALS['temporary_files_dir'] . "/po2-" . date("Y-m-d-Hi", time()) . ".pdf";
    exec('/usr/bin/pdftk ' . $pdfForm . ' fill_form ' . $fdfOut . ' output ' . $pdfOut . ' flatten');
   //     error_log("temp file dir is " . $GLOBALS['temporary_files_dir']);

    return file_get_contents($pdfOut);
}

function populateOrder($order) {
    $map = array();
    $orderIdStr = $order->printOrderId();
    $map['Req Number'] = $orderIdStr;
    $map['Facility Name'] = $order->facilityName();
    $map['Facility Street'] = $order->facilityStreet();
    $map['Facility CSZ'] = $order->facilityCity() .
        ", " . $order->facilityState() .
        " "  . $order->facilityZip() ;
    $map['Facility Phone'] = "Ph. " . $order->facilityPhone() .
        "   Fax." . $order->facilityFax();
    $map['Lab Name'] = $order->labName();
    $map['Lab Street'] = $order->labStreet();
    $map['Lab CSZ'] = $order->labCity() .
        ", " . $order->labState() .
        " "  . $order->labZip() ;
    $map['Lab Phone'] = "Ph. " . $order->labPhone() .
        "   Fax." . $order->labFax();
    $map['Physician Name'] = "Dr. " . $order->orderingProviderFirst() .
        " " . $order->orderingProviderLast();
    $map['Patient Sex'] = $order->patientSex();
    $map['Facility Acct-Name'] = $order->acctAtLab() . "---" . $order->facilityName();
    $map['Patient Phone'] = $order->patientPhone();
    $map['Patient Name'] = $order->patientFirstName() . " " . $order->patientLastName();
    $map['Patient Street'] = $order->patientStreet();
    $map['Patient CSZ'] = $order->patientCity() .
        ", " . $order->patientState() .
        " "  . $order->patientZip() ;
    $map['Patient ID'] = $order->patientPubid();
    $map['Patient DOB'] = $order->patientDOB();
    if ($order->payerCount() > 0) {
        $map['Pr Payer Name'] = $order->payerName(0);
        $map['Pr Payer Street'] = $order->payerStreet(0);
        $map['Pr Payer CSZ'] = $order->payerCity(0) .
            ", " . $order->payerState(0) .
            " "  . $order->payerZip(0) ;
        $map['Pr Policy'] = $order->policyNumber(0);
        $map['Pr Group'] = $order->groupNumber(0);
        $t = $order->insuredRelationship(0);
        if ($t == '18')
            $map['Pr Subscriber Rel'] = "Self";
        else {
            $map['Pr Subscriber Rel'] = $order->insuredFirstName(0) .
                " " . $order->insuredLastName(0);
            $map['Pr Subscriber DOB'] = $order->insuredDOB(0);
        }
    }
    /*
    FieldName: Sec Payer Name
    FieldName: Sec Payer Street
    FieldName: Sec Payer CSZ
    FieldName: Sec Policy
    FieldName: Sec Group
    FieldName: Sec Subscriber Rel
    FieldName: Sec Subscriber DOB
     */
    $map['Collection Time'] = date("m/d/Y H:i",$order->collected());
    $map['Comments'] = $order->comments();
    $map['Clinical_Hx'] = $order->clinical_Hx();

    $cr = '';
    foreach($order->getCodes() as $c => $t) {
        $map['Tests'] .= $cr . $c . "-->" . $t;
        $cr = "\n";
    }

    $cr = '';
    foreach($order->getDiags() as $c => $t) {
        $map['Diags'] .= $cr . $c . "-->" . $t;
        $cr = "\n";
    }

    $tmp = date("m/d/Y H:i",$order->collected()) .
        "\n" . $order->acctAtLab() . " " . $order->providerFirstName() . " " . $order->providerLastName() .
        "\n" . $order->patientFirstName() . " " . $order->patientLastName() .
        "\n" . $orderIdStr;
    $map['Label.0.0'] = $tmp;
    $map['Label.0.1'] = $tmp;
    $map['Label.0.2'] = $tmp;
    $map['Label.1.0'] = $tmp;
    $map['Label.1.1'] = $tmp;
    $map['Label.1.2'] = $tmp;
    return $map;
}
?>
