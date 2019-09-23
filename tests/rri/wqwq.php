<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 9/23/19
 * Time: 9:13 AM
 */
require_once("../../interface/globals.php");


$pdf = new Cezpdf('LETTER');
//$pdf->ezSetMargins(trim($_POST['top_margin']) + 0, 0, trim($_POST['left_margin']) + 0, 0);
$pdf->ezSetMargins(170, 0, 10, 20);
$pdf->selectFont('Courier');
$claim_count = 0;

$lines = file_get_contents('/tmp/wqwq');
$alines = explode("\014", $lines); // form feeds may separate pages
foreach ($alines as $tmplines) {
    if ($claim_count++) {
        $pdf->ezNewPage();
    }
    //$buffer = '';
    //error_log("string length is " . strlen($tmplines));
    $length = strlen($tmplines);
    if($length) {

        //for ($i = 0; $i < $length; $i++) {
            //error_log("buffer is $buffer with $tmplines[$i]");
            //$buffer .= $tmplines[$i];
        $body_start = strpos($tmplines, chr(032));
        $footer_start = strpos($tmplines, chr(034));
        $body_length = $footer_start - $body_start;
        $footer_length = $length - $footer_start;
     //   error_log("footer lenght is " . $footer_length);



        $header = substr($tmplines, 0, $body_start);
        $body = substr($tmplines,  $body_start, $body_length);
        $footer = substr($tmplines, $footer_start, $footer_length);

        $pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);
        $pdf->addPngFromFile("/tmp/rri.png", 0, 0, 612, 792);
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
                error_log("length is zero");
            }
        //}

    //$pdf->ezSetY($pdf->ez['pageHeight'] - $pdf->ez['topMargin']);

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
