<?php

/**
 * Prepayment balance report.
 *
 * Lists open pre-payment sessions (ar_session.adjustment_code = 'pre_payment',
 * closed = 0) whose received amount has not been fully applied to charges.
 *
 * NOTE: "Unapplied" here is pay_total - SUM(live ar_activity.pay_amount) and
 * deliberately does NOT subtract ar_session.global_amount. Money swept to the
 * "Global Account" is never consumed by any code path (global_amount is never
 * decremented anywhere in the codebase), so it is still patient money that has
 * not satisfied a charge. It is shown separately in the "In Global" column so
 * parked credit is visible instead of hidden. // LOCAL: SPECS custom report
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Core\Header;

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

if (!AclMain::aclCheckCore('acct', 'rep_a')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Prepayment Balances")]);
    exit;
}

// Optional filters. Empty from-date means "no lower bound" so the default
// view is ALL open prepayments regardless of age (that is the point of the
// report). Dates filter on ar_session.check_date.
$form_from_date = (!empty($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : '';
$form_to_date   = (!empty($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : '';
$form_patient   = trim($_POST['form_patient'] ?? '');
$form_parked_only = !empty($_POST['form_parked_only']);
?>

<html>

<head>
    <title><?php echo xlt('Prepayment Balances'); ?></title>

    <?php Header::setupHeader(["datetime-picker", "report-helper"]); ?>

    <script>
        // Re-run the report when the edit_payment dialog closes so applied
        // sessions drop off the list immediately (mirrors refreshSearch in
        // search_payments.php).
        function refreshReport() {
            $("#form_refresh").attr("value", "true");
            $("#theform").submit();
        }

        $(function () {
            var win = top.printLogSetup ? top : opener.top;
            win.printLogSetup(document.getElementById('printbutton'));

            // Open sessions in a dialog instead of navigating this tab.
            // Same pattern as search_payments.php's medium_modal handler.
            $(document).on('click', '.medium_modal', function (e) {
                e.preventDefault();
                e.stopPropagation();
                dlgopen('', '', 'modal-full', 800, '', '', {
                    buttons: [
                        {text: <?php echo xlj('Close'); ?>, close: true, style: 'default btn-sm'}
                    ],
                    sizeHeight: '',
                    onClosed: 'refreshReport',
                    type: 'iframe',
                    url: $(this).attr('href')
                });
            });

            $('.datepicker').datetimepicker({
                <?php $datetimepicker_timepicker = false; ?>
                <?php $datetimepicker_showseconds = false; ?>
                <?php $datetimepicker_formatInput = true; ?>
                <?php require($GLOBALS['srcdir'] . '/js/xl/jquery-datetimepicker-2-5-4.js.php'); ?>
            });
        });

        function setpatient(pid, lname, fname, dob) {
            document.forms[0].elements['form_patient'].value = pid;
        }

        function sel_patient() {
            dlgopen('../main/calendar/find_patient_popup.php?pflag=0', '_blank', 500, 400);
        }
    </script>

    <style>
        /* specifically include & exclude from printing */
        @media print {
            #report_parameters {
                visibility: hidden;
                display: none;
            }
            #report_parameters_daterange {
                visibility: visible;
                display: inline;
            }
            #report_results table {
                margin-top: 0px;
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
</head>

<body class="body_top">

<!-- Required for the popup date selectors -->
<div id="overDiv" style="position: absolute; visibility: hidden; z-index: 1000;"></div>

<span class='title'><?php echo xlt('Prepayment Balances'); ?></span>

<div id="report_parameters_daterange"><?php echo text(oeFormatShortDate($form_from_date)) . " &nbsp; " . xlt('to{{Range}}') . " &nbsp; " . text(oeFormatShortDate($form_to_date)); ?>
</div>

<form method='post' name='theform' id='theform' action='prepayment_balance_report.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

<div id="report_parameters">

<table>
    <tr>
        <td width='650px'>
        <div style='float: left'>

        <table class='text'>
            <tr>
                <td class='col-form-label'><?php echo xlt('Check Date From'); ?>:</td>
                <td><input type='text' name='form_from_date' id="form_from_date" class='datepicker form-control' size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>' /></td>
                <td class='col-form-label'><?php echo xlt('To{{Range}}'); ?>:</td>
                <td><input type='text' name='form_to_date' id="form_to_date" class='datepicker form-control' size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'></td>
            </tr>
            <tr>
                <td class='col-form-label'><?php echo xlt('Patient'); ?>:</td>
                <td>
                    <input type='text' size='20' name='form_patient' class='form-control' style='cursor:pointer;' id='form_patient' value='<?php echo attr($form_patient); ?>' onclick='sel_patient()' title='<?php echo xla('Click to select patient'); ?>' />
                </td>
                <td class='col-form-label'><?php echo xlt('Parked in Global only'); ?>:</td>
                <td>
                    <input type='checkbox' name='form_parked_only' value='1' <?php echo $form_parked_only ? 'checked' : ''; ?> />
                </td>
            </tr>
        </table>

        </div>

        </td>
        <td class='h-100' align='left' valign='middle'>
        <table class='w-100 h-100' style='border-left: 1px solid;'>
            <tr>
                <td>
                    <div class="text-center">
                        <div class="btn-group" role="group">
                            <a href='#' class='btn btn-secondary btn-save' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
                                <?php echo xlt('Submit'); ?>
                            </a>
                            <?php if (!empty($_POST['form_refresh'])) { ?>
                                <a href='#' class='btn btn-secondary btn-print' id='printbutton'>
                                    <?php echo xlt('Print'); ?>
                                </a>
                            <?php } ?>
                        </div>
                    </div>
                </td>
            </tr>
        </table>
        </td>
    </tr>
</table>

</div>
<!-- end of search parameters --> <?php
if (!empty($_POST['form_refresh'])) {
    // Live applied money per (session, pid), pre-aggregated to avoid
    // row fan-out multiplying pay_total across multiple distributions.
    $query = "SELECT s.session_id, s.patient_id, s.check_date, s.reference, " .
        "s.pay_total, s.global_amount, " .
        "COALESCE(act.applied, 0) AS applied, " .
        "s.pay_total - COALESCE(act.applied, 0) AS unapplied, " .
        "DATEDIFF(CURDATE(), s.check_date) AS days_open, " .
        "p.lname, p.fname, p.mname " .
        "FROM ar_session AS s " .
        "LEFT JOIN ( " .
        "SELECT session_id, pid, SUM(pay_amount) AS applied " .
        "FROM ar_activity WHERE deleted IS NULL " .
        "GROUP BY session_id, pid " .
        ") AS act ON act.session_id = s.session_id AND act.pid = s.patient_id " .
        "LEFT JOIN patient_data AS p ON p.pid = s.patient_id " .
        "WHERE s.adjustment_code = 'pre_payment' AND s.closed = 0";
    $sqlBindArray = [];
    if ($form_from_date) {
        $query .= " AND s.check_date >= ?";
        $sqlBindArray[] = $form_from_date;
    }
    if ($form_to_date) {
        $query .= " AND s.check_date <= ?";
        $sqlBindArray[] = $form_to_date;
    }
    if ($form_patient) {
        $query .= " AND s.patient_id = ?";
        $sqlBindArray[] = $form_patient;
    }
    if ($form_parked_only) {
        $query .= " AND s.global_amount != 0";
    }
    $query .= " HAVING unapplied > 0.005 ORDER BY unapplied DESC, s.check_date ASC";

    $res = sqlStatement($query, $sqlBindArray);

    $tot_pay_total = 0.0;
    $tot_applied = 0.0;
    $tot_global = 0.0;
    $tot_unapplied = 0.0;
    ?>
<div id="report_results">
<table class='table'>

    <thead class='thead-light'>
        <th><?php echo xlt('Patient'); ?></th>
        <th><?php echo xlt('PID'); ?></th>
        <th><?php echo xlt('Session'); ?></th>
        <th><?php echo xlt('Reference'); ?></th>
        <th><?php echo xlt('Check Date'); ?></th>
        <th class="text-right"><?php echo xlt('Days Open'); ?></th>
        <th class="text-right"><?php echo xlt('Received'); ?></th>
        <th class="text-right"><?php echo xlt('Applied'); ?></th>
        <th class="text-right"><?php echo xlt('In Global'); ?></th>
        <th class="text-right"><?php echo xlt('Unapplied'); ?></th>
    </thead>
    <tbody>
    <?php
    while ($row = sqlFetchArray($res)) {
        $tot_pay_total += (float) $row['pay_total'];
        $tot_applied   += (float) $row['applied'];
        $tot_global    += (float) $row['global_amount'];
        $tot_unapplied += (float) $row['unapplied'];
        $patname = $row['lname'] . ', ' . $row['fname'] . ($row['mname'] ? ' ' . $row['mname'] : '');
        ?>
        <tr valign='top'>
            <td class="detail">&nbsp;<?php echo text($patname); ?></td>
            <td class="detail">&nbsp;<?php echo text($row['patient_id']); ?></td>
            <td class="detail">&nbsp;
                <a class="medium_modal" href='../billing/edit_payment.php?payment_id=<?php echo attr_url($row['session_id']); ?>'>
                    <?php echo text($row['session_id']); ?>
                </a>
            </td>
            <td class="detail">&nbsp;<?php echo text($row['reference']); ?></td>
            <td class="detail">&nbsp;<?php echo text(oeFormatShortDate($row['check_date'])); ?></td>
            <td class="detail text-right"><?php echo text($row['days_open']); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($row['pay_total'])); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($row['applied'])); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($row['global_amount'])); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($row['unapplied'])); ?></td>
        </tr>
    <?php } ?>
    </tbody>
    <tfoot>
        <tr class='font-weight-bold'>
            <td class="detail" colspan="6">&nbsp;<?php echo xlt('Totals'); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($tot_pay_total)); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($tot_applied)); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($tot_global)); ?></td>
            <td class="detail text-right"><?php echo text(oeFormatMoney($tot_unapplied)); ?></td>
        </tr>
    </tfoot>
</table>
</div>
<!-- end of search results -->
<?php } else { ?>
<div class='text'><?php echo xlt('Click Submit to list all open prepayments with an unapplied balance. Date and patient filters are optional.'); ?>
</div>
<?php } ?>
<input type='hidden' name='form_refresh' id='form_refresh' value='' /></form>

</body>

</html>
