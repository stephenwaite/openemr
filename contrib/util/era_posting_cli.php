<?php

/**
 * Create an array of pids for whitelisting the patient filter for a chart review
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use OpenEMR\Billing\ParseERA;
use OpenEMR\Billing\SLEOB;

// This is called back by ParseERA::parseERA() if we are processing X12 835's.
function era_callback_cli(&$out)
{
    global $where, $eracount, $eraname;
    // print_r($out); // debugging
    ++$eracount;
    // $eraname = $out['isa_control_number'];
    // since it's always sent we use isa_sender_id if payer_id is not provided
    $eraname = $out['gs_date'] . '_' . ltrim($out['isa_control_number'], '0') .
        '_' . ltrim($out['payer_id'] ? $out['payer_id'] : $out['isa_sender_id'], '0');

    if (!empty($out['our_claim_id'])) {
        list($pid, $encounter, $invnumber) = SLEOB::slInvoiceNumber($out);
        if ($pid && $encounter) {
            if ($where) {
                $where .= ' OR ';
            }

            $where .= "( f.pid = '" . add_escape_custom($pid) . "' AND f.encounter = '" . add_escape_custom($encounter) . "' )";
        }
    }
}
echo "checking tmp dir \n";
$path = '/tmp/mdb';
if ($handle = opendir($path)) {
    while (false !== ($tmp_name = readdir($handle))) {
        if ($tmp_name != "." && $tmp_name != "..") {
            echo "$tmp_name\n";
            $alertmsg .= ParseERA::parseERA($path . '/' . $tmp_name, 'era_callback_cli') ?? '';
            $erafullname = $GLOBALS['OE_SITE_DIR'] . "/documents/era/$eraname.edi";
            if (is_file($erafullname)) {
                $alertmsg .=  xl("Warning") . ': ' . xl("Set") . ' ' . $eraname . ' ' . xl("was already uploaded") . ' ';
                if (is_file($GLOBALS['OE_SITE_DIR'] . "/documents/era/$eraname.html")) {
                    $Processed = 1;
                    $alertmsg .=  xl("and processed.") . ' ';
                } else {
                    $alertmsg .=  xl("but not yet processed.") . ' ';
                };
            }
            copy($path . '/' . $tmp_name, $erafullname);
            $_SESSION['authUserID'] = 1;
            require_once(__DIR__ . '/../../interface/billing/sl_eob_process.php');
        
        }
    }
    closedir($handle);
}

exit;



