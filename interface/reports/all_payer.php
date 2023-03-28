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
require_once("$srcdir/classes/InsuranceCompany.class.php");
require_once "$srcdir/options.inc.php";

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;
use OpenEMR\OeUI\OemrUI;
use OpenEMR\Services\InsuranceCompanyService;

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

$alertmsg = '';
$bgcolor = "#aaaaaa";
$export_patient_count = 0;
$export_dollars = 0;
$grand_total_charges = 0;
$grand_total_payments = 0;
$charges['medicare'] = 0;
$payments['medicare'] = 0;

$form_date      = (isset($_POST['form_date'])) ? DateToYYYYMMDD($_POST['form_date']) : "";
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : "";

function getChargesByDateAndCategory($insrow)
{
    global $charges;
    global $form_date, $form_to_date;
    $pay_pid = $insrow['pid'];
    $pay_enc = $insrow['encounter'];
    $enc_date = $insrow['date'];

    $insurance = getInsuranceDataByDate(
        $pay_pid,
        $enc_date,
        "primary"
    );

    $ins_id = $insurance['provider'];
    $insurance_company = (new InsuranceCompanyService())->getOne($ins_id);
    $ins_type_code = $insurance_company['ins_type_code'];

    if ($ins_type_code == '2') {
        $charges['medicare'] += $insrow['charges'];
    } elseif ($ins_type_code == '3') {
        $charges['medicaid'] += $insrow['charges'];
    } elseif (in_array($ins_type_code, array('4', '5'))) {
        $charges['tricare'] += $insrow['charges'];
    } else {
        $charges['commercial'] += $insrow['charges'];
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

function getPaymentsByPayerType($pay_row)
{
    global $payments;

    $enc_date = sqlQuery(
        "SELECT date FROM form_encounter WHERE encounter = ?",
        array($pay_row['encounter'])
    );

    if ($pay_row['payer_type'] == '1') {
        $type = 'primary';
    } elseif ($pay_row['payer_type'] == '2') {
        $type = 'secondary';
    } elseif ($pay_row['payer_type'] == '3') {
        $type = 'tertiary';
    }
        $pay_ins = getInsuranceDataByDate(
            $pay_row['pid'],
            $enc_date['date'],
            $type
        );
        $pay_insco = (new InsuranceCompanyService())->getOne($pay_ins['provider']);
        $pay_ins_type_code = $pay_insco['ins_type_code'];
    switch ($pay_ins_type_code) {
        case 0:
            $payments['selfpay'] += $pay_row['pay_amount'];
            break;
        case 2:
            $payments['medicare'] += $pay_row['pay_amount'];
            break;
        case 3:
            $payments['medicaid'] += $pay_row['pay_amount'];
            break;
        case 4:
            $payments['tricare'] += $pay_row['pay_amount'];
            break;
        case 5:
            $payments['tricare'] += $pay_row['pay_amount'];
            break;
        default:
            $payments['commercial'] += $pay_row['pay_amount'];
    }
}


?>
<html>
<head>

    <title><?php echo xlt('All payer revenue report')?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

    <?php
        $arrOeUiSettings = array(
            'heading_title' => xl('Payer Mix'),
            'include_patient_name' => false,
            'expandable' => false,
            'expandable_files' => array(),//all file names need suffix _xpd
            'action' => "",//conceal, reveal, search, reset, link or back
            'action_title' => "",
            'action_href' => "",//only for actions - reset, link or back
            'show_help_icon' => true,
            'help_file_name' => "payer_mix_help.php"
        );
        $oemr_ui = new OemrUI($arrOeUiSettings);
    ?>
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
            $("#theform").submit();
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
    </script>

</head>

<body class="body_top">
<div id="container_div" class="<?php echo attr($oemr_ui->oeContainer()); ?> mt-3">
        <div class="row">
            <div class="col-sm-12">
                <?php echo $oemr_ui->pageHeading() . "\r\n"; ?>
           </div>
        </div>

<span class='title'><?php echo xlt('Payer Mix Charges/Payments'); ?></span>

<form method='post' action='all_payer.php' enctype='multipart/form-data' id='theform' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

<div id="report_parameters">
    <input type='hidden' name='form_refresh' id='form_refresh' value=''/>
    <div class="input-group">
        <div class="form-group col-md-2">
            <label for="form_date">
                <?php echo xlt('From Date'); ?>:
            </label>
            <input type='text' class='datepicker form-control' name='form_date' id="form_date" size='10' value='<?php echo attr(oeFormatShortDate($form_date)); ?>'>
        </div>
        <div class="form-group col-md-2">
            <label for="form_to_date">
                <?php echo xlt('To{{Range}}'); ?>:
            </label>
            <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
        </div>
    </div>
    <div class="btn-group m-3" role="group">
        <a href='#' class='btn btn-primary btn-save' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
            <?php echo xlt('Submit'); ?>
        </a>
        <?php if ($_POST['form_refresh']) { ?>
            <a href='#' class='btn btn-secondary btn-print' onclick='window.print()'>
                <?php echo xlt('Print'); ?>
            </a>
        <?php } ?>
    </div>
</div>


<?php
    $sqlArray = [];
    $where = "f.date >= ? AND f.date <= ? ";
    array_push($sqlArray, $form_date . ' 00:00:00', $form_to_date . ' 23:59:59');

    $query = "SELECT f.id, f.date, f.pid, f.encounter, " .
      "( SELECT SUM(b.fee) FROM billing AS b WHERE " .
      "b.pid = f.pid AND b.encounter = f.encounter AND " .
      "b.activity = 1 AND b.code_type != 'COPAY' ) AS charges " .
      "FROM form_encounter AS f " .
      "JOIN patient_data AS p ON p.pid = f.pid " .
      "WHERE $where " .
      "ORDER BY f.pid, f.encounter";

    $eres = sqlStatement($query, $sqlArray);

while ($erow = sqlFetchArray($eres)) {
    if ($erow['charges'] ==  0) {
        continue;
    }
    getChargesByDateAndCategory($erow);
}

$ar_session_query = "SELECT session_id FROM ar_session WHERE deposit_date >= ? AND deposit_date <= ?";
$ar_session_res = sqlStatement($ar_session_query, array($form_date, $form_to_date));

while ($ar_row = sqlFetchArray($ar_session_res)) {
    $ar_activity_query = "SELECT pid, encounter, payer_type, pay_amount FROM ar_activity WHERE session_id = ?";
    $ar_activity_res = sqlStatement($ar_activity_query, array($ar_row['session_id']));
    while ($ar_activity_row = sqlFetchArray($ar_activity_res)) {
        if ($ar_activity_row['pay_amount'] != 0) {
            getPaymentsByPayerType($ar_activity_row);
        }
    }
}

foreach ($charges as $item) {
    $grand_total_charges += $item;
}

foreach ($payments as $item) {
    $grand_total_payments += $item;
}

?>

<div id="report_results">
    <table id='mymaintable' class="table table-striped">
        <thead>
            <tr>
                <th scope="col">Payer</th>
                <th scope="col">Charges</th>
                <th scope="col">Payments</th>
            </tr>    
        </thead>
        <tbody>
            <tr>
                <th scope="row"><?php echo text('Medicare') ?></th>
                <td><?php echo text(oeFormatMoney($charges['medicare'] ?? null)) ?></td>
                <td><?php echo text(oeFormatMoney($payments['medicare'] ?? null)) ?> </td>
            </tr>
            <tr>
                <th scope="row"><?php echo text('Medicaid') ?> </th>
                <td><?php echo text(oeFormatMoney($charges['medicaid'] ?? null)) ?></td>
                <td><?php echo text(oeFormatMoney($payments['medicaid'] ?? null)) ?></td>
            </tr>
            <tr>
                <th scope="row"><?php echo text('Tricare') ?></th>
                <td><?php echo text(oeFormatMoney($charges['tricare'] ?? null)) ?></td>
                <td><?php echo text(oeFormatMoney($payments['tricare'] ?? null)) ?></td>
            </tr>
            <tr>
                <th scope="row"><?php echo text('Commercial') ?></td>
                <td><?php echo text(oeFormatMoney($charges['commercial'])) ?></td>
                <td><?php echo text(oeFormatMoney($payments['commercial'])) ?></td>
            </tr>
            <tr>
                <th scope="row"><?php echo text('Self pays') ?></td>
                <td><?php echo text(oeFormatMoney($charges['selfpay'] ?? null)) ?></td>
                <td><?php echo text(oeFormatMoney($payments['selfpay'])) ?></td>
            </tr>
            <tr>
                <th scope="row"><?php echo text('Report totals') ?></th>
                <td><?php echo text(oeFormatMoney($grand_total_charges)) ?></td>
                <td><?php echo text(oeFormatMoney($grand_total_payments)) ?></td>
            </tr>
        </tbody>
    </table>    
</div>
</div> <!-- end container ui help div -->
<?php $oemr_ui->oeBelowContainerDiv(); ?>
</body>
</html>
