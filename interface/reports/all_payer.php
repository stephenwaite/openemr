<?php

/**
 * Collections report
 *
 * (TLH) Added payor,provider,fixed cvs download to included selected fields
 * (TLH) Added ability to download selected invoices only or all for patient
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Terry Hill <terry@lillysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @author    Sherwin Gaddis <sherwingaddis@gmail.com>
 * @copyright Copyright (c) 2006-2020 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2015 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2019 Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2019 Sherwin Gaddis <sherwingaddis@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("../../library/patient.inc");
require_once "$srcdir/options.inc.php";

use OpenEMR\Billing\InvoiceSummary;
use OpenEMR\Billing\SLEOB;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

$alertmsg = '';
$bgcolor = "#aaaaaa";
$export_patient_count = 0;
$export_dollars = 0;

$form_date      = (isset($_POST['form_date'])) ? DateToYYYYMMDD($_POST['form_date']) : "";
//$form_date = "2020-07-06";
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : "";
//$form_to_date = "2020-07-06";


function endInsurance($insrow)
{
    
    global $charges, $payments;

    $insurance = (new InsuranceService)->getOne($insrow['pid'], "primary");
    $ins_id = $insurance['provider'];
    
    $code_query = "SELECT code, encounter FROM billing WHERE encounter = ? and code_type = 'CPT4'";
    $temp = sqlStatement($code_query, array($insrow['encounter']));
    while ($temp_array = sqlfetcharray($temp)){
        if ($temp_array['code'] == "NP150") {
            $charges['selfpay'] += 150;
            continue;
        } 
    }

    $pay_query = "SELECT sequence_no, payer_type, pay_amount FROM ar_activity WHERE pid = ? AND encounter = ? AND pay_amount != 0";
    $pay_state = sqlStatement($pay_query, array($insrow['pid'], $insrow['encounter']));

    if (in_array($ins_id, array('4', '8'))) {
        $charges['medicare'] += $insrow['charges'];
        error_log("medicare charges are now " . $charges['medicare']);
        while ($pay_array = sqlFetchArray($pay_state)){
            //var_dump($pay_array);
            if ($pay_array['payer_type'] == "1") {
                $payments['medicare'] += $pay_array['pay_amount'];
                error_log("medicare payments are now " . $payments['medicare'] . " for encounter " . $insrow['encounter']);
            } else {
                $payments['selfpay'] += $pay_array['pay_amount'];
                error_log("selfpay payments are now " . $payments['selfpay'] . " for encounter " . $insrow['encounter']);
            }
            //error_log("encounter $enc");
        }
    } elseif (in_array($ins_id, array('13'))) {
        $charges['medicaid'] += $insrow['charges'];
        error_log("medicaid charges are now " . $charges['medicaid']);
        while ($pay_array = sqlFetchArray($pay_state)){
            //var_dump($pay_array);
            if ($pay_array['payer_type'] == "1") {
                $payments['medicaid'] += $pay_array['pay_amount'];
                error_log("medicaid payments are now " . $payments['medicaid'] . " for encounter " . $insrow['encounter']);
            } else {
                $payments['selfpay'] += $pay_array['pay_amount'];
                error_log("selfpay payments are now " . $payments['selfpay'] . " for encounter " . $insrow['encounter']);
            }
            //error_log("encounter $enc");
        }
    } elseif(in_array($ins_id, array('20', '86', '90'))) {
        $charges['tricare'] += $insrow['charges'];
        error_log("tricare charges are now " . $charges['tricare']);
        while ($pay_array = sqlFetchArray($pay_state)){
            //var_dump($pay_array);
            if ($pay_array['payer_type'] == "1") {
                $payments['tricare'] += $pay_array['pay_amount'];
                error_log("tricare payments are now " . $payments['tricare'] . " for encounter " . $insrow['encounter']);
            } else {
                $payments['selfpay'] += $pay_array['pay_amount'];
                error_log("selfpay payments are now " . $payments['selfpay'] . " for encounter " . $insrow['encounter']);
            }
            //error_log("encounter $enc");
        }
    } else {
            $charges['commercial'] += $insrow['charges'];
            error_log("commercial charges are now " . $charges['commercial']);while ($pay_array = sqlFetchArray($pay_state)){
                //var_dump($pay_array);
                if ($pay_array['payer_type'] == "1") {
                    $payments['commercial'] += $pay_array['pay_amount'];
                    error_log("commercial payments are now " . $payments['commercial'] . " for encounter " . $insrow['encounter']);
                } else {
                    $payments['selfpay'] += $pay_array['pay_amount'];
                    error_log("selfpay payments are now " . $payments['selfpay'] . " for encounter " . $insrow['encounter']);
                }
                //error_log("encounter $enc");
            }
    }
}

function bucks($amount)
{
    if ($amount) {
        return oeFormatMoney($amount); // was printf("%.2f", $amount);
    }
}




function getInsName($payerid)
{
    $tmp = sqlQuery("SELECT name FROM insurance_companies WHERE id = ? ", array($payerid));
    return $tmp['name'];
}

// In the case of CSV export only, a download will be forced.

    ?>
<html>
<head>

    <title><?php echo xlt('All payer revenue report')?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

    <style type="text/css" >
        @media print {
            #report_parameters {
                visibility: hidden;
                display: none;
            }
            #report_parameters_daterange {
                visibility: visible;
                display: inline;
            }
            #report_results {
               margin-top: 30px;
            }
        }

        /* specifically exclude some from the screen */
        @media screen {
            #report_parameters_daterange {
                visibility: hidden;
                display: none;
            }
        }
    </style>

    <script>
        function reSubmit() {
            $("#form_refresh").attr("value","true");
            $("#form_csvexport").val("");
            $("#theform").submit();
        }
        // open dialog to edit an invoice w/o opening encounter.
        function editInvoice(e, id) {
            e.stopPropagation();
            let url = './../billing/sl_eob_invoice.php?id=' + encodeURIComponent(id);
            dlgopen(url,'','modal-lg',750,false,'', {
                onClosed: 'reSubmit'
            });
        }

        $(function () {
            oeFixedHeaderSetup(document.getElementById('mymaintable'));
            var win = top.printLogSetup ? top : opener.top;
            win.printLogSetup(document.getElementById('printbutton'));

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
                <?php // can add any additional javascript settings to datetimepicker here; need to prepend first setting with a comma ?>
            });
        });

        function checkAll(checked) {
            var f = document.forms[0];
            for (var i = 0; i < f.elements.length; ++i) {
                var ename = f.elements[i].name;
                if (ename.indexOf('form_cb[') == 0)
                    f.elements[i].checked = checked;
            }
        }
    </script>

</head>

<body class="body_top">

<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('Revenue'); ?></span>

<form method='post' action='all_payer.php' enctype='multipart/form-data' id='theform' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

<div id="report_parameters">
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>
<input type='hidden' name='form_export' id='form_export' value=''/>
<input type='hidden' name='form_csvexport' id='form_csvexport' value=''/>

<table>
   <tr>
      <td class='col-form-label'>
          <?php echo xlt('Service Date'); ?>:
      </td>
      <td>
          <input type='text' class='datepicker form-control' name='form_date' id="form_date" size='10' value='<?php echo attr(oeFormatShortDate($form_date)); ?>'>
      </td>
      <td class='col-form-label'>
          <?php echo xlt('To{{Range}}'); ?>:
      </td>
      <td>
          <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
      </td>                        
  </tr>
  <tr> 
        <td align='left' valign='middle' height="100%">
            <div class="text-center">
                <div class="btn-group" role="group">
                    <a href='#' class='btn btn-secondary btn-save' onclick='$("#form_refresh").attr("value","true"); $("#form_csvexport").val(""); $("#theform").submit();'>
                        <?php echo xlt('Submit'); ?>
                    </a>
                        <?php if ($_POST['form_refresh']) { ?>
                    <a href='#' class='btn btn-secondary btn-print' onclick='window.print()'>
                        <?php echo xlt('Print'); ?>
                    </a>
                    <?php } ?>
                </div>
            </div>
        </td>
 </tr>
 </table>

</div>


    <?php    
        $sqlArray = [];
        $where .= "f.date >= ? AND f.date <= ? ";
        array_push($sqlArray, $form_date . ' 00:00:00', $form_to_date . ' 23:59:59');
    
    
   

    # added provider from encounter to the query (TLH)
    $query = "SELECT f.id, f.date, f.pid, f.encounter, f.last_level_billed, " .
      "f.last_level_closed, f.last_stmt_date, f.stmt_count, f.invoice_refno, " .
      "p.fname, p.mname, p.lname, p.street, p.city, p.state, " .
      "p.postal_code, p.phone_home, p.ss, p.billing_note, " .
      "p.pubpid, p.DOB, " .
      "( SELECT bill_date FROM billing AS b WHERE " .
      "b.pid = f.pid AND b.encounter = f.encounter AND " .
      "b.activity = 1 AND b.code_type != 'COPAY' LIMIT 1) AS billdate, " .
      "( SELECT SUM(b.fee) FROM billing AS b WHERE " .
      "b.pid = f.pid AND b.encounter = f.encounter AND " .
      "b.activity = 1 AND b.code_type != 'COPAY' ) AS charges, " .
      "( SELECT SUM(b.fee) FROM billing AS b WHERE " .
      "b.pid = f.pid AND b.encounter = f.encounter AND " .
      "b.activity = 1 AND b.code_type = 'COPAY' ) AS copays, " .
      "( SELECT SUM(s.fee) FROM drug_sales AS s WHERE " .
      "s.pid = f.pid AND s.encounter = f.encounter ) AS sales, " .
      "( SELECT SUM(a.pay_amount) FROM ar_activity AS a WHERE " .
      "a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS payments, " .
      "( SELECT SUM(a.adj_amount) FROM ar_activity AS a WHERE " .
      "a.pid = f.pid AND a.encounter = f.encounter AND a.deleted IS NULL) AS adjustments " .
      "FROM form_encounter AS f " .
      "JOIN patient_data AS p ON p.pid = f.pid " .
      "WHERE $where " .
      "ORDER BY f.pid, f.encounter";

    $eres = sqlStatement($query, $sqlArray);


    while ($erow = sqlFetchArray($eres)) {
        //var_dump($erow);
        if ($erow['charges'] ==  0) {
            continue;
        }

        endInsurance($erow);
        //var_dump($row);
        
    } // end while

    foreach($charges as $item) {

        $grand_total_charges += $item; 
        
    }

    foreach($payments as $item) {
        $grand_total_payments += $item; 
    }


?>
    <div id="report_results">
    <table id='mymaintable'>
    <thead class='thead-light'>
        <tr>
            <th>Payer</th>
            <th>Charges</th>
            <th>Payments</th>
        </tr>    
    </thead>
    <tbody>

    
    <?php
    echo " <tr >\n";
    echo "  <td>" .
    text('Medicare') . "</td>\n";
    echo "  <td class='detail' align='left'>&nbsp;" .
    text(oeFormatMoney($charges['medicare'])) . "&nbsp;</td>\n";
    echo "  <td class='detail' align='left'>&nbsp;" .
    text(oeFormatMoney($payments['medicare'])) . "&nbsp;</td>\n";
    echo "</tr>";

    echo " <tr class='bg-white'>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text('Medicaid') . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($charges['medicaid'])) . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($payments['medicaid'])) . "&nbsp;</td>\n";
    echo "</tr>";
   
    echo " <tr class='bg-white'>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text('Tricare') . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($charges['tricare'])) . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($payments['tricare'])) . "&nbsp;</td>\n";
    echo "</tr>";
       
    echo " <tr class='bg-white'>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text('Commercial') . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($charges['commercial'])) . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($payments['commercial'])) . "&nbsp;</td>\n";
    echo "</tr>";

    echo " <tr class='bg-white'>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text('Self pays') . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($charges['selfpay'])) . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($payments['selfpay'])) . "&nbsp;</td>\n";
    echo "</tr>";
    
    echo " <tr class='bg-white'>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text('Report totals') . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($grand_total_charges)) . "&nbsp;</td>\n";
    echo "  <td class='dehead' align='left'>&nbsp;" .
    text(oeFormatMoney($grand_total_payments)) . "&nbsp;</td>\n";
    echo "</tr>";
    
    echo "</tbody>";
    echo "</table>";
    echo "</div>";
    echo "</body>";
    echo "</html>";


   
?>
