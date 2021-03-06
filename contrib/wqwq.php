<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 9/23/19
 * Time: 9:13 AM
 */

ini_set('max_execution_time', '0');
$ignoreAuth = true;
$_GET['site'] = 'default';
$argv = $_GET['argv'];
require_once(dirname(__FILE__) . "/../interface/globals.php");

//echo "/tmp/" . $argv[1] . ".png \n";
// exit();

$pdf = new Cezpdf('LETTER');
//$pdf->ezSetMargins(trim($_POST['top_margin']) + 0, 0, trim($_POST['left_margin']) + 0, 0);
$pdf->ezSetMargins(170, 0, 10, 0);
$pdf->selectFont('Courier');
$page_count = 0;
$continued = false;
$is_continued = false;
$was_continued = false;

$content = file_get_contents('wqwq');
$pages = explode("\014", $content); // form feeds may separate pages
foreach ($pages as $page) {
    $body_start = strpos($page, chr(032)) +2;
    $footer_start = strpos($page, chr(034));
    $body_length = $footer_start - $body_start;
    $footer_length = $length - $footer_start;

    $page_lines = count(explode("\012", $page));

    $was_continued = $is_continued;

    if (!strpos($page, "CONTINUED")) {         
        $is_continued = false;
    } else {        
        $is_continued = true;
    }

    if (!$is_continued && !$was_continued) {
        printOnePage($page);
    }

    if ($is_continued && !$was_continued) {
        buildPage($page);
    }

    if (!$is_continued && $was_continued) {
        printMultiPage($page);
    }
    //$buffer = '';
    //error_log("string length is " . strlen($page));

    $body_start = strpos($page, chr(032)) +2;
    $footer_start = strpos($page, chr(034));
    $body_length = $footer_start - $body_start;
    $footer_length = $length - $footer_start;

    if ($footer_start && !$is_continued) {
        $header = substr($page, 0, $body_start);
        $body = substr($page, $body_start, $body_length);
        $footer = substr($page, $footer_start, $footer_length);

        $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
        $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
        $pdf->ezText($header, 12, array(
            'justification' => 'left',
            'leading' => 12
        ));
//        error_log("page height is " . $pdf->ez['pageHeight']);
//        error_log("footer is " . $footer);

        $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 130);
        $pdf->ezText($body, 12, array(
            'justification' => 'left',
            'leading' => 12
        ));

        $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
        $pdf->ezText($footer, 12, array(
            'justification' => 'left',
            'leading' => 12
        ));
        

    } else {
        $header = substr($page, 0, $body_start);
        $body = substr($page, $body_start, $length);
        if (!strpos($body, "CONTINUED")) {
            $blines = explode("\012", $body); // form feeds may separate pages
            $bline_count = 0;
            $bline_count = count($blines);
            $i = 0;
            $altered_body = '';
            do {
                $altered_body .= $blines[$i];
                //error_log($i . " " . $blines[$i] . "<br>");
                $i++;
            } while ($i < ($bline_count - 3));


            $altered_footer = "\012" . $blines[$bline_count - 3] . "\012";
            $altered_footer .= $blines[$bline_count - 2] . "\012";
            $altered_footer .= $blines[$bline_count - 1] . "\012";

            $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
            $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
            $pdf->ezText($header, 12, array(
                'justification' => 'left',
                'leading' => 12
            ));
//        error_log("page height is " . $pdf->ez['pageHeight']);
//        error_log("footer is " . $footer);

            if (!$continued) {
                $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 140);
                $pdf->ezText($altered_body, 12, array(
                    'justification' => 'left',
                    'leading' => 12
                ));

                $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
                $pdf->ezText($altered_footer, 12, array(
                    'justification' => 'left',
                    'leading' => 12
                ));
            } else {
                $combined_body = $altered_held_body . $altered_body;
                $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 140);
                $pdf->ezText($combined_body, 12, array(
                    'justification' => 'left',
                    'leading' => 12
                ));

                $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
                $pdf->ezText($altered_footer, 12, array(
                    'justification' => 'left',
                    'leading' => 12
                ));
                $was_continued = true;
            }
            $continued = 0;
        } else {
            $altered_body = '';
            if ($continued == 0) {
                $altered_held_body = '';
            }
            $continued++;
            $blines = explode("\012", $body); // form feeds may separate pages
            $bline_count = 0;
            $bline_count = count($blines);
            //error_log("blines count is $bline_count");
            $i = 0;
            do {
                $altered_body .= $blines[$i];
                //error_log($i . " " . $blines[$i] . "<br>");
                $i++;
            } while ($i < ($bline_count - 4));
            
            $altered_held_body .= $altered_body;
        }
    }
    
}

function printOnePage($page) {
    global $pdf;
    global $body_start;
    global $body_length;
    global $footer_length;
    $pdf->ezNewPage();
    $header = substr($page, 0, $body_start);
    $body = substr($page, $body_start, $body_length);
    $footer = substr($page, $footer_start, $footer_length);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
    $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
    $pdf->ezText($header, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));
//        error_log("page height is " . $pdf->ez['pageHeight']);
//        error_log("footer is " . $footer);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 130);
    $pdf->ezText($body, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
    $pdf->ezText($footer, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));        
}

function buildPage($page) {
    $pdf->ezNewPage();
    $header = substr($page, 0, $body_start);
    $body = substr($page, $body_start, $body_length);
    $footer = substr($page, $footer_start, $footer_length);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
    $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
    $pdf->ezText($header, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));
//        error_log("page height is " . $pdf->ez['pageHeight']);
//        error_log("footer is " . $footer);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 130);
    $pdf->ezText($body, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
    $pdf->ezText($footer, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));        
}

function printMultipage($page) {
    $pdf->ezNewPage();
    $header = substr($page, 0, $body_start);
    $body = substr($page, $body_start, $body_length);
    $footer = substr($page, $footer_start, $footer_length);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
    $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
    $pdf->ezText($header, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));
//        error_log("page height is " . $pdf->ez['pageHeight']);
//        error_log("footer is " . $footer);

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 130);
    $pdf->ezText($body, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));

    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 560);
    $pdf->ezText($footer, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));        
}

$fname = tempnam($GLOBALS['temporary_files_dir'], 'PDF');
file_put_contents($fname, $pdf->ezOutput());
// Send the content for view.
header("Pragma: public");
header("Expires: 0");
header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
header('Content-type: application/pdf');
header('Content-Disposition: inline; filename="new_wqwq"');
header('Content-Transfer-Encoding: binary');
header('Content-Length: ' . filesize($fname));
ob_end_clean();
@readfile($fname);
unlink($fname);
exit();
