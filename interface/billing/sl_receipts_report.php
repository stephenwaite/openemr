<?php

/**
 * Report - Cash receipts by Provider
 *
 * This module was written for one of my clients to report on cash
 * receipts by practitioner.  It is not as complete as it should be
 * but I wanted to make the code available to the project because
 * many other practices have this same need. - rod@sunsetsystems.com
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Terry Hill <terry@lillysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2006-2020 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2016 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2019 Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2025 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once('../globals.php');
require_once($GLOBALS['srcdir'] . '/patient.inc.php');
require_once($GLOBALS['srcdir'] . '/options.inc.php');
require_once($GLOBALS['fileroot'] . '/custom/code_types.inc.php');
// This determines if a particular procedure code corresponds to receipts
// for the "Clinic" column as opposed to receipts for the practitioner.  Each
// practice will have its own policies in this regard, so you'll probably
// have to customize this function.  If you use the "fee sheet" encounter
// form then the code below may work for you.
//
require_once('../forms/fee_sheet/codes.php');

use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Twig\TwigContainer;
use OpenEMR\Common\Utils\FormatMoney;
use OpenEMR\Core\Header;

if (!AclMain::aclCheckCore('acct', 'rep') && !AclMain::aclCheckCore('acct', 'rep_a')) {
    echo (new TwigContainer(null, $GLOBALS['kernel']))->getTwig()->render('core/unauthorized.html.twig', ['pageTitle' => xl("Cash Receipts by Provider")]);
    exit;
}

function is_clinic($code)
{
    global $bcodes;
    $i = strpos($code, ':');
    if ($i) {
        $code = substr($code, 0, $i);
    }

    return (
        !empty($bcodes['CPT4'][xl('Lab')][$code])   ||
        !empty($bcodes['CPT4'][xl('Immunizations')][$code]) ||
        !empty($bcodes['HCPCS'][xl('Therapeutic Injections')][$code])
    );
}

$form_use_edate  = intval($_POST['form_use_edate'] ?? 0);

$form_proc_codefull = trim($_POST['form_proc_codefull'] ?? '');
// Parse the code type and the code from <code_type>:<code>
$tmp_code_array = explode(':', $form_proc_codefull);
$form_proc_codetype = $tmp_code_array[0];
$form_proc_code = $tmp_code_array[1] ?? null;

$form_dx_codefull  = trim($_POST['form_dx_codefull'] ?? '');
// Parse the code type and the code from <code_type>:<code>
$tmp_code_array = explode(':', $form_dx_codefull);
$form_dx_codetype = $tmp_code_array[0];
$form_dx_code = $tmp_code_array[1] ?? null;

$form_procedures = empty($_POST['form_procedures']) ? 0 : 1;
$form_from_date = (isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-01');
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');

$form_facility   = $_POST['form_facility'] ?? null;
?>
<html>
<head>

    <title><?php echo xlt('Cash Receipts by Provider')?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

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
        $(function () {
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

        // This is for callback by the find-code popup.
        // Erases the current entry
        // The target element is set by the find-code popup
        //  (this allows use of this in multiple form elements on the same page)
        function set_related_target(codetype, code, selector, codedesc, target_element, limit=0) {
            var f = document.forms[0];
            var s = f[target_element].value;
            if (code) {
                s = codetype + ':' + code;
            } else {
                s = '';
            }
            f[target_element].value = s;
        }

        // This invokes the find-code (procedure/service codes) popup.
        function sel_procedure() {
            dlgopen('../patient_file/encounter/find_code_popup.php?target_element=form_proc_codefull&codetype=' + <?php echo js_url(collect_codetypes("procedure", "csv")); ?>, '_blank', 500, 400);
        }

        // This invokes the find-code (diagnosis codes) popup.
        function sel_diagnosis() {
            dlgopen('../patient_file/encounter/find_code_popup.php?target_element=form_dx_codefull&codetype=' + <?php echo js_url(collect_codetypes("diagnosis", "csv")); ?>, '_blank', 500, 400);
        }

    </script>
</head>

<body class="body_top">
<div class="container">
    <div class="row">
        <div class="col-sm-12">
            <div class="clearfix">
                <h2 class="title">
                    <?php echo xlt('Report'); ?> - <?php echo xlt('Cash Receipts by Provider'); ?>
                </h2>
            </div>
        </div>
    </div><!-- end of header div -->
    <div class="row">
        <div class="col">
               <form method='post' action='sl_receipts_report.php' id='theform' onsubmit='return top.restoreSession()'>
                <input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

                <div id="report_parameters">

                <input type='hidden' name='form_refresh' id='form_refresh' value=''/>

                <div class="container">
                    <div class="row">
                        <div class="col">
                                <div class="row">
                                    <div class="col-4">
                                        <div class='col-form-label'>
                                            <?php echo xlt('Facility'); ?>:
                                        </div>
                                        <div class="form-select">
                                        <?php dropdown_facility($form_facility, 'form_facility'); ?>
                                        </div>
                                        <div class='col-form-label'>
                                            <?php echo xlt('Provider'); ?>:
                                        </div>
                                        <div class="form-select">
                                            <?php
                                            if (AclMain::aclCheckCore('acct', 'rep_a')) {
                                                // Build a drop-down list of providers.
                                                //
                                                $query = "select id, lname, fname from users where " .
                                                    "authorized = 1 order by lname, fname";
                                                $res = sqlStatement($query);
                                                echo "<select name='form_doctor' class='form-control'>\n";
                                                echo "    <option value=''>-- " . xlt('All Providers') . " --\n";
                                                while ($row = sqlFetchArray($res)) {
                                                    $provid = $row['id'];
                                                    echo "    <option value='" . attr($provid) . "'";
                                                    if (!empty($_POST['form_doctor']) && ($provid == $_POST['form_doctor'])) {
                                                        echo " selected";
                                                    }

                                                    echo ">" . text($row['lname']) . ", " . text($row['fname']) . "\n";
                                                }

                                                echo "   </select>\n";
                                            } else {
                                                echo "<input type='hidden' name='form_doctor' value='" . attr($_SESSION['authUserID']) . "'>";
                                            }
                                            ?>
                                        </div>
                                        <div class='col-form-label'>
                                            <?php echo xlt('Date Type'); ?>:
                                        </div>
                                        <div class="form-select">
                                            <select name='form_use_edate' class='form-control'>
                                                <option value='0'><?php echo xlt('Payment Date'); ?></option>
                                                <option value='1'<?php echo ($form_use_edate == 1) ? ' selected' : ''; ?>><?php echo xlt('Service Date'); ?></option>
                                                <option value='2'<?php echo ($form_use_edate == 2) ? ' selected' : ''; ?>><?php echo xlt('Entry Date'); ?></option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class='col-form-label'>
                                            <?php echo xlt('From'); ?>:
                                        </div>
                                        <div class="controls inline-inputs">
                                            <input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>'
                                                title='<?php echo xla('Date of appointments'); ?>' >
                                        </div>
                                        <div class='col-form-label'>
                                            <?php echo xlt('To{{Range}}'); ?>:
                                        </div>
                                        <div class="controls inline-inputs">
                                            <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'
                                                title='<?php echo xla('Optional end date'); ?>' >
                                        </div>
                                    </div>
                                    <div class="col-4">
                                        <div class='col-form-label'>
                                            <?php
                                            if (!$GLOBALS['simplified_demographics']) {
                                                echo '&nbsp;' . xlt('Procedure/Service') . ':';
                                            } ?>
                                        </div>
                                        <div class="controls inline-inputs">
                                            <input type='text' class='form-control' name='form_proc_codefull' size='11' value='<?php echo attr($form_proc_codefull); ?>' onclick='sel_procedure()'
                                                title='<?php echo xla('Optional procedure/service code'); ?>'
                                                <?php
                                                if ($GLOBALS['simplified_demographics']) {
                                                    echo "style='display:none'";
                                                } ?>>
                                        </div>

                                        <div class='col-form-label'>
                                            <?php
                                            if (!$GLOBALS['simplified_demographics']) {
                                                echo '&nbsp;' . xlt('Diagnosis') . ':';
                                            } ?>
                                        </div>

                                        <div class="controls inline-inputs">
                                            <input type='text' class='form-control' name='form_dx_codefull' size='11' value='<?php echo attr($form_dx_codefull); ?>' onclick='sel_diagnosis()'
                                                title='<?php echo xla('Enter a diagnosis code to exclude all invoices not containing it'); ?>'
                                                <?php
                                                if ($GLOBALS['simplified_demographics']) {
                                                    echo "style='display: none'";
                                                } ?>>
                                        </div>

                                        <div class="controls inline-inputs mt-3">
                                            <div class='checkbox'>
                                                    <label><input type='checkbox' name='form_details' value='1'<?php echo (!empty($_POST['form_details'])) ? " checked" : ""; ?>><?php echo xlt('Details')?></label>
                                            </div>
                                            <div class='checkbox'>
                                                    <label><input type='checkbox' name='form_procedures' value='1'<?php echo ($form_procedures) ? " checked" : ""; ?>><?php echo xlt('Procedures')?></label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                        </div>
                    </div>
                    <div class="row center mt-3">
                        <div class="col">
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
                        </div>
                    </div>
                </div> <?php // report parameters div ?>
                </div>

                <?php
                if (!empty($_POST['form_refresh'])) {
                    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
                        CsrfUtils::csrfNotVerified();
                    }

                    ?>
                <div id="report_results">
                <div class="table" id='mymaintable'>
                    <?php $report_from_date = oeFormatShortDate($form_from_date)  ;
                        $report_to_date = oeFormatShortDate($form_to_date)  ;
                    ?>
                <div class='row title center'>
                    <?php echo xlt('Report Date') . ' '; ?><?php echo text($report_from_date);?> - <?php echo text($report_to_date);?>
            </div>

                <div class="row bg-light">
                <div class="col">
                    <?php echo xlt('Practitioner') ?>
                </div>
                <div class="col">
                    <?php echo xlt('Date') ?>
                </div>
                    <?php if ($form_procedures) { ?>
                <div class="col">
                        <?php
                        if ($GLOBALS['cash_receipts_report_invoice'] == '0') {
                            echo xlt('Invoice');
                        } else {
                            echo xlt('Name');
                        }?>
                </div>
                    <?php } ?>
                    <?php if ($form_proc_codefull) { ?>
                <div class="col">
                        <?php echo xlt('InvAmt') ?>
                </div>
                    <?php } ?>
                    <?php if ($form_proc_codefull) { ?>
                <div class="col">
                        <?php echo xlt('Insurance') ?>
                </div>
                    <?php } ?>
                    <?php if ($form_procedures) { ?>
                <div class="col">
                        <?php echo xlt('Procedure') ?>
                </div>
                <div class="col text-right">
                        <?php echo xlt('Provider Receipts') ?>
                </div>
                <div class="col text-right">
                        <?php echo xlt('Clinic') ?>
                </div>
                    <?php } else { ?>
                <div class="col text-right">
                        <?php echo xlt('Received') ?>
                </div>
                    <?php } ?>
                </div>
                    <?php
                    if ($_POST['form_refresh']) {
                        $form_doctor = $_POST['form_doctor'];
                        if (!AclMain::aclCheckCore('acct', 'rep_a')) {
                            // only allow user to see their encounter information
                            $form_doctor = $_SESSION['authUserID'];
                        }

                        $arows = array();

                        $ids_to_skip = array();
                        $irow = 0;

                        // Get copays.  These will be ignored if a CPT code was specified.
                        //
                        if (!$form_proc_code || !$form_proc_codetype) {
                            /*************************************************************
                            $query = "SELECT b.fee, b.pid, b.encounter, b.code_type, b.code, b.modifier, " .
                            "fe.date, fe.id AS trans_id, u.id AS docid " .
                            "FROM billing AS b " .
                            "JOIN form_encounter AS fe ON fe.pid = b.pid AND fe.encounter = b.encounter " .
                            "JOIN forms AS f ON f.pid = b.pid AND f.encounter = b.encounter AND f.formdir = 'newpatient' " .
                            "LEFT OUTER JOIN users AS u ON u.username = f.user " .
                            "WHERE b.code_type = 'COPAY' AND b.activity = 1 AND " .
                            "fe.date >= '$form_from_date 00:00:00' AND fe.date <= '$form_to_date 23:59:59'";
                            // If a facility was specified.
                            if ($form_facility) {
                            $query .= " AND fe.facility_id = '$form_facility'";
                            }
                            // If a doctor was specified.
                            if ($form_doctor) {
                            $query .= " AND u.id = '$form_doctor'";
                            }
                            *************************************************************/
                            $sqlBindArray = array();
                            $query = "SELECT b.fee, b.pid, b.encounter, b.code_type, b.code, b.modifier, " .
                            "fe.date, fe.id AS trans_id, fe.provider_id AS docid, fe.invoice_refno " .
                            "FROM billing AS b " .
                            "JOIN form_encounter AS fe ON fe.pid = b.pid AND fe.encounter = b.encounter " .
                            "WHERE b.code_type = 'COPAY' AND b.activity = 1 AND " .
                            "fe.date >= ? AND fe.date <= ?";
                            array_push($sqlBindArray, $form_from_date . " 00:00:00", $form_to_date . " 23:59:59");
                            // If a facility was specified.
                            if ($form_facility) {
                                $query .= " AND fe.facility_id = ?";
                                array_push($sqlBindArray, $form_facility);
                            }

                            // If a doctor was specified.
                            if ($form_doctor) {
                                $query .= " AND fe.provider_id = ?";
                                array_push($sqlBindArray, $form_doctor);
                            }

                            /************************************************************/
                            //
                            $res = sqlStatement($query, $sqlBindArray);
                            while ($row = sqlFetchArray($res)) {
                                $trans_id = $row['trans_id'];
                                $thedate = substr($row['date'], 0, 10);
                                $patient_id = $row['pid'];
                                $encounter_id = $row['encounter'];
                            //
                                if (!empty($ids_to_skip[$trans_id])) {
                                    continue;
                                }

                            //
                            // If a diagnosis code was given then skip any invoices without
                            // that diagnosis.
                                if ($form_dx_code && $form_dx_codetype) {
                                    $tmp = sqlQuery("SELECT count(*) AS count FROM billing WHERE " .
                                    "pid = ? AND encounter = ? AND " .
                                    "code_type = ? AND code LIKE ? AND " .
                                    "activity = 1", array($patient_id,$encounter_id,$form_dx_codetype,$form_dx_code));
                                    if (empty($tmp['count'])) {
                                        $ids_to_skip[$trans_id] = 1;
                                        continue;
                                    }
                                }

                            //
                                $key = sprintf(
                                    "%08u%s%08u%08u%06u",
                                    $row['docid'],
                                    $thedate,
                                    $patient_id,
                                    $encounter_id,
                                    ++$irow
                                );
                                $arows[$key] = array();
                                $arows[$key]['transdate'] = $thedate;
                                $arows[$key]['amount'] = $row['fee'];
                                $arows[$key]['docid'] = $row['docid'];
                                $arows[$key]['project_id'] = 0;
                                $arows[$key]['memo'] = '';
                                if ($GLOBALS['cash_receipts_report_invoice'] == '0') {
                                    $arows[$key]['invnumber'] = "$patient_id.$encounter_id";
                                } else {
                                    $arows[$key]['invnumber'] = "$patient_name";
                                }

                                $arows[$key]['irnumber'] = $row['invoice_refno'];
                            } // end while
                        } // end copays (not $form_proc_code)

                        // Get ar_activity (having payments), form_encounter, forms, users, optional ar_session
                        /***************************************************************
                        $query = "SELECT a.pid, a.encounter, a.post_time, a.code, a.modifier, a.pay_amount, " .
                        "fe.date, fe.id AS trans_id, u.id AS docid, s.deposit_date, s.payer_id " .
                        "FROM ar_activity AS a " .
                        "JOIN form_encounter AS fe ON fe.pid = a.pid AND fe.encounter = a.encounter " .
                        "JOIN forms AS f ON f.pid = a.pid AND f.encounter = a.encounter AND f.formdir = 'newpatient' " .
                        "LEFT OUTER JOIN users AS u ON u.username = f.user " .
                        "LEFT OUTER JOIN ar_session AS s ON s.session_id = a.session_id " .
                        "WHERE a.pay_amount != 0 AND ( " .
                        "a.post_time >= '$form_from_date 00:00:00' AND a.post_time <= '$form_to_date 23:59:59' " .
                        "OR fe.date >= '$form_from_date 00:00:00' AND fe.date <= '$form_to_date 23:59:59' " .
                        "OR s.deposit_date >= '$form_from_date' AND s.deposit_date <= '$form_to_date' )";
                        // If a procedure code was specified.
                        if ($form_proc_code) $query .= " AND a.code = '$form_proc_code'";
                        // If a facility was specified.
                        if ($form_facility) $query .= " AND fe.facility_id = '$form_facility'";
                        // If a doctor was specified.
                        if ($form_doctor) $query .= " AND u.id = '$form_doctor'";
                        ***************************************************************/
                        $sqlBindArray = array();
                        $query = "SELECT a.pid, a.encounter, a.post_time, a.code, a.modifier, a.pay_amount, " .
                        "fe.date, fe.id AS trans_id, fe.provider_id AS docid, fe.invoice_refno, s.deposit_date, s.payer_id, " .
                        "b.provider_id, concat(p.lname, ' ', p.fname) as 'pat_fulname' " .
                        "FROM ar_activity AS a " .
                        "JOIN form_encounter AS fe ON fe.pid = a.pid AND fe.encounter = a.encounter " .
                        "LEFT OUTER JOIN ar_session AS s ON s.session_id = a.session_id " .
                        "LEFT OUTER JOIN patient_data AS p ON p.pid = a.pid " .
                        "LEFT OUTER JOIN billing AS b ON b.pid = a.pid AND b.encounter = a.encounter AND " .
                        "b.code = a.code AND b.modifier = a.modifier AND b.activity = 1 AND " .
                        "b.code_type != 'COPAY' AND b.code_type != 'TAX' " .
                        "WHERE a.deleted IS NULL AND a.pay_amount != 0 AND ( " .
                        "a.post_time >= ? AND a.post_time <= ? " .
                        "OR fe.date >= ? AND fe.date <= ? " .
                        "OR s.deposit_date >= ? AND s.deposit_date <= ? )";
                        array_push($sqlBindArray, $form_from_date . " 00:00:00", $form_to_date . " 23:59:59", $form_from_date . " 00:00:00", $form_to_date . " 23:59:59", $form_from_date, $form_to_date);
                        // If a procedure code was specified.
                        // Support code type if it is in the ar_activity table. Note it is not always included, so
                        // also support a blank code type in ar_activity table.
                        if ($form_proc_codetype && $form_proc_code) {
                            $query .= " AND (a.code_type = ? OR a.code_type = '') AND a.code = ?";
                            array_push($sqlBindArray, $form_proc_codetype, $form_proc_code);
                        }

                        // If a facility was specified.
                        if ($form_facility) {
                            $query .= " AND fe.facility_id = ?";
                            array_push($sqlBindArray, $form_facility);
                        }

                        // If a doctor was specified.
                        if ($form_doctor) {
                            $query .= " AND ( b.provider_id = ? OR " .
                            "( ( b.provider_id IS NULL OR b.provider_id = 0 ) AND " .
                            "fe.provider_id = ? ) )";
                            array_push($sqlBindArray, $form_doctor, $form_doctor);
                        }

                        /**************************************************************/
                        //
                        $res = sqlStatement($query, $sqlBindArray);
                        while ($row = sqlFetchArray($res)) {
                            $trans_id = $row['trans_id'];
                            $patient_id = $row['pid'];
                            $encounter_id = $row['encounter'];
                            $patient_name = $row['pat_fulname'];
                            //
                            if (!empty($ids_to_skip[$trans_id])) {
                                continue;
                            }

                            //

                            if (empty($form_use_edate)) {
                                if (!empty($row['deposit_date'])) {
                                    $thedate = $row['deposit_date'];
                                } else {
                                    $thedate = substr($row['post_time'], 0, 10);
                                }
                            } elseif ($form_use_edate == 1) {
                                $thedate = substr($row['date'], 0, 10);
                            } elseif ($form_use_edate == 2) {
                                $thedate = substr($row['post_time'], 0, 10);
                            }

                            if (strcmp($thedate, $form_from_date) < 0 || strcmp($thedate, $form_to_date) > 0) {
                                continue;
                            }

                            //
                            // If a diagnosis code was given then skip any invoices without
                            // that diagnosis.
                            if ($form_dx_code && $form_dx_codetype) {
                                $tmp = sqlQuery("SELECT count(*) AS count FROM billing WHERE " .
                                "pid = ? AND encounter = ? AND " .
                                "code_type = ? AND code LIKE ? AND " .
                                "activity = 1", array($patient_id,$encounter_id,$form_dx_codetype,$form_dx_code));
                                if (empty($tmp['count'])) {
                                    $ids_to_skip[$trans_id] = 1;
                                    continue;
                                }
                            }

                            //
                            $docid = empty($row['encounter_id']) ? $row['docid'] : $row['encounter_id'];
                            $key = sprintf(
                                "%08u%s%08u%08u%06u",
                                $docid,
                                $thedate,
                                $patient_id,
                                $encounter_id,
                                ++$irow
                            );
                            $arows[$key] = array();
                            $arows[$key]['transdate'] = $thedate;
                            $arows[$key]['amount'] = 0 - $row['pay_amount'];
                            $arows[$key]['docid'] = $docid;
                            $arows[$key]['project_id'] = empty($row['payer_id']) ? 0 : $row['payer_id'];
                            $arows[$key]['memo'] = $row['code'];
                            if ($GLOBALS['cash_receipts_report_invoice'] == '0') {
                                $arows[$key]['invnumber'] = "$patient_id.$encounter_id";
                            } else {
                                $arows[$key]['invnumber'] = "$patient_name";
                            }

                            $arows[$key]['irnumber'] = $row['invoice_refno'];
                        } // end while

                        ksort($arows);
                        $docid = 0;

                        foreach ($arows as $row) {
                        // Get insurance company name
                            $insconame = '';
                            if ($form_proc_codefull  && $row['project_id']) {
                                $tmp = sqlQuery("SELECT name FROM insurance_companies WHERE " .
                                "id = ?", array($row['project_id']));
                                $insconame = $tmp['name'];
                            }

                            $amount1 = 0;
                            $amount2 = 0;
                            if ($form_procedures && is_clinic($row['memo'])) {
                                $amount2 -= $row['amount'];
                            } else {
                                $amount1 -= $row['amount'];
                            }

                        // if ($docid != $row['employee_id']) {
                            if ($docid != $row['docid']) {
                                if ($docid) {
                                    // Print doc totals.
                                    ?>
                    <div class="row bg-light">
                        <!-- date div for col spacing-->
                        <div class="col"></div>
                        <div class="col">
                                    <?php echo xlt('Totals for ') . text($docname) ?>
                        </div>
                <div class="col text-right">
                                    <?php echo text(FormatMoney::getBucks($doctotal1)) ?>
                </div>
                                    <?php if ($form_procedures) { ?>
                <div class="col text-right">
                                        <?php echo text(FormatMoney::getBucks($doctotal2)) ?>
                </div>
                    <?php } ?>
                    </div>
                                    <?php
                                }

                                $doctotal1 = 0;
                                $doctotal2 = 0;

                                $docid = $row['docid'];
                                $tmp = sqlQuery("SELECT lname, fname FROM users WHERE id = ?", array($docid));
                                $docname = empty($tmp) ? xl('Unknown') : $tmp['fname'] . ' ' . $tmp['lname'];

                                $docnameleft = $docname;
                            }

                            if ($_POST['form_details'] ?? '') {
                                ?>

                <div class="row">
                    <div class="col">
                                <?php echo text($docnameleft); $docnameleft = " " ?>
                    </div>
                <div class="col">
                                <?php echo text(oeFormatShortDate($row['transdate'])); ?>
                </div>
                                <?php if ($form_procedures) { ?>
                <div class="col">
                                    <?php echo empty($row['irnumber']) ? text($row['invnumber']) : text($row['irnumber']); ?>
                </div>
                    <?php } ?>
                                <?php
                                if ($form_proc_code && $form_proc_codetype) {
                                        echo "  <div class='col text-right'";
                                        list($patient_id, $encounter_id) = explode(".", $row['invnumber']);
                                        $tmp = sqlQuery("SELECT SUM(fee) AS sum FROM billing WHERE " .
                                            "pid = ? AND encounter = ? AND " .
                                            "code_type = ? AND code = ? AND activity = 1", array($patient_id,$encounter_id,$form_proc_codetype,$form_proc_code));
                                        echo text(FormatMoney::getBucks($tmp['sum']));
                                        echo "  </div>\n";
                                }
                                ?>
                                <?php
                                if ($form_proc_codefull) { ?>
                    <div class="col">
                                        <?php echo text($insconame) ?>
                    </div>
                                    <?php } ?>
                                <?php if ($form_procedures) { ?>
                <div class="col">
                                    <?php echo text($row['memo']) ?>
                </div>
                            <?php } ?>
                <div class="col text-right">
                                <?php echo text(FormatMoney::getBucks($amount1)) ?>
                </div>
                                <?php if ($form_procedures) { ?>
                <div class="col text-right">
                                    <?php echo text(FormatMoney::getBucks($amount2)) ?>
                </div>
                            <?php } ?>
                </div>
                                <?php
                            } // end details
                            $doctotal1   += $amount1;
                            $doctotal2   += $amount2;

                            $grandtotal1 = $grandtotal1 ?? null;
                            $grandtotal1 += $amount1;

                            $grandtotal2 = $grandtotal2 ?? null;
                            $grandtotal2 += $amount2;
                        }
                        ?>
                <div class="row bg-light">
                <div class="col">
                        <?php echo xlt('Totals for ') . text($docname ?? '') ?>
                </div>
                <!-- date div for col spacing-->
                <div class="col"></div>
                        <?php if ($form_procedures) { ?>
                    <!-- proc div for col spacing-->
                    <div class="col"></div>
                    <!-- inv div for col spacing-->
                    <div class="col"></div>
                <?php } ?>
                <div class="col text-right">
                        <?php echo text(FormatMoney::getBucks($doctotal1 ?? '')) ?>
                </div>
                        <?php if ($form_procedures) { ?>
                <div class="col text-right">
                            <?php echo text(FormatMoney::getBucks($doctotal2)) ?>
                </div>
                <?php } ?>
                </div>

                <div class="row bg-secondary">
                <div class="col">
                        <?php echo xlt('Grand Totals') ?>
                </div>
                <!-- date div for col spacing-->
                <div class="col"></div>
                        <?php if ($form_procedures) { ?>
                    <!-- proc div for col spacing-->
                    <div class="col"></div>
                    <!-- inv div for col spacing-->
                    <div class="col"></div>
                <?php } ?>
                <div class="col text-right">
                        <?php echo text(FormatMoney::getBucks($grandtotal1 ?? '')) ?>
                </div>
                        <?php if ($form_procedures) { ?>
                <div class="col text-right">
                            <?php echo text(FormatMoney::getBucks($grandtotal2)) ?>
                </div>
                <?php } ?>
                </div>
                        
                        <?php
                    }
                    ?>

                </div>
                </div>
                <?php } else { ?>
                <div class="info mt-3 text-center">
                    <?php
                        echo xlt('Please input search criteria above, and click Submit to view results.');
                }    ?>

                </form>
        </div>
    </div>
</div>


</body>

</html>
