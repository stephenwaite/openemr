<?php

ini_set('max_execution_time', '0');
$ignoreAuth = true;
$_GET['site'] = 'default';
$argv = $_GET['argv'];
require_once(dirname(__FILE__) . "/../interface/globals.php");

$pdf = new Cezpdf('LETTER');
$pdf->ezSetMargins(170, 0, 10, 0);
$pdf->selectFont('Courier');
$page_count = 0;
$continued = false;
$is_continued = false;
$was_continued = false;
$slew = false;
$body_count = 0;

$content = file_get_contents('wqwq');
$pages = explode("\014", $content); // form feeds separate pages
foreach ($pages as $page) {
    $last_body_count = $body_count;
    $body_count = 0;
    $body_start = strpos($page, chr(032)) +2;

    if ($footer_start = strpos($page, chr(034))) {
        $slew = true;
        $body_length = $footer_start - $body_start;
        $footer_length = $length - $footer_start;
    };
    
    $page_lines = explode("\012", $page);
    $page_lines_count = count($page_lines);

    $was_continued = $is_continued;

    if (!strpos($page, "CONTINUED")) {         
        $is_continued = false;
    } else {        
        $is_continued = true;
    }

    $header = '';
    for ($i = 0; $i < 5; $i++) {
        $header .= $page_lines[$i];
    }

    $body = '';
    for ($i = 5; $i < ($page_lines_count - 4); $i++) {        
        $body .= $page_lines[$i];
        $body_count++;
    }

    $footer = '';
    for ($i = ($page_lines_count - 3); $i < $page_lines_count; $i++) {
        if ($page_lines[$i] == '') {
            $footer .= $page_lines[$i] . "\r";
        }
        $footer .= $page_lines[$i];
    }    

    if (!$is_continued && !$was_continued) {
        printHeader($header, $pdf);
        printBody($body, $pdf);
        printFooter($footer, $pdf);
    }

    if ($is_continued && !$was_continued) {
        $old_body .= $body;
    }

    if (!$is_continued && $was_continued) {
        $old_body .= $body;
        printHeader($header, $pdf);
        printBody($old_body, $pdf);
        printFooter($footer, $pdf);
        $old_body = '';
    }
}

function printHeader($header, $pdf) {
    $pdf->ezNewPage();        
    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
    $pdf->addPngFromFile("image.png", 0, 0, 612, 792);
    $pdf->ezText($header, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));
}

function printBody($content, $pdf) {
    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 130);
    $pdf->ezText($content, 12, array(
        'justification' => 'left',
        'leading' => 12
    ));
}

function printFooter($footer, $pdf) { 
    $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin'] - 570);
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
