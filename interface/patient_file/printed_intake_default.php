<?php
//echo "<pre>";

// Copyright (C) 2007-2010 Rod Roark <rod@sunsetsystems.com>
//
// 2012 - Refactored extensively to allow for creating multiple feesheets on demand
// uses a session array of PIDS by Medical Information Integration, LLC - mi-squared.com
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.

require_once("../globals.php");
require_once("$srcdir/patient.inc");
require_once("$srcdir/classes/Address.class.php");
require_once("$srcdir/classes/InsuranceCompany.class.php");
require_once("$srcdir/formatting.inc.php");
function genColumn($ix) {
    global $html;
    global $SBCODES;
    for ($imax = count($SBCODES); $ix < $imax; ++$ix) {
        $a = explode('|', $SBCODES[$ix], 2);
        $cmd = trim($a[0]);
        if ($cmd == '*C') { // column break
            return++$ix;
        }
        if ($cmd == '*B') { // Borderless and empty
         //   $html .= " <tr><td colspan='5' class='fscode' style='border-width:0 1px 0 0;padding-top:1px;' nowrap>&nbsp;</td></tr>\n";
        } else if ($cmd == '*G') {
            $title = htmlspecialchars($a[1]);
            if (!$title)
                $title = '&nbsp;';
          //  $html .= " <tr><td colspan='5' align='center' class='fsgroup' style='vertical-align:middle' nowrap>$title</td></tr>\n";
        }
        else if ($cmd == '*H') {
            $title = htmlspecialchars($a[1]);
            if (!$title)
                $title = '&nbsp;';
          //  $html .= " <tr><td colspan='5' class='fshead' style='vertical-align:middle' nowrap>$title</td></tr>\n";
        }
        else {
            $title = htmlspecialchars($a[1]);
            if (!$title)
                $title = '&nbsp;';
            $b = explode(':', $cmd);
         //   $html .= " <tr>\n";
         //   $html .= " <td class='fscode' style='vertical-align:middle;width:14pt' nowrap>&nbsp;</td>\n";
            if (count($b) <= 1) {
                $code = $b[0];
                if (!$code)
                    $code = '&nbsp;';
             //   $html .= " <td class='fscode' style='vertical-align:middle' nowrap>$code</td>\n";
              //  $html .= " <td colspan='3' class='fscode' style='vertical-align:middle' nowrap>$title</td>\n";
            }
            else {
             //   $html .= " <td colspan='2' class='fscode' style='vertical-align:middle' nowrap>" . $b[0] . '/' . $b[1] . "</td>\n";
             //   $html .= " <td colspan='2' class='fscode' style='vertical-align:middle' nowrap>$title</td>\n";
            }
           // $html .= " </tr>\n";
        }
    }
    return $ix;
}

// MAIN Body
//
// Build output to handle multiple pids and and superbill for each patient.
// This value is initially a maximum, and will be recomputed to
// distribute lines evenly among the pages.  (was 55)
$lines_per_page = 55;

$lines_in_stats = 8;

$header_height = 44; // height of page headers in points
// This tells us if patient/encounter data is to be filled in.
// 1 = single PID from popup, 2=array of PIDs for session

if (empty($_GET['fill'])) {
    $form_fill = 0;
} else {
    $form_fill = $_GET['fill'];
}


// Show based on session array or single pid? 
$pid_list = array();

if(!empty($_SESSION['pidList']) and $form_fill == 2)
{
    $pid_list = $_SESSION['pidList'];
}
else if ($form_fill == 1)
{
    array_push($pid_list,$pid); //get from active PID
} else {
    array_push($pid_list,''); // empty element for blank form 
}

$site_id = $_SESSION['site_id'];
//echo $site_id;

//print_r($_SESSION);
//print_r($_SESSION['apptdateList']);
$apptdate_list = array();
$apptdate_list = $_SESSION['apptdateList'];
//$nextappt = array();

function getNextAppointment($id,$date) {
  $query = "select * from openemr_postcalendar_events where pc_pid=$id and pc_eventDate = '$date' order by pc_time";
  $rez = sqlStatement($query);
  $rowz = sqlFetchArray($rez);
  return $rowz; 
    }

// make sure to clean up the session 
// else we'll build off of trash in the combo-drop down for a single patient later
//// TEST RonP 3/30/2017: Commenting out unsets to avoid refresh / 2nd click of Superbills
//// While this may fix that, it may also bring about issue(s) mentioned in original comments.
//// unset($_SESSION['pidList']);
//// unset($_SESSION['apptdateList']);

// This file is optional. You can create it to customize how the printed
// fee sheet looks, otherwise you'll get a mirror of your actual fee sheet.
//
if (file_exists("../../custom/fee_sheet_codes.php"))
    include_once ("../../custom/fee_sheet_codes.php");

// TBD: Move these to globals.php, or make them user-specific.
$fontsize = 7;
$page_height = 600;

$padding = 0;

// The $SBCODES table is a simple indexed array whose values are
// strings of the form "code|text" where code may be either a billing
// code or one of the following:
//
// *H - A main heading, where "text" is its title (to be centered).
// *G - Specifies a new category, where "text" is its name.
// *B - A borderless blank row.
// *C - Ends the current column and starts a new one.
// If $SBCODES is not provided, then manufacture it from the Fee Sheet.
//
if (empty($SBCODES)) {
    $SBCODES = array();
    $last_category = '';

    // Create entries based on the fee_sheet_options table.
    $res = sqlStatement("SELECT * FROM fee_sheet_options " .
            "ORDER BY fs_category, fs_option");
    while ($row = sqlFetchArray($res)) {
        $fs_category = $row['fs_category'];
        $fs_option = $row['fs_option'];
        $fs_codes = $row['fs_codes'];
        if ($fs_category !== $last_category) {
            $last_category = $fs_category;
            $SBCODES[] = '*G|' . substr($fs_category, 1);
        }
        $SBCODES[] = " |" . substr($fs_option, 1);
    }

    // Create entries based on categories defined within the codes.
    $pres = sqlStatement("SELECT option_id, title FROM list_options " .
            "WHERE list_id = 'superbill' ORDER BY seq");
    while ($prow = sqlFetchArray($pres)) {
        $SBCODES[] = '*G|' . $prow['title'];
        $res = sqlStatement("SELECT code_type, code, code_text FROM codes " .
                "WHERE superbill = '" . $prow['option_id'] . "' AND active = 1 " .
                "ORDER BY code_text");
        while ($row = sqlFetchArray($res)) {
            $SBCODES[] = $row['code'] . '|' . $row['code_text'];
        }
    }

    // Create one more group, for Products.
    if ($GLOBALS['sell_non_drug_products']) {
        $SBCODES[] = '*G|' . xl('Products');
        $tres = sqlStatement("SELECT " .
                "dt.drug_id, dt.selector, d.name, d.ndc_number " .
                "FROM drug_templates AS dt, drugs AS d WHERE " .
                "d.drug_id = dt.drug_id AND d.active = 1 " .
                "ORDER BY d.name, dt.selector, dt.drug_id");
        while ($trow = sqlFetchArray($tres)) {
            $tmp = $trow['selector'];
            if ($trow['name'] !== $trow['selector'])
                $tmp .= ' ' . $trow['name'];
            $prodcode = empty($trow['ndc_number']) ? ('(' . $trow['drug_id'] . ')') :
                    $trow['ndc_number'];
            $SBCODES[] = "$prodcode|$tmp";
        }
    }

    // Extra stuff for the labs section.
    //$SBCODES[] = '*G|' . xl('NOTES');
    $percol = intval((count($SBCODES) + 2) / 3);
    while (count($SBCODES) < $percol * 3)
        $SBCODES[] = '*B|';

    // Adjust lines per page to distribute lines evenly among the pages.
    $pages = intval(($percol + $lines_in_stats + $lines_per_page - 1) / $lines_per_page);
    $lines_per_page = intval(($percol + $lines_in_stats + $pages - 1) / $pages);

    // Figure out page and column breaks.
    $pages = 1;
    $lines = $percol;
    $page_start_index = 0;
    while ($lines + $lines_in_stats > $lines_per_page) {
        ++$pages;
        $lines_this_page = $lines > $lines_per_page ? $lines_per_page : $lines;
        $lines -= $lines_this_page;
        array_splice($SBCODES, $lines_this_page * 3 + $page_start_index, 0, '*C|');
        array_splice($SBCODES, $lines_this_page * 2 + $page_start_index, 0, '*C|');
        array_splice($SBCODES, $lines_this_page * 1 + $page_start_index, 0, '*C|');
        $page_start_index += $lines_this_page * 3 + 3;
    }
    array_splice($SBCODES, $lines * 2 + $page_start_index, 0, '*C|');
    array_splice($SBCODES, $lines * 1 + $page_start_index, 0, '*C|');
}

$lheight = sprintf('%d', ($page_height - $header_height) / $lines_per_page);

// Common HTML Header information

$html = "<html>
<head>";

$html .= "
<style>
#csspatname {font-family:mono; font-size: 16px; position:relative; top:0px; left:75px; display: inline-block; width:350px;}

#csspatage  {font-family:mono; font-size: 16px; position:relative; top:0px;            display: inline-block; width:30px;}

#cssdob     {font-family:mono; font-size: 16px; position:relative; top:0px;            display: inline-block; width:140px;}
/* #cssdob     {font-family:mono; font-size: 16px; position:relative; top:0px;            display: inline-block; width:170px;} */

#cssdos     {font-family:mono; font-size: 16px; position:relative; top:0px;            display: inline-block; width:165px;}
#cssapptime {font-family:mono; font-size: 16px; position:relative; top:0px;            display: inline-block;}

#cssssn {font-family:mono;  font-size: 14px; position:relative; top:65px; display: inline-block; width:115px;}
#csssex {font-family:mono;  font-size: 14px; position:relative; top:65px; display: inline-block; width:60px;}
#csspid {font-family:mono;  font-size: 14px; position:relative; top:65px;}
#cssadd1 {font-family:mono;  font-size: 14px; position:relative; top:60px; display: inline-block; width:250px;}
#cssphone {font-family:mono;  font-size: 14px; position:relative; top:60px; left:10px; display: inline-block; width:150px;}

#cssinsname {font-family:mono;  font-size: 14px; position:relative; top:55px; display: inline-block; width:200px;}
#cssrefname {font-family:mono;  font-size: 14px; position:relative; top:55px; left:150px}
#cssappt_comment {font-family: mono;  font-size: 14px; position: relative; top: 175px; left: 125px; display: block;}
div.pagebreak {
page-break-after: always;
height: ${page_height}pt;
}
</style>";

$html .= "<title>" . htmlspecialchars($frow['name']) . "</title>
<script type=\"text/javascript\" src=\"../../library/dialog.js\"></script>
<script language=\"JavaScript\">";

$html .= "
// Process click on Print button.
function printme() {
var divstyle = document.getElementById('hideonprint').style;
divstyle.display = 'none';
window.print();
}

</script>
</head>
<body bgcolor='#ffffff'>
<form name='theform' method='post' action='printed_fee_sheet' + '$site_id' + '.php?fill=$form_fill'
onsubmit='return opener.top.restoreSession()'>";
//<center>";

// Set Pagebreak for multi forms
if ($form_fill == 2) {
    //$html .= "<div class=pagebreak>\n";
} else {
    $html .= "<div>\n";
}

$today = date('Y-m-d');

$alertmsg = ''; // anything here pops up in an alert box

// Get details for the primary facility.
$frow = sqlQuery("SELECT * FROM facility WHERE primary_business_entity = 1");

// If primary is not set try to old method of guessing...for backward compatibility
if (empty($frow)) {
    $frow = sqlQuery("SELECT * FROM facility " . 
            "ORDER BY billing_location DESC, accepts_assignment DESC, id LIMIT 1");
}

// Still missing...
if (empty($frow)) {
    $alertmsg = xl("No Primary Business Entity selected in facility list");
}

// Loop on array of PIDS
$pages = 1; //Save calculated page count of a single fee sheet
$its = 0;
foreach ($pid_list as $pid) {
    
    if ($form_fill) {
        // Get the patient's name and chart number.
        $patdata = getPatientData($pid);
    }

// This tracks our position in the $SBCODES array.
    $cindex = 0;

    while (--$pages >= 0) {
        $html .= "<div class=pagebreak>";
       // $html .= genFacilityTitle(xl(''), -1);

//        $html .="
//<table class='bordertbl' cellspacing='0' cellpadding='0' width='50%'>
//<tr>
//<td valign='top'>
//<table border='0' cellspacing='0' cellpadding='0' width='100%'>
//<tr>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:25%'></td>
//<td class='toprow' style='width:55%'></td>
//</tr>";

        $cindex = genColumn($cindex); // Column 1

        if ($pages == 0) { // if this is the last page
            //$html .= "<tr><td colspan='3' valign='top' class='fshead' style='height:" . $lheight * 2 . "pt'>";
            //$html .= "<br /><br /><br /><br />";
            //$html .= '&nbsp;&nbsp;&nbsp;';
          //  $html .= ":";
          
          $whichdate = $apptdate_list[$its];
          
//          echo "whichdate is " . $whichdate . "</br>";

          $nextappt = getNextAppointment($pid,$whichdate);
          
          $lasttime = $nextappt['pc_startTime'];

          $appt_comment = $nextappt['pc_hometext'];

          
//          while(!empty($nextappt)) {
          
//          var_dump($nextappt);
          
//          echo "start time is " . $nextappt['pc_startTime'] . "</br>";
        

          
            if ($form_fill) {
                $html .=  "<span id=csspatname>" . $patdata['fname'] . ' ' . $patdata['mname'] . ' ' . $patdata['lname'] . "</span>";
                $html .=  "<span id=csspatage>" . getPatientAgeDisplay($patdata['DOB']) . "</span>";
                $html .=  "<span id=cssdob>" . oeFormatShortDate($patdata['DOB']) . "</span>"; 
             //   "</span> <span id=cssssn>" . $patdata['ss'] . "</span><span id=cssdob>" . $patdata['DOB'] . "</span>";
             //   $html .=  "<span id=csssex>" . $patdata['sex'] . "</span>" . "<span id=csspid>" . $patdata['pubpid'] . "</span></br></br>" ;
             //   $html .=  "<span id=cssadd1>" .  $patdata['street'] . "</span> <span id=cssphone>" . $patdata['phone_home'] 

                $html .=  "<span id=cssdos>" . oeFormatShortDate($whichdate) . "</span>"; 
                $html .=  "<span id=cssapptime>" . date_format(date_create($lasttime),"g:i") . "</span>";
                $html .=  "<span id=cssappt_comment>" . $appt_comment . "</span>";

                //   $html .=  "<span id=cssadd1>" . $patdata['city'] . ', ' . $patdata['state'] . ' ' . $patdata['postal_code'] . "</span>";
             //   $html .=  "<span id=cssphone>" . $patdata['phone_biz'] . "</span></br></br>";
              
            
               
            //echo $its;
            //echo $whichdate;
          //  $html .= "<br />";  


//            $html .=  xl('ID', 'r') . ':' ;
            }

  //          if ($form_fill)
   //             $html .= $patdata['pubpid'];

   //         $html .= "<br />";  
       //     $html .= '&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp';
            //$html .= ":<br />";

            if ($form_fill)
               
                
                
                
            
            
            
           // $html .= "</td></tr><tr><td colspan='3' valign='top' class='fshead' style='height:${lheight}pt'>";


              $whichref = $patdata['ref_providerID'];
              //echo $whichpat;
              //echo $whichref;
              
              
             
              $docquery = "SELECT * FROM users WHERE id = '$whichref'";
              $docresult = sqlQuery($docquery);
            //  if (!$docresult) echo "no ref on file";
              
            //$html .= "<br />";
            //$html .= xl('Doctor', 'r') . ':' . '&nbsp;&nbsp;' . $docresult['fname'] . '&nbsp;' . $docresult['lname'];
            //$html .= "<br />";


            $encdata = false;
            if ($form_fill && $encounter) {
                $query = "SELECT fe.reason, fe.date, u.fname, u.mname, u.lname, u.username " .
                        "FROM forms AS f " .
                        "JOIN form_encounter AS fe ON fe.id = f.form_id " .
                        "LEFT JOIN users AS u ON u.username = f.user " .
                        "WHERE f.pid = '$pid' AND f.encounter = '$encounter' AND f.formdir = 'newpatient' AND f.deleted = 0 " .
                        "ORDER BY f.id LIMIT 1";
                $encdata = sqlQuery($query);
                if (!empty($encdata['username'])) {
                   // $html .= $encdata['fname'] . ' ' . $encdata['mname'] . ' ' . $encdata['lname'];
                }
            }

           // $html .= "</td><td valign='top' class='fshead'>";
            //$html .= xl('Reason', 'r') . ':' . $apptresult['pc_hometext'];
            //$html .= "<br />";
            //$html .= "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br /><br />";
            //$html .= "<br /><br /><br /><br /><br /><br /><br /><br /><br /><br />";
            //$html .= "<br /><br /><br /><br />";
            

            if (!empty($encdata)) {
             //   $html .= $encdata['reason'];
            }

//            $html .= "</td></tr><tr><td colspan='4' valign='top' class='fshead' style='height:${lheight}pt'>";

            if (empty($GLOBALS['ippf_specific'])) {
            //    $html .= xl('Insurance', 'r').":";
                if ($form_fill) {
                    foreach (array('primary') as $instype) {
                        $query = "SELECT * FROM insurance_data WHERE " .
                                "pid = '$pid' AND type = '$instype' " .
                                "ORDER BY date DESC LIMIT 1";
                        $row = sqlQuery($query);
                        if ($row['provider']) {
                            $icobj = new InsuranceCompany($row['provider']);
                            $adobj = $icobj->get_address();
                            $insco_name = trim($icobj->get_name());
                            if ($instype = 'primary');
                             //   $html .= "<span id=cssinsname>" . $insco_name . "</span></br></br>";
                            else {
                               // $html .= "&nbsp;<font color='red'><b>Missing Name</b></font>";
                            }
                        }
                    }
                }
            } else {
                // IPPF wants a visit date box with the current date in it.
                $html .= xl('Visit date','r');
                //$html .= ":<br />\n";
                if (!empty($encdata)) {
                    $html .= substr($encdata['date'], 0, 10);
                } else {
                    $html .= oeFormatShortDate(date('Y-m-d')) . "\n";
                }
            }
//            $html .= "<span id=cssrefname>" . $docresult['lname'] . ', ' . $docresult['fname'] . "</span>" ;
//            $html .= "</td></tr>";

//<tr>
//<td colspan='4' valign='top' class='fshead' style='height:${lheight}pt'>";
//            $html .= xl('Prior Visit', 'r');
//            $html .= ":<br />
//</td>
//</tr>
//<tr>
//<td colspan='4' valign='top' class='fshead' style='height:${lheight}pt'>";
//            $html .= xl('Today\'s Charges', 'r');
//            $html .= ":<br />
//</td>
//</tr>
//<tr>
//<td colspan='4' valign='top' class='fshead' style='height:${lheight}pt'>";
//            $html .= xl('Today\'s Balance', 'r');
//            $html .= ":<br />
//</td>
//</tr>
//<tr>
//<td colspan='4' valign='top' class='fshead' style='height:${lheight}pt'>";
//            $html .= xl('Notes', 'r');
//            $html .= ":<br />
//</td>
//</tr>";

        } // end if last page

//        $html .= "</table></td>";
//<td valign='top'>
//<table border='0' cellspacing='0' cellpadding='0' width='100%'>
//<tr>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:25%'></td>
//<td class='toprow' style='width:55%'></td>
//</tr>";

        $cindex = genColumn($cindex); // Column 2

        if ($pages == 0) { // if this is the last page
            $html .= "<tr><td colspan='4' valign='top' class='fshead' style='height:" . $lheight * 8 . "pt'>";
           // $html .= xl('Notes', 'r');
            $html .= "</br></td></tr>";
        } // end if last page

//        $html .= "</table>
//</td>
//<td valign='top'>
//<table border='0' cellspacing='0' cellpadding='0' width='100%'>
//<tr>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:10%'></td>
//<td class='toprow' style='width:25%'></td>
//<td class='toprow' style='width:55%'></td>
//</tr>";

        $cindex = genColumn($cindex); // Column 3

        if ($pages == 0) { // if this is the last page
//            $html .= "<tr>
//<td valign='top' colspan='4' class='fshead' style='height:" . $lheight * 6 . "pt;border-width:0 1px 0 0'>
//&nbsp;
//</td>
//</tr>
//<tr>
//<td valign='top' colspan='4' class='fshead' style='height:" . $lheight * 2 . "pt'>";
//            $html .= xl('Signature', 'r');
//            $html .= ":<br />
//</td>
//</tr>";
        } // end if last page

//        $html .= "</table></td></tr></table>";
        
        $html .= "</div>";  //end of div.pageLetter
        
    } // end while
    $its = $its + 1;
    $pages = 1; //RESET
}

// Common End Code
if ($form_fill != 2) {   //use native browser 'print' for multipage
$html .= "<div id='hideonprint'><p><input type='button' value='";

$html .= xl('Print', 'r');
$html .="' onclick='printme()' /></div>";
}

$html .= "</form></center></body></html>";

// Send final result to display
echo $html;
?>
