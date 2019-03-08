<?php
/**
 *  Encounters report.
 *
 *  This report shows past encounters with filtering and sorting,
 *  Added filtering to show encounters not e-signed, encounters e-signed and forms e-signed.
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Terry Hill <terry@lilysystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2007-2016 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2015 Terry Hill <terry@lillysystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */


require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

use OpenEMR\Billing\BillingUtilities;
use OpenEMR\Core\Header;

set_time_limit(0);


if (!empty($_POST)) {
    if (!verifyCsrfToken($_POST["csrf_token_form"])) {
        csrfNotVerified();
    }
}

$alertmsg = ''; // not used yet but maybe later

// For each sorting option, specify the ORDER BY argument.
//
$ORDERHASH = array(
  'doctor'  => 'lower(u.lname), lower(u.fname), fe.date',
  'patient' => 'lower(p.lname), lower(p.fname), fe.date',
  'pubpid'  => 'lower(p.pubpid), fe.date',
  'time'    => 'fe.date, lower(u.lname), lower(u.fname)',
  'encounter'    => 'fe.encounter, fe.date, lower(u.lname), lower(u.fname)',
);

function show_doc_total($lastdocname, $doc_encounters)
{
    if ($lastdocname) {
        echo " <tr>\n";
        echo "  <td class='detail'>" .  text($lastdocname) . "</td>\n";
        echo "  <td class='detail' align='right'>" . text($doc_encounters) . "</td>\n";
        echo " </tr>\n";
    }
}

$form_from_date = (isset($_POST['form_from_date'])) ? DateToYYYYMMDD($_POST['form_from_date']) : date('Y-m-d');
$form_to_date   = (isset($_POST['form_to_date'])) ? DateToYYYYMMDD($_POST['form_to_date']) : date('Y-m-d');
$form_provider  = $_POST['form_provider'];
$form_facility  = $_POST['form_facility'];
$form_details   = $_POST['form_details'] ? true : false;
$form_pain      = $_POST['form_pain'];
$form_new_patients = $_POST['form_new_patients'] ? true : false;
$form_esigned = $_POST['form_esigned'] ? true : false;
$form_not_esigned = $_POST['form_not_esigned'] ? true : false;
$form_encounter_esigned = $_POST['form_encounter_esigned'] ? true : false;

$form_orderby = $ORDERHASH[$_REQUEST['form_orderby']] ?
$_REQUEST['form_orderby'] : 'doctor';
//$orderby = $ORDERHASH[$form_orderby];
$orderby = "pid";

// Get the info.
//
$esign_fields = '';
$esign_joins = '';
if ($form_encounter_esigned) {
    $esign_fields = ", es.table, es.tid ";
    $esign_joins = "LEFT OUTER JOIN esign_signatures AS es ON es.tid = fe.encounter ";
}

if ($form_esigned) {
    $esign_fields = ", es.table, es.tid ";
    $esign_joins = "LEFT OUTER JOIN esign_signatures AS es ON es.tid = fe.encounter ";
}

if ($form_not_esigned) {
    $esign_fields = ", es.table, es.tid ";
    $esign_joins = "LEFT JOIN esign_signatures AS es on es.tid = fe.encounter ";
}

$sqlBindArray = array();

$query = "SELECT " .
  "fe.encounter, fe.date, fe.reason, " .
  "f.formdir, f.form_name, " .
  "p.fname, p.mname, p.lname, p.pid, p.pubpid, p.dob, p.sex, " .
   "TIMESTAMPDIFF(YEAR, p.dob, fe.date) AS age, " .
  "u.lname AS ulname, u.fname AS ufname, u.mname AS umname " .
  "$esign_fields" .
  "FROM ( form_encounter AS fe, forms AS f ) " .
  "LEFT OUTER JOIN patient_data AS p ON p.pid = fe.pid " .
  "LEFT JOIN users AS u ON u.id = fe.provider_id " .
  "$esign_joins" .
  "WHERE f.pid = fe.pid AND f.encounter = fe.encounter AND f.formdir = 'newpatient' ";
if ($form_to_date) {
    $query .= "AND fe.date >= ? AND fe.date <= ? ";
    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_to_date . ' 23:59:59');
} else {
    $query .= "AND fe.date >= ? AND fe.date <= ? ";
    array_push($sqlBindArray, $form_from_date . ' 00:00:00', $form_from_date . ' 23:59:59');
}

if ($form_provider) {
    $query .= "AND fe.provider_id = ? ";
    array_push($sqlBindArray, $form_provider);
}

if ($form_facility) {
    $query .= "AND fe.facility_id = ? ";
    array_push($sqlBindArray, $form_facility);
}

if ($form_new_patients) {
    $query .= "AND fe.date = (SELECT MIN(fe2.date) FROM form_encounter AS fe2 WHERE fe2.pid = fe.pid) ";
}

if ($form_encounter_esigned) {
    $query .= "AND es.tid = fe.encounter AND es.table = 'form_encounter' ";
}

if ($form_esigned) {
    $query .= "AND es.tid = fe.encounter ";
}

if ($form_not_esigned) {
    $query .= "AND es.tid IS NULL ";
}

$query .= "ORDER BY $orderby";

$res = sqlStatement($query, $sqlBindArray);
?>
<html>
<head>
    <title><?php echo xlt('Encounters Report'); ?></title>

    <?php Header::setupHeader(['datetime-picker', 'report-helper']); ?>

    <style type="text/css">
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

    <script LANGUAGE="JavaScript">
        $(document).ready(function() {
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

        function dosort(orderby) {
            var f = document.forms[0];
            f.form_orderby.value = orderby;
            f.submit();
            return false;
        }

        function refreshme() {
            document.forms[0].submit();
        }
    </script>
</head>
<body class="body_top">
<!-- Required for the popup date selectors -->
<div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>

<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('Encounters'); ?></span>

<div id="report_parameters_daterange">
<?php echo text(oeFormatShortDate($form_from_date)) . " " . xlt('to') . " ". text(oeFormatShortDate($form_to_date)); ?>
</div>

<form method='post' name='theform' id='theform' action='encounters_report.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(collectCsrfToken()); ?>" />

<div id="report_parameters">
<table>
 <tr>
  <td width='550px'>
    <div style='float:left'>

    <table class='text'>
        <tr>
            <td class='control-label'>
                <?php echo xlt('Facility'); ?>:
            </td>
            <td>
            <?php dropdown_facility($form_facility, 'form_facility', true); ?>
            </td>
            <td class='control-label'>
                <?php echo xlt('Provider'); ?>:
            </td>
            <td>
                <?php

                 // Build a drop-down list of providers.
                 //

                 $query = "SELECT id, lname, fname FROM users WHERE ".
                  "authorized = 1 $provider_facility_filter ORDER BY lname, fname"; //(CHEMED) facility filter

                 $ures = sqlStatement($query);

                 echo "   <select name='form_provider' class='form-control'>\n";
                 echo "    <option value=''>-- " . xlt('All') . " --\n";

                while ($urow = sqlFetchArray($ures)) {
                    $provid = $urow['id'];
                    echo "    <option value='" . attr($provid) . "'";
                    if ($provid == $_POST['form_provider']) {
                        echo " selected";
                    }

                    echo ">" . text($urow['lname']) . ", " . text($urow['fname']) . "\n";
                }

                 echo "   </select>\n";

                ?>
            </td>
        </tr>
        <tr>
            <td class='control-label'>
                <?php echo xlt('From'); ?>:
            </td>
            <td>
               <input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='<?php echo attr(oeFormatShortDate($form_from_date)); ?>'>
            </td>
            <td class='control-label'>
                <?php echo xlt('To'); ?>:
            </td>
            <td>
               <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='<?php echo attr(oeFormatShortDate($form_to_date)); ?>'>
            </td>
        </tr>
    <tr>
      <td></td>
      <td>
        <div class="checkbox">
          <label><input type='checkbox' name='form_details'<?php echo ($form_details) ? ' checked' : ''; ?>>
            <?php echo xlt('Details'); ?></label>
        </div>
        <div class="checkbox">
          <label><input type='checkbox' name='form_new_patients' title='<?php echo xla('First-time visits only'); ?>'<?php echo ($form_new_patients) ? ' checked' : ''; ?>>
            <?php  echo xlt('New'); ?></label>
        </div>
          <div class="checkbox">
              <label><input type='checkbox' name='form_pain' title='<?php echo xla('Pain assess'); ?>'<?php echo ($form_pain) ? ' checked' : ''; ?>>
                  <?php  echo xlt('Pain assess'); ?></label>
          </div>
      </td>
      <td></td>
      <td>
        <div class="checkbox">
          <label><input type='checkbox' name='form_esigned'<?php echo ($form_esigned) ? ' checked' : ''; ?>>
            <?php  echo xlt('Forms Esigned'); ?></label>
        </div>
        <div class="checkbox">
          <label><input type='checkbox' name='form_encounter_esigned'<?php echo ($form_encounter_esigned) ? ' checked' : ''; ?>>
            <?php  echo xlt('Encounter Esigned'); ?></label>
        </div>
        <div class="checkbox">
          <label><input type='checkbox' name='form_not_esigned'<?php echo ($form_not_esigned) ? ' checked' : ''; ?>>
            <?php echo xlt('Not Esigned'); ?></label>
        </div>
      </td>
    </tr>
  </table>

    </div>

  </td>
  <td align='left' valign='middle' height="100%">
    <table style='border-left:1px solid; width:100%; height:100%' >
        <tr>
            <td>
                <div class="text-center">
          <div class="btn-group" role="group">
                      <a href='#' class='btn btn-default btn-save' onclick='$("#form_refresh").attr("value","true"); $("#theform").submit();'>
                            <?php echo xlt('Submit'); ?>
                      </a>
                        <?php if ($_POST['form_refresh'] || $_POST['form_orderby']) { ?>
              <a href='#' class='btn btn-default btn-print' id='printbutton'>
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

</div> <!-- end report_parameters -->

<?php
if ($_POST['form_refresh'] || $_POST['form_orderby']) {
?>
<div id="report_results">
<table id='mymaintable'>
<thead>
<?php if ($form_details) { ?>
    <th>
        <a href="nojs.php" onclick="return dosort('pubpid')"
          <?php echo ($form_orderby == "pubpid") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('pub pID'); ?></a>
    </th>
    <th>
        <a href="nojs.php" onclick="return dosort('pubpid')"
          <?php echo ($form_orderby == "pubpid") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('Medicare ID'); ?></a>
    </th>

  <th>
      <?php echo xlt('First Name');?>
  </th>
    <th>
        <a href="nojs.php" onclick="return dosort('Last Name')"
            <?php echo ($form_orderby == "patient") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('Last Name'); ?></a>
    </th>
  <th>
      <?php echo xlt('DOB');?>
  </th>
      <?php //echo xlt('Age'); ?>
  <th>
      <?php echo xlt('Sex'); ?>
  </th>
    <th>
        <a href="nojs.php" onclick="return dosort('time')"
            <?php echo ($form_orderby == "time") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('Date'); ?></a>
    </th>
    <td>
        <?php echo 'NPI';?>
    </td>
    <td>
        <?php echo 'Race Code';?>
    </td>
    <td>
        <?php echo 'Ethnic';?>
    </td>
    <td>
        <?php echo 'Medicare pt';?>
    </td>
    <td>
        <?php echo 'Medicaid pt';?>
    </td>
    <td>
        <?php echo 'primary language';?>
    </td>

  <th>
    <?php echo xlt('CPT'); ?>
  </th>
    <th>
        <?php echo xlt('Modifier'); ?>
    </th>
  <th>
    <?php echo xlt('ICD10'); ?>
  </th>
  <th>
    <?php echo xlt('DXA #39'); ?>
  </th>

  <th>
    <?php echo xlt('TB #176'); ?>
  </th>
    <th>
        <?php echo xlt('CDAI #177'); ?>
    </th>
    <th>
        <?php echo xlt('Func #178'); ?>
    </th>
    <th>
        <?php echo xlt('Prog #179'); ?>
    </th>
    <th>
        <?php echo xlt('Gluco #180'); ?>
    </th>
    <th>
        <?php echo xlt('BP #236 sys'); ?>
    </th>
    <th>
        <?php echo xlt('BP #236 dia'); ?>
    </th>
    <th>
        <?php echo xlt('Referral #374'); ?>
    </th>
    <th>
        <?php echo xlt('Result date'); ?>
    </th>
    <th>
        <?php echo "blood pressure"; ?>
    </th>
  <th>
    <a href="nojs.php" onclick="return dosort('doctor')"
            <?php //echo ($form_orderby == "doctor") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('Encounter #'); ?> </a>
  </th>
    <th>
        <a href="nojs.php" onclick="return dosort('pubpid')"
            <?php echo ($form_orderby == "pubpid") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('PID'); ?></a>
    </th>

<?php } else { ?>
  <th><?php echo xlt('Provider'); ?></td>
  <th><?php echo xlt('Encounters'); ?></td>
<?php } ?>
</thead>
<tbody>
<?php
if ($res) {
    $lastdocname = "";
    $doc_encounters = 0;
    $prior_pt = '';

    while ($row = sqlFetchArray($res)) {
        $patient_id = $row['pid'];
        $mips_enc_date = substr($row['date'], 0, 10);
        //error_log("mips_enc_date is " . $mips_enc_date . " for pt id " . $row['pubpid']);

        if ($patient_id == $prior_pt) {
            //error_log("we're continuing");
            continue;
        }

        $prior_pt = $patient_id;

        if ($row['age'] < 18 ) {
            echo "youngin " .$row['pubpid'] . ' ' . $row['age'] . "\n";
            continue;
        }

        $docname = '';
        if (!empty($row['ulname']) || !empty($row['ufname'])) {
            $docname = $row['ulname'];
            if (!empty($row['ufname']) || !empty($row['umname'])) {
                $docname .= ', ' . $row['ufname'] . ' ' . $row['umname'];
            }
        }

            $errmsg  = "";
            $out = "";
            if ($form_details) {
                // Fetch all other forms for this encounter.
                $encnames = '';
                $encarr = getFormByEncounter(
                    $patient_id,
                    $row['encounter'],
                    "formdir, user, form_name, form_id"
                );

                $vitals_id = '';
                if ($encarr!='') {
                    foreach ($encarr as $enc) {
                        if ($enc['formdir'] == 'newpatient') {
                            //$encnames .= '<br />';
                            continue;
                        }

                        if ($enc['formdir'] == 'vitals') {
                            $vitals_id = $enc['form_id'];
                           // error_log("vitals form id is " . $vitals_id . " for pt " . $patient_id);
                        }

                        if ($encnames) {
                            $encnames .= '<br />';
                        }

                        $encnames .= text($enc['form_name']); // need to html escape it here for output below
                    }
                }

                // Fetch coding and compute billing status.
                $coded = "";
                $billed_count = 0;
                $unbilled_count = 0;
                /*if ($billres = BillingUtilities::getBillingByEncounter(
                    $row['pid'],
                    $row['encounter'],
                    "code_type, code, code_text, billed")
                ) {
                    foreach ($billres as $billrow) {
                        // $title = addslashes($billrow['code_text']);
                        if ($billrow['code_type'] != 'COPAY' && $billrow['code_type'] != 'TAX') {
                            $coded .= $billrow['code'] . ', ';
                            if ($billrow['billed']) {
                                ++$billed_count;
                            } else {
                                ++$unbilled_count;
                            }
                        }
                    }

                    $coded = substr($coded, 0, strlen($coded) - 2);
                }

                // Figure product sales into billing status.
                $sres = sqlStatement("SELECT billed FROM drug_sales " .
                    "WHERE pid = ? AND encounter = ?", array($row['pid'], $row['encounter']));
                while ($srow = sqlFetchArray($sres)) {
                    if ($srow['billed']) {
                        ++$billed_count;
                    } else {
                        ++$unbilled_count;
                    }
                }*/

                // Compute billing status.
                /*if ($billed_count && $unbilled_count) {
                    $status = xl('Mixed');
                } else if ($billed_count) {
                    $status = xl('Closed');
                } else if ($unbilled_count) {
                    $status = xl('Open');
                } else {
                    $status = xl('Empty');
                }
                */
                ?>
                <tr bgcolor='<?php echo attr($bgcolor); ?>'>
                    <td>
                        <?php echo text(strtoupper($row['pubpid']));?>
                    </td>
                    <td>
                        <?php echo text(strtoupper($row['medicare id']));?>
                    </td>
                    <td>
                        <?php //echo text(strtoupper($row['fname']) . ' ' . text($row['mname']));?>
                    </td>
                    <td>
                        <?php //echo text(strtoupper($row['lname']));?>
                    </td>
                    <td>
                        <?php echo text($row['dob']);?>
                    </td>
                    <?php //echo text($row['age']);?>
                    <td>
                        <?php echo text(strtoupper($row['sex']));?>
                    </td>
                    <td>
                        <?php echo text(oeFormatShortDate(substr($row['date'], 0, 10)));?>
                    </td>
                    <td>
                        <?php //echo 'NPI';?>
                    </td>
                    <td>
                        <?php //echo 'Race Code';?>
                    </td>
                    <td>
                        <?php //echo 'Ethnic';?>
                    </td>
                    <td>
                        <?php //echo 'Medicare pt';
                        $mres = sqlStatement("SELECT provider from insurance_data as ins WHERE ins.type = 'primary' " .
                                                "AND ins.date > '2018-01-01' AND ins.pid = ?", array($patient_id));
                        $mrow = sqlFetchArray($mres);
                        //var_dump($mrow);
                        if ($mrow['provider'] == '003') {
                            echo "Yes";
                        } else {
                            echo "No ";
                        }?>

                    </td>
                    <td>
                        <?php //echo 'Medicaid pt';
                        if ($mrow['provider'] == '004') {
                            echo "$mrow[provider] Yes";
                        } else {
                            echo "No ";
                        }                        ?>
                    </td>
                    <td>
                        <?php //echo 'primary language';?>
                    </td>



                    <td>
                        <?php $bres = sqlStatement("SELECT code, modifier from billing as b WHERE b.code_type = 'CPT4' " .
                         "AND b.encounter = ?", array($row['encounter']));
                          $brow = sqlFetchArray($bres);
                          if ($brow['code']) {
                              echo $brow['code'];
                          } else {
                              echo '99214';
                          };?>
                    </td>
                    <td>
                         <?php echo $brow['modifier'];?>
                    </td>
                    <td>
                        <?php $dres = sqlStatement("SELECT code from billing as b WHERE b.code_type = 'ICD10' " .
                            "AND b.encounter = ?", array($row['encounter']));
                        $drow = sqlFetchArray($dres);
                        if ($drow['code']) {
                        echo $drow['code'];
                        } else {
                        echo 'M06.9';
                        };?>
                    </td>

                <?php
                //if ($form_pain) {
                //    $rpd_where = "AND item = 'act_pain'";
                //}

                $rres = sqlStatement("SELECT * from rule_patient_data as rpd WHERE rpd.pid = ? " . $rpd_where .
                    "AND rpd.date > '2017-12-31' AND rpd.date < '2019-01-01' ORDER BY item ASC", array($patient_id));
                $rpd_data = 0; // indicate if pt has any rpd data
                $qpp = array();
                $qpp['39']  = '';
                $qpp['176'] = '';
                $qpp['177'] = '';
                $qpp['178'] = '';
                $qpp['179'] = '';
                $qpp['180'] = '';

                while ($rrow = sqlFetchArray($rres)) {
                    ++$rpd_data;
                    //error_log("rpd date is " . $rrow['date'] . " for pt id " .
                    //    $row['pubpid'] . " item is " . $rrow['item']);
                    //if (!substr($rrow['date'], 0, 10) == $mips_enc_date) {
                    //    error_log($rrow['pid'] . "has rpd on " . $rrow['date'] . " but not on date of encounter " . $mips_enc_date);
                    //    continue;
                    //}
                    //SELECT * FROM `rule_patient_data` WHERE `item` = 'act_osteo' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_osteo') {  // quality id 39, nqf 0046
                        if (strpos($rrow['result'], "/")) {
                            $qpp['39'] = 'G8399';
                            continue;
                        } else {
                            $qpp['39'] = 'G8400';
                            continue;
                        }
                    }

                    //SELECT * FROM `rule_patient_data` WHERE `item` = 'act_tb' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_tb') { // quality id 176
                        //echo "there's an act_tb $rrow[result]";
                        if (strpos($rrow['result'], "18")) {
                            $qpp['176'] = '3455F';
                            continue;

                        } else {
                            $qpp['176'] = "";
                            continue;
                        }
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_cdai' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_cdai') { // quality id 177
                        if ($rrow['result'] <= 10) {
                            $qpp['177'] = '3470F';
                            continue;
                        } else if (($rrow['result'] > 10) && ($rrow['result'] <= 22)) {
                            $qpp['177'] = '3471F';
                            continue;
                        } else {
                            $qpp['177'] = '3472F';
                            continue;
                        }
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_rafunc' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_rafunc') { //quality id 178
                        $qpp['178'] = '1170F';
                        continue;
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_disease_prog' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_disease_prog') { //quality id 179
                        $pos1 = stripos($rrow['result'], "positive"); // poor prognosis
                        $pos2 = stripos($rrow['result'], "poos"); // poor prognosis
                        $pos3 = stripos($rrow['result'], "seronegative"); // good prognosis
                        if (strpos(strtolower($rrow['result']), "positive") || strpos(strtolower($rrow['result']), "poor")) {
                            $qpp['179'] = '3475F';
                            continue;
                        } else if (strpos($rrow['result'], "seronegative")) {
                            $qpp['179'] = '3476F';
                            continue;
                        } else {
                            echo "$patient_id there's an act_ ". stripos($rrow['result'], "poor");
                            $qpp['179'] = 'mod 179?';
                            continue;
                        }
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_glucocorticoid' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($rrow['item'] == 'act_glucocorticoid') { // quality id 180
                        if (strpos($rrow['result'], 'no steroids')) {
                            $qpp['180'] = '4192F';
                            continue;
                        } else if (strpos($rrow['result'], "low-dose")) {
                            $qpp['180'] = '4193F';
                            continue;
                        } else if (strpos($rrow['result'], "risks")) {
                            $qpp['180'] = '4194F';
                                continue;
                        } else {
                            $qpp['180'] = '180 mod?';
                                continue;
                        }
                    }
            }
            ksort($qpp);

            foreach($qpp as $meas => $meas_val) {
                echo "<td> $meas_val </td>";
            }

            echo $out; // now print it

            // quality id 236 NQF 0018
            $query_htn = "SELECT title, enddate FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
                        "title LIKE '%HYPERTENSION%' LIMIT 1 ";
            $htn_res = sqlStatement($query_htn, array($patient_id));
            //error_log("med problem is " . $htn_row['title']);
            $bps_pt = '';
            $bpd_pt = '';
            $bps_mips = 140;
            $bpd_mips = 90;
            $query_bp = "SELECT bps, bpd FROM form_vitals WHERE id = ?";
            $bp_res = sqlStatement($query_bp, array($vitals_id));
            $bp_row = sqlFetchArray($bp_res);
            $bps_pt = $bp_row['bps'];
            $bps_test = ($bps_pt < $bps_mips);
            $bpd_test = ($bpd_pt < $bpd_mips);
                //? "true </br>" : "false </br>";
            $bpd_pt = $bp_row['bpd'];

            //echo "$patient_id systolic " . $bps_pt . " diastolic " . $bpd_pt . "</br>";
            if ($bps_pt != '') {
                if ($bps_test) {
                    //echo "$patient_id is $bps_pt less than $bps_mips";
                    echo "<td> G8752 </td>";
                } else {
                    echo "<td> G8753 </td>";
                }
            } else {
                    echo "<td> G8756 </td>";
            }

            if ($bpd_pt != '') {
                if ($bpd_test) {
                    echo "<td> G8754 </td>";
                } else {
                    echo "<td> G8755 </td>";
                }
            } else {
                echo "<td></td>";
            }

            ?>

                    </td>
                    <td>
                        <?php echo text('G9969'); // quality id 374 act_ref_sent_sum ?>
                    </td>
                    <td>
                        <?php //echo text('Modifier'); ?>
                    </td>
                    <td>
                        <?php
                        echo $rrow['result'];
                        ?>
                    </td>
                    <td>
                        <?php
                        echo $rrow['date'];
                        ?>
                    </td>
                    <td>
                        <?php echo text($row['encounter']);
                        //echo ($docname == $lastdocname) ? "" : text($docname) ?>
                    </td>
                    <td>
                        <?php echo text(strtoupper($row['pid']));?>
                    </td>
                </tr>
                <?php
            } else {
                if ($docname != $lastdocname) {
                    show_doc_total($lastdocname, $doc_encounters);
                    $doc_encounters = 0;
                }

                ++$doc_encounters;
            }

            $lastdocname = $docname;

    }

    if (!$form_details) {
        show_doc_total($lastdocname, $doc_encounters);
    }
}
?>
</tbody>
</table>
</div>  <!-- end encresults -->
<?php } else { ?>
<div class='text'>
    <?php echo xlt('Please input search criteria above, and click Submit to view results.'); ?>
</div>
<?php } ?>

<input type="hidden" name="form_orderby" value="<?php echo attr($form_orderby) ?>" />
<input type='hidden' name='form_refresh' id='form_refresh' value=''/>

</form>
</body>

<script language='JavaScript'>
<?php if ($alertmsg) {
    echo " alert(" . js_escape($alertmsg) . ");\n";
} ?>
</script>
</html>
