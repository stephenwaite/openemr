<?php

/* This is a template for printing patient statements and collection
 * letters.  You must customize it to suit your practice.  If your
 * needs are simple then you do not need programming experience to do
 * this - just read the comments and make appropriate substitutions.
 * All you really need to do is replace the [strings in brackets].
 *
 * @package OpenEMR
 * @author Rod Roark <rod@sunsetsystems.com>
 * @copyright Copyright (c) 2006 Rod Roark <rod@sunsetsystems.com>
 * @author Bill Cernansky <bill@mi-squared.com>
 * @copyright Copyright (c) 2009 Bill Cernansky <bill@mi-squared.com>
 * @author Tony McCormick <tony@mi-squared.com>
 * @copyright Copyright (c) 2009 Tony McCormick <tony@mi-squared.com>
 * @author Raymond Magauran <magauran@medfetch.com>
 * @copyright Copyright (c) 2016 Raymond Magauran <magauran@medfetch.com>
 * @author Jerry Padgett <sjpadgett@gmail.com>
 * @copyright Copyright (c) 2017 Jerry Padgett <sjpadgett@gmail.com>
 * @author Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2020 Stephen Waite <stephen.waite@cmsvt.com>
 * @author Daniel Pflieger <daniel@growlingflea.com>
 * @copyright Copyright (c) 2018 Daniel Pflieger <daniel@growlingflea.com>
 * @link https://github.com/openemr/openemr/tree/master
 * @license https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

use OpenEMR\Common\Crypto\CryptoGen;

// The location/name of a temporary file to hold printable statements.
// May want to alter these names to allow multi-site installs out-of-the-box

$STMT_TEMP_FILE = $GLOBALS['temporary_files_dir'] . "/openemr_statements.txt";
$STMT_TEMP_FILE_PDF = $GLOBALS['temporary_files_dir'] . "/openemr_statements.pdf";
$STMT_PRINT_CMD = (new CryptoGen())->decryptStandard($GLOBALS['more_secure']['print_command']);

/** There are two options to print a batch of PDF statements:
 *  1.  The original statement, a text based statement, using CezPDF
 *      Altering this statement is labor intensive, but capable of being altered any way desired...
 *
 *  2.  Branded Statement, whose core is build from 1., the original statement, using mPDF.
 *
 *      To customize 2., add your practice location/images/practice_logo.gif
 *      In the base/default install this is located at '/openemr/sites/default/images/practice_logo.gif',
 *      Adjust directory paths per your installation.
 *      Further customize 2. manually in functions report_2() and create_HTML_statement(), below.
 *
 */
function make_statement($stmt)
{
    return create_statement($stmt);
}
    /* This function builds a printable statement or collection letter from
    // an associative array having the following keys:
    //
    //  today   = statement date yyyy-mm-dd
    //  pid     = patient ID
    //  patient = patient name
    //  amount  = total amount due
    //  to      = array of addressee name/address lines
    //  lines   = array of lines, each with the following keys:
    //    dos     = date of service yyyy-mm-dd
    //    desc    = description
    //    amount  = charge less adjustments
    //    paid    = amount paid
    //    notice  = 1 for first notice, 2 for second, etc.
    //    detail  = associative array of details
    //
    // Each detail array is keyed on a string beginning with a date in
    // yyyy-mm-dd format, or blanks in the case of the original charge
    // items.  Its values are associative arrays like this:
    //
    //  pmt - payment amount as a positive number, only for payments
    //  src - check number or other source, only for payments
    //  chg - invoice line item amount amount, only for charges or
    //        adjustments (adjustments may be zero)
    //  rsn - adjustment reason, only for adjustments
    //
    // The returned value is a string that can be sent to a printer.
    // This example is plain text, but if you are a hotshot programmer
    // then you could make a PDF or PostScript or whatever peels your
    // banana.  These strings are sent in succession, so append a form
    // feed if that is appropriate.
    //

    // A sample of the text based format follows:

    //[Your Clinic Name]             Patient Name          2009-12-29
    //[Your Clinic Address]          Chart Number: 1848
    //[City, State Zip]              Insurance information on file
    //
    //
    //ADDRESSEE                      REMIT TO
    //Patient Name                     [Your Clinic Name]
    //patient address                  [Your Clinic Address]
    //city, state zipcode              [City, State Zip]
    //                                 If paying by VISA/MC/AMEX/Dis
    //
    //Card_____________________  Exp______ Signature___________________
    //                     Return above part with your payment
    //-----------------------------------------------------------------
    //
    //_______________________ STATEMENT SUMMARY _______________________
    //
    //Visit Date  Description                                    Amount
    //
    //2009-08-20  Procedure 99345                                198.90
    //            Paid 2009-12-15:                               -51.50
    //... more details ...
    //...
    //...
    // skipping blanks in example
    //
    //
    //Name: Patient Name              Date: 2009-12-29     Due:   147.40
    //_________________________________________________________________
    //
    //Please call if any of the above information is incorrect
    //We appreciate prompt payment of balances due
    //
    //[Your billing contact name]
    //  Billing Department
    //  [Your billing dept phone]
    */

function create_statement($stmt)
{
    if (! $stmt['pid']) {
        return ""; // get out if no data
    }

    #minimum_amount_to _print
    if ($stmt['amount'] <= ($GLOBALS['minimum_amount_to_print']) && $GLOBALS['use_statement_print_exclusion'] && ($_REQUEST['form_category'] != "All")) {
        return "";
    }

    // These are your clinics return address, contact etc.  Edit them.
    // TBD: read this from the facility table

    // Facility (service location) modified by Daniel Pflieger at Growlingflea Software
    $service_query = sqlStatement("SELECT * FROM `form_encounter` fe join facility f on fe.facility_id = f.id where fe.id = ?", array($stmt['fid']));
    $row = sqlFetchArray($service_query);
    $clinic_name = "{$row['name']}";
    $clinic_addr = "{$row['street']}";
    $clinic_csz = "{$row['city']}, {$row['state']}, {$row['postal_code']}";


    // Billing location modified by Daniel Pflieger at Growlingflea Software
    $service_query = sqlStatement("SELECT * FROM `form_encounter` fe join facility f on fe.billing_facility = f.id where fe.id = ?", array($stmt['fid']));
    $row = sqlFetchArray($service_query);
    $remit_name = "{$row['name']}";
    $remit_addr = "{$row['street']}";
    $remit_csz = "{$row['city']}, {$row['state']}, {$row['postal_code']}";


    // Contacts
    $atres = sqlStatement("select f.attn,f.phone from facility f " .
        " left join users u on f.id=u.facility_id " .
        " left join  billing b on b.provider_id=u.id and b.pid = ?  " .
        " where billing_location=1", [$stmt['pid']]);
    $row = sqlFetchArray($atres);
    $billing_contact = "{$row['attn']}";
    $billing_phone = "{$row['phone']}";

    // dunning message setup

    // insurance has paid something
    // $stmt['age'] how old is the invoice
    // $stmt['dun_count'] number of statements run
    // $stmt['level_closed'] <= 3 insurance 4 = patient

    if ($GLOBALS['use_dunning_message']) {
        if ($stmt['ins_paid'] != 0 || $stmt['level_closed'] == 4) {
            // do collection messages
            switch ($stmt['age']) {
                case $stmt['age'] <= $GLOBALS['first_dun_msg_set']:
                    $dun_message = $GLOBALS['first_dun_msg_text'];
                    break;
                case $stmt['age'] <= $GLOBALS['second_dun_msg_set']:
                    $dun_message = $GLOBALS['second_dun_msg_text'];
                    break;
                case $stmt['age'] <= $GLOBALS['third_dun_msg_set']:
                    $dun_message = $GLOBALS['third_dun_msg_text'];
                    break;
                case $stmt['age'] <= $GLOBALS['fourth_dun_msg_set']:
                    $dun_message = $GLOBALS['fourth_dun_msg_text'];
                    break;
                case $stmt['age'] >= $GLOBALS['fifth_dun_msg_set']:
                    $dun_message = $GLOBALS['fifth_dun_msg_text'];
                    break;
            }
        }
    }

    // Text only labels

    $label_addressee = xl('ADDRESSED TO');
    $label_remitto = xl('REMIT TO');
    $label_chartnum = xl('Chart Number');
    $label_insinfo = xl('Insurance information on file');
    $label_totaldue = xl('Total amount due');
    $label_payby = xl('If paying by');
    $label_cards = xl('VISA/MC/Discovery/HSA');
    $label_cardnum = xl('Card');
    $label_expiry = xl('Exp');
    $label_cvv = xl('CVV');
    $label_sign = xl('Signature');
    $label_retpay = xl('Return above part with your payment');
    $label_pgbrk = xl('STATEMENT SUMMARY');
    $label_visit = xl('Visit Date');
    $label_desc = xl('Description');
    $label_amt = xl('Amount');

    // This is the text for the top part of the page, up to but not
    // including the detail lines.  Some examples of variable fields are:
    //  %s    = string with no minimum width
    //  %9s   = right-justified string of 9 characters padded with spaces
    //  %-25s = left-justified string of 25 characters padded with spaces
    // Note that "\n" is a line feed (new line) character.
    // reformatted to handle i8n by tony
    //$out = "\n\n";    
    $addrline = strtoupper(preg_replace('/\s+/', ' ', $stmt['to'][1]));
    $out  = sprintf("%-9s %-55s %6s \r\n", '', strtoupper($stmt['to'][0]), $stmt['pid']);
    $out .= sprintf("%-9s %-43s %-8s \r\n", '', $addrline, date('m d y'));
    $out .= "\r\n";
    $out .= sprintf("%-9s %-43s %-8s %9s\r\n", '', strtoupper($stmt['to'][2] ?? ''), date('m d y'), $stmt['amount']);

    if (($stmt['to'][3] ?? '') != '') { //to avoid double blank lines the if condition is put.
        $out .= sprintf("   %-32s\r\n", $stmt['to'][3]);
    }

    $out .= "\r\n";
    $out .= "\r\n";

    $header = $out;

    // This must be set to the number of lines generated above.
    //
    $count = 25;
    $num_ages = 4;
    $aging = array();
    for ($age_index = 0; $age_index < $num_ages; ++$age_index) {
        $aging[$age_index] = 0.00;
    }

    $todays_time = strtotime(date('Y-m-d'));

    // This generates the detail lines.  Again, note that the values must
    // be specified in the order used.
    //

    $agedate = '0000-00-00';

    $line_count = 0;
    $page_count = 0;
    $continued = false;
    $continued_text = '';
    foreach ($stmt['lines'] as $line) {
        $desc_row = sqlQuery("SELECT code_text from codes WHERE code = ?", array(substr($line['desc'], 10, 5)));
        $description = $desc_row['code_text'] ?? $line['desc'];

        //92002-14 are Eye Office Visit Codes

        $dos = $line['dos'];
        ksort($line['detail']);

        foreach ($line['detail'] as $dkey => $ddata) {
            if ($continued = true) {
                $out .= $continued_text;
            }
            $continued_text = '';

            $ddate = substr($dkey, 0, 10);
            if (preg_match('/^(\d\d\d\d)(\d\d)(\d\d)\s*$/', $ddate, $matches)) {
                $ddate = $matches[1] . '-' . $matches[2] . '-' . $matches[3];
            }

            $amount = '';
            
            if (strpos(($ddata['pmt_method'] ?? ''), ($insco ?? '')) !== false) {
                $insco = '';
            }

            if ($ddata['pmt'] ?? '') {
                $dos = $ddate;
                if ($dos > $agedate) {
                    $agedate = $dos;
                }
                $amount = sprintf("%.2f", $ddata['pmt']);
                $desc = xl('Paid') . ' ' . $ddata['src'] . ' ' . ($ddata['pmt_method'] ?? '') . ' ' . $insco;
                if ($ddata['src'] == 'Pt Paid' || $ddata['plv'] == '0') {
                    $pt_paid_flag = true;
                    $desc = xl('Pt paid');
                    //$out .= sprintf("%-8s %-44s           %8s  -%-8s \r\n", sidDate($dos), $desc, $amount, $amount);
                    $out .= sprintf("%-8s %-44s           %8s  \r\n", sidDate($dos), $desc, $amount);
                } else {
                    $out .= sprintf("%-8s %-44s           %8s\r\n", sidDate($dos), $desc, $amount);
                }
            } elseif ($ddata['rsn'] ?? '') {
                $dos = $ddate;
                if ($ddata['chg']) {
                    $amount = sprintf("%.2f", ($ddata['chg'] * -1));
                    $desc = xl('Adj') . ' ' . $ddata['rsn'] . ' ' . ($ddata['pmt_method'] ?? '') . ' ' . $insco;
                } else {
                    $desc = xl('Note') . ' ' . $ddata['rsn'] . ' ' . ($ddata['pmt_method'] ?? '') . ' ' . $insco;
                }
                $out .= sprintf("%-8s %-44s           %8s\r\n", sidDate($dos), $desc, $amount);
            } elseif ($ddata['chg'] < 0) {
                $amount = sprintf("%.2f", $ddata['chg']);
                $desc = xl('Patient Payment');
                $out .= sprintf("%-8s %-44s           %8s\r\n", sidDate($dos), $desc, $amount);
            } else {
                $amount = sprintf("%.2f", $ddata['chg']);
                $dos = $line['dos'];
                $desc = $description;
                $bal = sprintf("%.2f", ($line['amount'] - $line['paid']));
                $out .= sprintf("%-8s %-44s    %-8s          %-8s \r\n", sidDate($dos), $desc, $amount, $bal);
            }

            ++$count;
            ++$line_count;
            if ($line_count % 34 == 0) {
                $page_count++;
                $continued = true;
                $continued_text = "\r\n\r\n";
                $continued_text .= sprintf("                       %.2f", $stmt['amount']);
                $continued_text .= "CONTINUED PAGE $page_count \r\n";
                $continued_text .= "\014"; // this is a form feed                
            }
        }
        if ($agedate == '0000-00-00') {
            $agedate = $dos;
        }

        // Compute the aging bucket index and accumulate into that bucket.
        $age_in_days = (int) (($todays_time - strtotime($agedate)) / (60 * 60 * 24));
        $age_index = (int) (($age_in_days - 1) / 30);
        $age_index = max(0, min($num_ages - 1, $age_index));
        $aging[$age_index] += $line['amount'] - $line['paid'];
    }


    // This generates blank lines until we are at line 42.
    //
    while ($count++ < 62) {
        $out .= "\r\n";
    }

    # Generate the string of aging text.  This will look like:
    # Current xxx.xx / 31-60 x.xx / 61-90 x.xx / Over-90 xxx.xx
    # ....+....1....+....2....+....3....+....4....+....5....+....6....+
    #
    $ageline = sprintf(" %7.2f %10s %7.2f", $stmt['amount'], '', $aging[0]);
    for ($age_index = 1; $age_index < ($num_ages - 1); ++$age_index) {
        $ageline .= sprintf("   %7.2f", $aging[$age_index]);
    }

    // Fixed text labels
    $label_ptname = xl('Name');
    $label_today = xl('Date');
    $label_due = xl('Amount Due');
    $label_thanks = xl('Thank you for choosing');
    $label_call = xl('Please call if any of the above information is incorrect.');
    $label_prompt = xl('We appreciate prompt payment of balances due.');
    $label_dept = xl('Billing Department');
    $label_bill_phone = (!empty($GLOBALS['billing_phone_number']) ? $GLOBALS['billing_phone_number'] : $billing_phone );
    $label_appointments = xl('Future Appointments') . ':';

    /* This is the bottom portion of the page.
    if (strlen($stmt['bill_note']) != 0 && $GLOBALS['statement_bill_note_print']) {
        $out .= sprintf("%-46s\r\n", $stmt['bill_note']);
    }

    if ($GLOBALS['use_dunning_message']) {
        $out .= sprintf("%-46s\r\n", $dun_message);
    }


    if ($GLOBALS['statement_message_to_patient']) {
        $out .= "\r\n";
        $statement_message = $GLOBALS['statement_msg_text'];
        $out .= sprintf("%-40s\r\n", $statement_message);
    }
    */

    //if ($GLOBALS['show_aging_on_custom_statement']) {
        # code for ageing
        $ageline .= sprintf("%5s %.2f %12s %.2f", '', $aging[$age_index], '', $stmt['amount']);
        $out .= $ageline . "\r\n";
    //}

    /*
    if ($GLOBALS['number_appointments_on_statement'] != 0) {
        $out .= "\r\n";
        $num_appts = $GLOBALS['number_appointments_on_statement'];
        $next_day = mktime(0, 0, 0, date('m'), date('d') + 1, date('Y'));
        # add one day to date so it will not get todays appointment
        $current_date2 = date('Y-m-d', $next_day);
        $events = fetchNextXAppts($current_date2, $stmt['pid'], $num_appts);
        $j = 0;
        $out .= sprintf("%-s\r\n", $label_appointments);
        #loop to add the appointments
        for ($x = 1; $x <= $num_appts; $x++) {
            $next_appoint_date = oeFormatShortDate($events[$j]['pc_eventDate']);
            $next_appoint_time = substr($events[$j]['pc_startTime'], 0, 5);
            if (strlen(umname) != 0) {
                $next_appoint_provider = $events[$j]['ufname'] . ' ' . $events[$j]['umname'] .
                    ' ' .  $events[$j]['ulname'];
            } else {
                $next_appoint_provider = $events[$j]['ufname'] . ' ' .  $events[$j]['ulname'];
            }

            if (strlen($next_appoint_time) != 0) {
                $label_plsnote[$j] = xlt('Date') . ': ' . text($next_appoint_date) . ' ' . xlt('Time') .
                    ' ' . text($next_appoint_time) . ' ' . xlt('Provider') . ' ' . text($next_appoint_provider);
                $out .= sprintf("%-s\r\n", $label_plsnote[$j]);
            }

            $j++;
        }
    }
    */
    $out .= "\014"; // this is a form feed

    return $out;
}

function sidDate($date)
{
    return substr($date, 5, 2) . " " . substr($date, 8, 2) . " " . substr($date, 2, 2);
}

function rodDate($date)
{
    return substr($date, 0, 2) . " " . substr($date, 3, 2) . " " . substr($date, 6, 2);
}
