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


/*if (!empty($_POST)) {
    if (!verifyCsrfToken($_POST["csrf_token_form"])) {
        csrfNotVerified();
    }
}*/

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
$form_ra        = $_POST['form_ra'];
$form_results   = $_POST['form_results'];
$form_new_patients = $_POST['form_new_patients'] ? true : false;
$form_esigned   = $_POST['form_esigned'] ? true : false;
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

<form method='post' name='theform' id='theform' action='diers_encounters_report.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php // echo attr(collectCsrfToken()); ?>" />

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
               <input type='text' class='datepicker form-control' name='form_from_date' id="form_from_date" size='10' value='20191201'>
            </td>
            <td class='control-label'>
                <?php echo xlt('To'); ?>:
            </td>
            <td>
               <input type='text' class='datepicker form-control' name='form_to_date' id="form_to_date" size='10' value='20191207'>
            </td>
        </tr>
    <tr>
      <td></td>
      <td>
        <div class="checkbox">
          <label><input type='checkbox' name='form_details' checked<?php echo ($form_details) ? ' checked' : ''; ?>>
            <?php echo xlt('Details'); ?></label>
        </div>
        <div class="checkbox">
          <label><input type='checkbox' name='form_new_patients' title='<?php echo xla('First-time visits only'); ?>'<?php echo ($form_new_patients) ? ' checked' : ''; ?>>
            <?php  echo xlt('New'); ?></label>
        </div>
          <div class="checkbox">
              <label><input type='checkbox' name='form_ra' checked title='<?php echo xla('Pain assess'); ?>'<?php echo ($form_ra) ? ' checked' : ''; ?>>
                  <?php  echo xlt('RA patients only'); ?></label>
          </div>
          <div class="checkbox">
              <label><input type='checkbox' name='form_results' title='<?php echo xla('Pain assess'); ?>'<?php echo ($form_results) ? ' checked' : ''; ?>>
                  <?php  echo xlt('with results'); ?></label>
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
          <?php echo ($form_orderby == "pubpid") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('Patient ID'); ?></a>
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
            <?php echo ($form_orderby == "time") ? " style=\"color:#00cc00\"" : ""; ?>><?php echo xlt('DOS'); ?></a>
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
        <?php echo 'pt has care';?>
    </td>
    <td>
        <?php echo 'pt has caid';?>
    </td>
    <td>
        <?php echo 'primary language';?>
    </td>

  <th>
    <?php echo xlt('CPT'); ?>
  </th>
  <th>
    <?php echo xlt('ICD10'); ?>
  </th>
  <th bgcolor="gray">
    <?php echo xlt('Codes'); ?>
  </th>
    <th bgcolor="#8b0000">
        <?php echo xlt('Codes'); ?>
    </th>
    <th bgcolor="#8b0000">
        <?php echo xlt('Codes'); ?>
    </th>
    <th bgcolor="#adff2f">
        <?php echo xlt('Codes'); ?>
    </th>
    <th bgcolor="#00008b">
        <?php echo xlt('Codes'); ?>
    </th>
    <th bgcolor="#00008b">
        <?php echo xlt('Modifier'); ?>
    </th>
    <th>
        <?php echo xlt('Codes'); ?>
    </th>
    <th>
        <?php echo xlt('Codes'); ?>
    </th>
    <th>
        <?php echo "Codes"; ?>
    </th>
    <th>
        <?php echo 'Modifier'; ?>
    </th>
    <th>
        <?php echo "Codes htn"; ?>
    </th>
    <th>
        <?php echo xlt('Codes #236'); ?>
    </th>
    <th>
        <?php echo xlt('Codes #236'); ?>
    </th>
    <th>
        <?php echo xlt('Codes #374'); ?>
    </th>
    <th>
        <?php echo xlt('Codes #374'); ?>
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
    $ra_dx = "M05.00, M05.011, M05.012, M05.019, M05.021," .
"M05.022, M05.029, M05.031, M05.032, M05.039, M05.041, M05.042, M05.049, M05.051, M05.052," .
"M05.059, M05.061, M05.062, M05.069, M05.071, M05.072, M05.079, M05.09, M05.111, M05.112," .
"M05.119, M05.121, M05.122, M05.129, M05.131, M05.132, M05.139, M05.141, M05.142, M05.149," .
"M05.151, M05.152, M05.159, M05.161, M05.162, M05.169, M05.171, M05.172, M05.179, M05.19, M05.20," .
"M05.211, M05.212, M05.219, M05.221, M05.222, M05.229, M05.231, M05.232, M05.239, M05.241," .
"M05.242, M05.249, M05.251, M05.252, M05.259, M05.261, M05.262, M05.269, M05.271, M05.272," .
"M05.279, M05.29, M05.30, M05.311, M05.312, M05.319, M05.321, M05.322, M05.329, M05.331, M05.332," .
"M05.339, M05.341, M05.342, M05.349, M05.351, M05.352, M05.359, M05.361, M05.362, M05.369," .
"M05.371, M05.372, M05.379, M05.39, M05.40, M05.411, M05.412, M05.419, M05.421, M05.422, M05.429," .
"M05.431, M05.432, M05.439, M05.441, M05.442, M05.449, M05.451, M05.452, M05.459, M05.461," .
"M05.462, M05.469, M05.471, M05.472, M05.479, M05.49, M05.50, M05.511, M05.512, M05.519, M05.521," .
"M05.522, M05.529, M05.531, M05.532, M05.539, M05.541, M05.542, M05.549, M05.551, M05.552," .
"M05.559, M05.561, M05.562, M05.569, M05.571, M05.572, M05.579, M05.59, M05.60, M05.611, M05.612," .
"M05.619, M05.621, M05.622, M05.629, M05.631, M05.632, M05.639, M05.641, M05.642, M05.649," .
"M05.651, M05.652, M05.659, M05.661, M05.662, M05.669, M05.671, M05.672, M05.679, M05.69, M05.70," .
"M05.711, M05.712, M05.719, M05.721, M05.722, M05.729, M05.731, M05.732, M05.739, M05.741," .
"M05.742, M05.749, M05.751, M05.752, M05.759, M05.761, M05.762, M05.769, M05.771, M05.772," .
"M05.779, M05.79, M05.80, M05.811, M05.812, M05.819, M05.821, M05.822, M05.829, M05.831, M05.832,".
"M05.839, M05.841, M05.842, M05.849, M05.851, M05.852, M05.859, M05.861, M05.862, M05.869," .
"M05.871, M05.872, M05.879, M05.89, M05.9, M06.00, M06.011, M06.012, M06.019, M06.021, M06.022," .
"M06.029, M06.031, M06.032, M06.039, M06.041, M06.042, M06.049, M06.051, M06.052, M06.059," .
"M06.061, M06.062, M06.069, M06.071, M06.072, M06.079, M06.08, M06.09, M06.1, M06.30, M06.311," .
"M06.312, M06.319, M06.321, M06.322, M06.329, M06.331, M06.332, M06.339, M06.341, M06.342," .
"M06.349, M06.351, M06.352, M06.359, M06.361, M06.362, M06.369, M06.371, M06.372, M06.379, " .
"M06.38, M06.39, M06.80, M06.811, M06.812, M06.819, M06.821, M06.822, M06.829, M06.831, M06.832," .
"M06.839, M06.841, M06.842, M06.849, M06.851, M06.852, M06.859, M06.861, M06.862, M06.869," .
"M06.871, M06.872, M06.879, M06.88, M06.89, M06.9";
    $pieces = explode(", ", $ra_dx);
    //var_dump($pieces);
    $icd10 = '';
    while ($row = sqlFetchArray($res)) {
        $patient_id = $row['pid'];
        $mips_enc_date = substr($row['date'], 0, 10);
        //error_log("mips_enc_date is " . $mips_enc_date . " for pt id " . $row['pubpid']);

        if ($patient_id == $prior_pt) {
        //    error_log("we're continuing past $patient_id");
            continue;
        }

        $prior_pt = $patient_id;

        if ($row['age'] < 18 ) {
            echo "youngin " .$row['pubpid'] . ' ' . $row['age'] . "\n";
            continue;
        }

        if ($row['age'] >= 65 &&  $row['age'] <= 85 && $row['sex'] == 'Female') {
            //echo "here's a lady we all know";
            $dxa_pt = true;
            $dexa++;
            //error_log("$patient_id qualifies for dxa");
        } else {
            $dxa_pt = false;
        }


        $dres = sqlStatement("SELECT code from billing as b WHERE b.code_type = 'ICD10' " .
                            "AND b.pid = ?", array($patient_id));
        $match = false;
        $counter = 0;
        $icd10_ra = false;

        while ($drow = sqlFetchArray($dres)) {
            //++$counter;
            $icd10 = $drow['code'];
            //if ($patient_id = 6114) {
            //    error_log("counter is $counter and $patient_id has $icd10");
                //var_dump($drow);
                //exit;
            //}
            if (in_array("$icd10", $pieces)) {
            //    error_log("$icd10 is in pieces aray");
                $icd10_ra = true;
                //$match = true;
                continue;
            } else {
                //error_log("$patient_id code $drow[code] is not RA");
                $icd10_ra = false;
            };
        }

        $ra_row = '';
        $ra_res = sqlStatement("SELECT title, enddate FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
            "title LIKE '%RHEUMATOID ARTHRITIS%'", array($patient_id));
        $ra_row = sqlFetchArray($ra_res);

        if ($ra_row && !$icd10_ra) {
            $icd10 = "M06.9";
            $icd10_ra = true;
            //error_log("using M06.9 since $patient_id has $ra_row[title] but not in cms charges");
        }

        if ($icd10_ra) {
            $ra_pt++;
            //echo $ra_pt;
        }

        if (!$icd10_ra && $form_ra) {
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
                        $ins_code = $mrow['provider'];
                        //echo "ins code is . $ins_code";
                        if ($ins_code == '003') {
                            echo "Yes";
                        } else if ($ins_code == '116' && $row['age'] >= 65) {
                            echo "Yes";
                        } else if ($ins_code == '475' && $row['age'] >= 65) {
                            echo "Yes";
                        } else {
                            echo "No ";
                            if ($row['age'] >= 65) {
                                //error_log("check $patient_id since not medicare but 65 years or older");
                            }
                        }?>

                    </td>
                    <td>
                        <?php //echo 'Medicaid pt';
                        if ($mrow['provider'] == '004') {
                            echo "Yes";
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
                        <?php echo $icd10; // here's the dx ?>
                    </td>

                <?php
                //if ($form_pain) {
                //    $rpd_where = "AND item = 'act_pain'";
                //}

                $rres = sqlStatement("SELECT * from rule_patient_data as rpd WHERE rpd.pid = ? " . $rpd_where .
                    "AND rpd.date > '2018-12-31' AND rpd.date < '2019-12-31' ORDER BY item ASC", array($patient_id));
                $rpd_data = 0; // indicate if pt has any rpd data
                $qpp = array();
                $qpp['39'] = '<td></td>';
                $qpp['176'] = '<td></td>';
                $qpp['177'] = '<td></td>';
                $qpp['178'] = '1170F</td><td>8P';
                $qpp['179'] = '3475F</td><td>8P';
                // intialize due to performance hcpcs with glucocorticoid
                $qpp['180'] = '</td><td>4194F</td><td>8P';
                while ($rrow = sqlFetchArray($rres)) {
                    ++$rpd_data;
                    $item = $rrow['item'];
                    $result = $rrow['result'];
                    //error_log("rpd date is " . $rrow['date'] . " for pt id " .
                    //    $row['pubpid'] . " item is " . $item);
                    //if (!substr($rrow['date'], 0, 10) == $mips_enc_date) {
                    //    error_log($rrow['pid'] . "has rpd on " . $rrow['date'] . " but not on date of encounter " . $mips_enc_date);
                    //    continue;
                    //}
                    $dexa_res = sqlStatement("SELECT * FROM `rule_patient_data` WHERE `item` = 'act_osteo' and `pid` = ?", array($patient_id));
                    $dexa_row = sqlFetchArray($dexa_res);
                    if ($dexa_row) {  // quality id 39, nqf 0046
                        //echo $result;
                        $pos1 = stripos($dexa_row['result'], "NEEDS"); // there's been a DXA
                        if ($pos1 !== false) {
                            $qpp['39'] = 'G8400';
                        } else {
                            $qpp['39'] = 'G8399';
                        }
                    } else {
                        $qpp['39'] = '';
                    }

                    //SELECT * FROM `rule_patient_data` WHERE `item` = 'act_tb' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($item == 'act_tb') { // quality id 176
                        //echo "there's an act_tb $result";
                        $pos1 = stripos("$result", "19");
                        if ($pos1 !== false) {
                            $qpp['176'] = 'M1003</td><td>';
                            continue;

                        } else {
                            $qpp['176'] = "</td><td>";
                            continue;
                        }
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_cdai' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($item == 'act_cdai') { // quality id 177
                        error_log("in measure 177 logic with result $result");
                        $qpp['177'] = 'M1007</td><td>';
                    } else {
                        $qpp['177'] = 'M1006</td><td>';
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_rafunc' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($item == 'act_rafunc') { //quality id 178
                        $qpp['178'] = '1170F<td></td>';
                        continue;
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_disease_prog' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($item == 'act_disease_prog') { //quality id 179
                        $pos1 = stripos("$result", "positive"); // poor prognosis
                        $pos2 = stripos("$result", "poor"); // poor prognosis
                        $pos3 = stripos("$result", "seronegative"); // good prognosis
                        if ($pos1 !== false || $pos2 !== false) {
                            $qpp['179'] = '3475F<td></td>';
                            continue;
                        } else if ($pos3 !== false) {
                            $qpp['179'] = '3476F<td></td>';
                            continue;
                        } else {
                            //echo "$patient_id there's an act_ ". $result;
                            $qpp['179'] = '3475F<td></td>';
                            continue;
                        }
                    }

                    // SELECT * FROM `rule_patient_data` WHERE `item` = 'act_glucocorticoid' and date > '2017-12-31 23:59:59' and date < '2019-01-01 00:00:00' ORDER BY `date` DESC
                    if ($item == 'act_glucocorticoid') { // quality id 180
                        $pos1 = stripos("$result", "no"); //
                        $pos2 = stripos("$result", "low-dose"); // < 10 mg qd
                        $pos3 = stripos("$result", "tapering"); //
                        $pos4 = stripos("$result", "prednisone");
                        $pos5 = stripos("$result", "off");
                        $pos6 = stripos("$result", "rare");
                        $pos7 = stripos("$result", "chronic");

                        if ($pos1 !== false || $pos5 !== false) {
                            $qpp['180'] = "</td><td>4192F<td>";
                            continue;
                        } else if ($pos2 !== false || $pos4 !== false || $pos6 !== false) {
                            $qpp['180'] = '</td><td>4193F<td>';
                            continue;
                        } else if ($pos3 !== false || $pos7 !== false) {
                            $qpp['180'] = '0540F</td><td>4194F<td>';
                                continue;
                        } else {
                            $qpp['180'] = "</td><td>4192F<td>";
                            continue;
                        }
                    }
            }
            ksort($qpp);

            if ($icd10_ra) {
                foreach ($qpp as $meas => $meas_val) {
                    if (!$form_results) {
                        echo "<td> $meas_val </td>";
                    } else {
                        //echo ;
                    }
                }
            } else {
                echo "<td bgcolor='gray'>$qpp[39]</td>";
                for ($i=0; $i < 11; $i++) {
                    echo "<td>$icd10_ra</td>";
                }
            }

            // quality id 236 NQF 0018
            $htn_res = sqlStatement("SELECT title, enddate FROM lists WHERE pid = ? AND type = 'medical_problem' AND " .
                        "title LIKE '%HYPERTENSION%' LIMIT 1 ", array($patient_id));
            //error_log("med problem is " . $htn_row['title']);
            $htn_row = sqlFetchArray($htn_res);
            if ($htn_row) {
                echo "<td>I10</td>";
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
                        echo "<td>G8752</td>";
                    } else {
                        echo "<td>G8753</td>";
                    }
                } else {

                    echo "<td>G8756</td>";
                }

                if ($bpd_pt != '') {
                    if ($bpd_test) {
                        echo "<td>G8754</td>";
                    } else {
                        echo "<td>G8755</td>";
                    }
                } else {
                    echo "<td>bpd blank</td>";
                }
            } else {
                echo "<td></td><td></td><td></td>";
            }

            ?>
                        <?php echo "<td>G9968</td><td>G9969</td>"; // quality id 374 act_ref_sent_sum ?>
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
//echo "dexa count is $dexa";
//echo " ra pt count is $ra_pt";
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
