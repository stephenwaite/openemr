<?php
// Copyright (C) 2010 MMF Systems, Inc>
//
// This program is free software; you can redistribute it and/or
// modify it under the terms of the GNU General Public License
// as published by the Free Software Foundation; either version 2
// of the License, or (at your option) any later version.
//SANITIZE ALL ESCAPES
$sanitize_all_escapes = true;
//
//STOP FAKE REGISTER GLOBALS
$fake_register_globals = false;
//
//	START - INCLUDE STATEMENTS
include_once(dirname(__file__) . "/../globals.php");
include_once("$srcdir/forms.inc");
include_once("$srcdir/billing.inc");
include_once("$srcdir/pnotes.inc");
include_once("$srcdir/patient.inc");
include_once("$srcdir/report.inc");
include_once("$srcdir/calendar.inc");
include_once("$srcdir/classes/Document.class.php");
include_once("$srcdir/classes/Note.class.php");
include_once("$srcdir/sqlconf.php");
include_once("$srcdir/edi.inc");

// END - INCLUDE STATEMENTS
//  File location (URL or server path)
$EXT = "*.elr";
$EXT_LEN = strlen($EXT);

$target = $GLOBALS['edi_271_file_path'];

if (isset($_FILES) && !empty($_FILES)) {

    $target = $target . time() . basename($_FILES['uploaded']['name']);

    $FilePath = $target;

    if ($_FILES['uploaded']['size'] > 350000) {
        $message .= htmlspecialchars(xl('Your file is too large'), ENT_NOQUOTES) . "<br>";
    }

    if ($_FILES['uploaded']['type'] != "text/plain") {
        $message .= htmlspecialchars(xl('You may only upload .txt files'), ENT_NOQUOTES) . "<br>";
    }
    if (!isset($message)) {
        if (move_uploaded_file($_FILES['uploaded']['tmp_name'], $target)) {
            $message = htmlspecialchars(xl('The following EDI file has been uploaded') . ': "' . basename($_FILES['uploaded']['name']) . '"', ENT_NOQUOTES);

            // Reads the content of the file
            $Response271 = file($FilePath);
            $rpt = process_271_results($Response271);
            if (!$rpt)
                $messageEDI = true;
        }
    } else {
        $message .= htmlspecialchars(xl('Sorry, there was a problem uploading your file'), ENT_NOQUOTES) . "<br><br>";
    }
} else 
    if (isset($_GET['file_selected']) && !empty($_GET['file_selected'])) {
            $FilePath = $target . $_GET['file_selected'];
            $Response271 = file($FilePath);
            $rpt = process_271_results($Response271);
            $message = htmlspecialchars(xl('The following EDI file has been uploaded') . ': "' . $FilePath . '"', ENT_NOQUOTES);
            if (!$rpt)
                $messageEDI = true;    
    }
?>
<html>
    <head>
        <?php html_header_show(); ?>
        <title><?php echo htmlspecialchars(xl('EDI-271 Response File Upload'), ENT_NOQUOTES); ?></title>
        <link rel=stylesheet href="<?php echo $css_header; ?>" type="text/css">
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
            #report_results table thead {
                cursor: pointer;
            }

        </style>

        <script type="text/javascript" src="../../library/textformat.js"></script>
        <script type="text/javascript" src="../../library/dialog.js"></script>
        <script type="text/javascript" src="../../library/js/jquery-1.7.2.min.js"></script>
        <script type="text/javascript" src="../../library/js/jquery.tablesorter.min.js"></script>

        <script type="text/javascript" id="js">
            $(document).ready(function() { 
                $("#results").tablesorter(); 
            } 
        ); 
        </script>

        <script type="text/javascript">
            function edivalidation(){
			
                var mypcc = "<?php echo htmlspecialchars(xl('Required Field Missing: Please choose the EDI-271 file to upload'), ENT_QUOTES); ?>";

                if(document.getElementById('uploaded').value == ""){
                    alert(mypcc);
                    return false;
                }
                else
                {
                    $("#theform").submit();
                }
			
            }
        </script>

    </head>
    <body class="body_top">

        <div id="overDiv" style="position:absolute; visibility:hidden; z-index:1000;"></div>
        <?php
        if (isset($message) && !empty($message)) {
            ?>
            <div style="margin-left:25%;width:50%;color:RED;text-align:center;font-family:arial;font-size:15px;background:#ECECEC;border:1px solid;" ><?php echo $message; ?></div>
            <?php
            $message = "";
        }
        if (isset($messageEDI)) {
            ?>
            <div style="margin-left:25%;width:50%;color:RED;text-align:center;font-family:arial;font-size:15px;background:#ECECEC;border:1px solid;" >
                <?php echo htmlspecialchars(xl('Please choose the proper formatted EDI-271 file'), ENT_NOQUOTES); ?>
            </div>
            <?php
            $messageEDI = "";
        }
        ?>

        <div>

            <span class='title'><?php echo htmlspecialchars(xl('EDI-271 File Upload'), ENT_NOQUOTES); ?></span>

            <form enctype="multipart/form-data" name="theform" id="theform" action="edi_271.php" method="POST" onsubmit="return top.restoreSession()">

                <div id="report_parameters">
                    <table>
                        <tr>
                            <td width='550px'>
                                <div style='float:left'>
                                    <table class='text'>
                                        <tr>
                                            <td style='width:125px;' class='label'> <?php echo htmlspecialchars(xl('Select EDI-271 file'), ENT_NOQUOTES); ?>:	</td>
                                            <td> <input name="uploaded" id="uploaded" type="file" size=37 /></td>
                                        </tr>
                                    </table>
                                </div>
                            </td>
                            <td align='left' valign='middle' height="100%">
                                <table style='border-left:1px solid; width:100%; height:100%' >
                                    <tr>
                                        <td>
                                            <div style='margin-left:15px'>
                                                <a href='#' class='css_button' onclick='return edivalidation(); '><span><?php echo htmlspecialchars(xl('Upload'), ENT_NOQUOTES); ?></span>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                    </table>
                </div>


                <input type="hidden" name="form_orderby" value="<?php echo htmlspecialchars($form_orderby, ENT_QUOTES); ?>" />
                <input type='hidden' name='form_refresh' id='form_refresh' value=''/>

            </form>
        </div>
        <?php
        if (isset($rpt) && count($rpt) > 0) {
            //show_271_results($rpt);
            echo "<div id='report_results'>
			<table id='results' class='tablesorter'>
				<thead>
                                    <tr>
					<th style='width:15%;'>	" . htmlspecialchars(xl('Name'), ENT_NOQUOTES) . "</th>
					<th style='width:10%;'>	" . htmlspecialchars(xl('Policy No'), ENT_NOQUOTES) . "</th>
					<th style='width:15%;' > " . htmlspecialchars(xl('Insurance Co'), ENT_NOQUOTES) . "</th>
					<th style='width:10%;'>	" . htmlspecialchars(xl('Status'), ENT_NOQUOTES) . "</th>
					<th style='width:5%;' >	" . htmlspecialchars(xl('Copay'), ENT_NOQUOTES) . "</th>
					<th style='width:5%;' >	" . htmlspecialchars(xl('Deductible'), ENT_NOQUOTES) . "</th>
					<th style='width:40%;' > " . htmlspecialchars(xl('Messages'), ENT_NOQUOTES) . "</th>
                                    </tr>
                                </thead>

				<tbody>
					
		";
            $i = 0;
            foreach ($rpt as $row) {

                echo "	<tr id='PR" . $i . "_" . htmlspecialchars($row['policy_number'], ENT_QUOTES) . "'>
				<td class ='detail' style='width:15%;'>" . htmlspecialchars($row['lName'], ENT_NOQUOTES) . ", " . htmlspecialchars($row['fName'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:10%;'>" . htmlspecialchars($row['policy'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:15%;'>" . htmlspecialchars($row['insurance'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:10%;'>" . htmlspecialchars($row['status'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:5%;'>" . htmlspecialchars($row['copay'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:5%;'>" . htmlspecialchars($row['deductible'], ENT_NOQUOTES) . "</td>
				<td class ='detail' style='width:40%;'>" . htmlspecialchars($row['msg'], ENT_NOQUOTES) . "</td>
			</tr>			
		";
            }

            if ($i == 0) {

                echo "	<tr>
				<td class='norecord' colspan=9>
					<div style='padding:5px;font-family:arial;font-size:13px;text-align:center;'>" . htmlspecialchars(xl('No records found'), ENT_NOQUOTES) . "</div>
				</td>
			</tr>	";
            }
            echo "	</tbody>
			</table>
       </div>";
        }
        else {
            echo "<div id='report_results'>
			<table id='files' class='tablesorter'>
				<thead>
                                    <tr>
					<th>	" . htmlspecialchars(xl('File Name'), ENT_NOQUOTES) . "</th>
					<th>	" . htmlspecialchars(xl('Date'), ENT_NOQUOTES) . "</th>
                                    </tr>
                                </thead>
				<tbody>
					
		";
            $fls = glob($target . $EXT);
            foreach ($fls as $fl) 
                $flArr[filemtime($fl)] = basename($fl); 
            krsort($flArr);
            $i=0;
            foreach ($flArr as $dt => $fname) {
                    echo "	<tr >
				<td class ='detail' >" . "<a href='edi_271.php?file_selected=" . 
                                    htmlspecialchars($fname, ENT_NOQUOTES) . 
                                    "' class='detail' onClick='top.restoreSession()' <span>" . 
                                    htmlspecialchars($fname, ENT_NOQUOTES) . 
                                    "</span></a></td>
				<td class ='detail' >" . htmlspecialchars(date('D Y-m-d H:i',$dt), ENT_NOQUOTES) . "</td>
			</tr>			
                        ";
                    if (++$i > 6) break;
                    
            }
            
            echo "	</tbody>
			</table>
       </div>";
        }
        ?>
    </body>
</html>
