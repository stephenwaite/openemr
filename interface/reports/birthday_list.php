<?php

/**
 * This report lists birthdays by month for matching entries in patient_data table
 *
 * @package   OpenEMR
 * @link      http://www.open-emr.org
 * @author    Rod Roark <rod@sunsetsystems.com>
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2006-2016 Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2017-2018 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once("../globals.php");
require_once("$srcdir/patient.inc.php");
require_once("$srcdir/options.inc.php");

use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Core\Header;

if (!empty($_POST)) {
    if (!CsrfUtils::verifyCsrfToken($_POST["csrf_token_form"])) {
        CsrfUtils::csrfNotVerified();
    }
}

$form_month  = (!empty($_POST['form_month'])) ? intval($_POST['form_month']) : 0;

// https://stackoverflow.com/questions/10829424/generating-a-list-of-month-names
$monthSet = array_map(
    fn(\DateTimeImmutable $dt): string => $dt->format('M'),
    iterator_to_array(new \DatePeriod(
        new \DateTimeImmutable('first day of jan'),
        new \DateInterval('P1M'),
        11,
    )),
);

// In the case of CSV export only, a download will be forced.
if (!empty($_POST['form_csvexport'])) {
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
    header("Content-Type: application/force-download");
    header("Content-Disposition: attachment; filename=birthday_list.csv");
    header("Content-Description: File Transfer");
} else {
    ?>
<html>
<head>
    <title><?php echo xlt('Birthday by Month List'); ?></title>
    <?php Header::setupHeader(['report-helper']); ?>

<script>

$(function () {
    oeFixedHeaderSetup(document.getElementById('mymaintable'));
    top.printLogSetup(document.getElementById('printbutton'));
});

</script>

<style>

/* specifically include & exclude from printing */
@media print {
    #report_parameters {
        visibility: hidden;
        display: none;
    }
    #report_results table {
       margin-top: 0px;
    }
}

/* specifically exclude some from the screen */
@media screen {
    #report_results {
        width: 100%;
    }
}

</style>

</head>

<body class="body_top">

<span class='title'><?php echo xlt('Report'); ?> - <?php echo xlt('Birthday by Month List'); ?></span>

<form name='theform' id='theform' method='post' action='birthday_list.php' onsubmit='return top.restoreSession()'>
<input type="hidden" name="csrf_token_form" value="<?php echo attr(CsrfUtils::collectCsrfToken()); ?>" />

<div id="report_parameters">

<input type='hidden' name='form_refresh' id='form_refresh' value=''/>
<input type='hidden' name='form_csvexport' id='form_csvexport' value=''/>

<table>
 <tr>
  <td width='60%'>
    <div style='float:left'>

    <table class='text'>
        <tr>
             <td class='col-form-label'>
                <?php echo xlt('Birthdays In'); ?>:
            </td>
            <td>
               <select name="form_month" id="form_month" class="form-control">
                <?php
                foreach ($monthSet as $key => $month) {
                    $isSelected = ($key === $form_month) ? ' selected' : '';
                    echo "<option value='" . attr($key) . "'" . $isSelected . ">" . xlt($month) . "</option>\n";
                }
                ?>
               </select>
            </td>
        </tr>
    </table>

    </div>

  </td>
  <td class="h-100" align='left' valign='middle'>
    <table class="w-100 h-100" style='border-left: 1px solid;'>
        <tr>
            <td>
        <div class="text-center">
                  <div class="btn-group" role="group">
                    <a href='#' class='btn btn-secondary btn-save' onclick='$("#form_csvexport").val(""); $("#form_refresh").attr("value","true"); $("#theform").submit();'>
                        <?php echo xlt('Submit'); ?>
                    </a>
                    <?php if (!empty($_POST['form_refresh'])) { ?>
                    <a href='#' class='btn btn-secondary btn-transmit' onclick='$("#form_csvexport").attr("value","true"); $("#theform").submit();'>
                        <?php echo xlt('Export to CSV'); ?>
                    </a>
                      <a href='#' id='printbutton' class='btn btn-secondary btn-print'>
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
</div> <!-- end of parameters -->

    <?php
} // end not form_csvexport

if (!empty($_POST['form_refresh']) || !empty($_POST['form_csvexport'])) {
    if ($_POST['form_csvexport']) {
        // CSV headers:
        echo csvEscape(xl('DOB')) . ',';
        echo csvEscape(xl('First{{Name}}')) . ',';
        echo csvEscape(xl('Last{{Name}}')) . ',';
        echo csvEscape(xl('Middle{{Name}}')) . ',';
        echo csvEscape(xl('ID')) . ',';
        echo csvEscape(xl('Street')) . ',';
        echo csvEscape(xl('City')) . ',';
        echo csvEscape(xl('State')) . ',';
        echo csvEscape(xl('Zip')) . ',';
        echo csvEscape(xl('Home Phone')) . ',';
        echo csvEscape(xl('Work Phone')) . "\n";
    } else {
        ?>

  <div id="report_results">
  <table class='table' id='mymaintable'>
   <thead class='thead-light'>
    <th> <?php echo xlt('DOB'); ?> </th>
    <th> <?php echo xlt('Patient'); ?> </th>
    <th> <?php echo xlt('ID'); ?> </th>
    <th> <?php echo xlt('Street'); ?> </th>
    <th> <?php echo xlt('City'); ?> </th>
    <th> <?php echo xlt('State'); ?> </th>
    <th> <?php echo xlt('Zip'); ?> </th>
    <th> <?php echo xlt('Home Phone'); ?> </th>
    <th> <?php echo xlt('Work Phone'); ?> </th>
 </thead>
 <tbody>
        <?php
    } // end not export
    $totalpts = 0;
    $sqlArrayBind = array();
    $query = "SELECT " .
        "p.fname, p.mname, p.lname, p.street, p.city, p.state, " .
        "p.postal_code, p.phone_home, p.phone_biz, p.pid, p.pubpid, p.DOB " .
        "FROM patient_data as p WHERE MONTH(DOB) = ? ORDER BY p.lname";

    $res = sqlStatement($query, [intval($_POST['form_month']) + 1]);

    while ($row = sqlFetchArray($res)) {
        if ($_POST['form_csvexport']) {
            echo csvEscape(oeFormatShortDate(substr($row['DOB'], 0, 10))) . ',';
            echo csvEscape($row['lname']) . ',';
            echo csvEscape($row['fname']) . ',';
            echo csvEscape($row['mname']) . ',';
            echo csvEscape($row['pubpid']) . ',';
            echo csvEscape(xl($row['street'])) . ',';
            echo csvEscape(xl($row['city'])) . ',';
            echo csvEscape(xl($row['state'])) . ',';
            echo csvEscape($row['postal_code']) . ',';
            echo csvEscape($row['phone_home']) . ',';
            echo csvEscape($row['phone_biz']) . "\n";
        } else {
            ?>
       <tr>
        <td>
            <?php echo text(oeFormatShortDate(substr($row['DOB'], 0, 10))); ?>
   </td>
   <td>
            <?php echo text($row['lname'] . ', ' . $row['fname'] . ' ' . $row['mname']); ?>
   </td>
   <td>
            <?php echo text($row['pubpid']); ?>
   </td>
   <td>
            <?php echo xlt($row['street']); ?>
   </td>
   <td>
            <?php echo xlt($row['city']); ?>
   </td>
   <td>
            <?php echo xlt($row['state']); ?>
   </td>
   <td>
            <?php echo text($row['postal_code']); ?>
   </td>
   <td>
            <?php echo text($row['phone_home']); ?>
   </td>
   <td>
            <?php echo text($row['phone_biz']); ?>
   </td>
  </tr>
            <?php
        } // end not export
        ++$totalpts;
    } // end while
    if (!$_POST['form_csvexport']) {
        ?>

   <tr class="report_totals">
    <td colspan='9'>
        <?php echo xlt('Total Number of Birthdays'); ?>
   :
        <?php echo text($totalpts); ?>
  </td>
 </tr>

</tbody>
</table>
</div> <!-- end of results -->
        <?php
    } // end not export
} // end if refresh or export

if (empty($_POST['form_refresh']) && empty($_POST['form_csvexport'])) {
    ?>
<div class='text'>
    <?php echo xlt('Please input search criteria above, and click Submit to view results.'); ?>
</div>
    <?php
}

if (empty($_POST['form_csvexport'])) {
    ?>

</form>
</body>

</html>
    <?php
} // end not export
?>
