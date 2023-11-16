<?php
/**
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
namespace OpenEMR\Common\ProcedureTools;

use OpenEMR\Common\ProcedureTools\GenHl7OrderBase;
use OpenEMR\Common\Logging\EventAuditLogger;
use OpenEMR\Modules\Dorn\ConnectorApi;

class DornGenHl7Order extends GenHl7OrderBase
{
    public function __construct()
    {
    }
    public static function isDornLab($ppid)
    {
        $sql = "SHOW TABLES LIKE 'mod_dorn_routes'";
        $result = sqlQuery($sql);
        if ($result === false) {
            return false;
        }

        $sql = "SELECT 1 FROM mod_dorn_routes WHERE ppid = ?";
        $dornRecord = sqlQuery($sql, [$ppid]);
        if ($dornRecord !== false) {
            return true;
        }
        return false;
    }

    /**
     * Generate HL7 for the specified procedure order.
     *
     * @param integer $orderid Procedure order ID.
     * @param string &$out     Container for target HL7 text.
     * @param string &$reqStr
     * @return string            Error text, or empty if no errors.
     */
    public function genHl7Order($orderid, &$out, &$reqStr)
    {

        error_log('here!!!!!!!!!!!!!!!!!!!!!');
        // Delimiters
        $d0 = "\r";
        $d1 = '|';
        $d2 = '^';

        $today = time();
        $out = '';
        // init 2d barcode req record arrays
        for ($i = 0; $i < 98; $i++) {
            if ($i < 6) {
                $H[$i] = '';
            }
            if ($i < 9) {
                $G[$i] = '';
            }
            if ($i < 27) {
                $C[$i] = '';
            }
            if ($i < 41) {
                $A[$i] = '';
                $T[$i] = '';
            }
            $P[$i] = '';
        }
        $H[0] = 'H';
        $C[0] = 'C';
        $C[19] = '^';
        $A[0] = 'A';
        $M[0] = 'M';
        $T[0] = 'T';
        $O[0] = 'O';
        $S[0] = 'S';
        $G[0] = 'G';
        $D[0] = 'D';
        $L[0] = 'L';
        $E[0] = 'E';
        $A[21] = "^^";
        $A[22] = "^";
        $A[23] = "^";
        $A[29] = "^";
        $A[30] = "^^^^^";
        $A[33] = "^^^";
        $G[1] = "^";
        $S[1] = "^^^^^^";
        $P[0] = 'P';
        $P[36] = "^";
        $P[45] = "^";
        $P[54] = "^^^^^^^^^^^^^^";
        $P[55] = "^^^^^^^";
        $P[72] = "^^";
        $P[73] = "^^";
        $P[74] = "^^";
        $P[75] = "^^";
        $P[79] = "^";
        $P[85] = "^";
        $P[86] = "^^^^";
        $P[89] = "^";
        $P[94] = "^";
        $P[95] = "^^";
        $B = "B|||||||||||||||||||||";
        $K = "K|^|||||||||||||||^^^^||||||";
        $I = "I|^^|^^|^^|^^|^^|^^|^^|^^|";

        $porow = sqlQuery(
            "SELECT " .
            "po.date_collected, po.date_ordered, po.order_priority,po.billing_type,po.clinical_hx,po.account,po.order_diagnosis, " .
            "pp.*, " .
            "pd.pid, pd.pubpid, pd.fname, pd.lname, pd.mname, pd.DOB, pd.ss, pd.race, " .
            "pd.phone_home, pd.phone_biz, pd.sex, pd.street, pd.city, pd.state, pd.postal_code, " .
            "f.encounter, u.fname AS docfname, u.lname AS doclname, u.npi AS docnpi, u.id as user_id " .
            "FROM procedure_order AS po, procedure_providers AS pp, " .
            "forms AS f, patient_data AS pd, users AS u " .
            "WHERE " .
            "po.procedure_order_id = ? AND " .
            "pp.ppid = po.lab_id AND " .
            "f.formdir = 'procedure_order' AND " .
            "f.form_id = po.procedure_order_id AND " .
            "pd.pid = f.pid AND " .
            "u.id = po.provider_id",
            array($orderid)
        );
        if (empty($porow)) {
            return "Procedure order, ordering provider or lab is missing for order ID '$orderid'";
        }

        $pcres = sqlStatement(
            "SELECT " .
            "pc.procedure_code, pc.procedure_name, pc.procedure_order_seq, pc.diagnoses " .
            "FROM procedure_order_code AS pc " .
            "WHERE " .
            "pc.procedure_order_id = ? AND " .
            "pc.do_not_send = 0 " .
            "ORDER BY pc.procedure_order_seq",
            array($orderid)
        );

        $pdres = sqlStatement(
            "SELECT " .
            "pc.procedure_code, pc.procedure_name, pc.procedure_order_seq, pc.diagnoses " .
            "FROM procedure_order_code AS pc " .
            "WHERE " .
            "pc.procedure_order_id = ? AND " .
            "pc.do_not_send = 0 " .
            "ORDER BY pc.procedure_order_seq",
            array($orderid)
        );

        $vitals = sqlQuery(
            "SELECT * FROM form_vitals v join forms f on f.form_id=v.id WHERE f.pid=? and f.encounter=? ORDER BY v.date DESC LIMIT 1",
            [$porow['pid'], $porow['encounter']]
        );
        $P[68] = $vitals['weight'];
        $P[70] = $vitals['height'];
        $P[88] = $vitals['bps'] . '^' . $vitals['bpd'];
        $P[89] = $vitals['waist_circ'];
        $C[17] = parent::hl7Date(date("Ymd", strtotime($porow['date_collected'])));

        // if (empty($porow['account'])) {
        //     return "ERROR! Missing this orders facility location account code (Facility Id) in Facility!";
        // }

        // Message Header
        
        $bill_type = strtoupper(substr($porow['billing_type'], 0, 1));
        $out .= $this->createMsh($porow['send_app_id'], $porow['send_fac_id'], $porow['recv_app_id'], $porow['recv_fac_id'], date('YmdHisO', $today), "", $orderid, "T", "", "", "AL", "NE", "", "", "", "");
        $out .= $this->createPid("1", "", $porow['pid'], "", $porow['fname'], $porow['lname'], $porow['mname'], "", $porow['DOB'], $porow['sex'], "", $porow['race'], $porow['street'], "", $porow['city'], $porow['state'], $porow['postal_code'], "", $porow['phone_home'], "", "", "", "", "", "", "", "", ""   );
        $out .= $this->createPv1("U", $bill_type);
        
        
        
        $H[1] = $porow['send_app_id'];
        $H[2] = date('Ymd', $today);
        $P[1] = $porow['pid'];
        $P[7] = $porow['recv_fac_id'];


        $P[9] = $this->hl7Text($porow['lname']) . '^' . $this->hl7Text($porow['fname']) . '^' . $this->hl7Text($porow['mname']);
        $P[10] = $this->hl7Date($porow['DOB']);
        $P[11] = $this->hl7Sex($porow['sex']);
        $P[12] = $this->hl7SSN($porow['ss']);
        $P[13] = $this->hl7Text($porow['street']);
        $P[14] = $this->hl7Text($porow['city']);
        $P[15] = $this->hl7Text($porow['state']);
        $P[16] = $this->hl7Zip($porow['postal_code']);
        $P[17] = $this->hl7Phone($porow['phone_home']);
        $P[57] = $orderid;
        $P[58] = $porow['pid'];


        if ($bill_type == 'T') {
            $P[18] = "XI";
        } elseif ($bill_type == 'P') {
            $P[18] = "03";
        } else {
            $P[18] = "04";
        }

        $P[29] =  $this->hl7Text($porow['doclname']) . "^" . $this->hl7Text($porow['docfname']);
        $P[30] =  $this->hl7Text($porow['docnpi']);
        $P[71] = $this->hl7Text($porow['docnpi']);
            // Insurance stuff.
        $payers = $this->loadPayerInfo($porow['pid'], $porow['date_ordered']);
        $setid = 0;
        if ($bill_type == 'T') {
            // only send primary and secondary insurance
            foreach ($payers as $payer) {
                $payer_object = $payer['object'];
                $payer_address = $payer_object->get_address();
                $full_address = $payer_address->get_line1();

                $payer_address1 = $payer_address->get_line1();
                $payer_address2 = $payer_address->get_line2();
                $payer_addressCity = $payer_address->get_city();
                $payer_addressState = $payer_address->get_state();
                $payer_addressZip = $payer_address->get_zip();
                $payer_addressPhone = $payer_object->get_phone();

                if (!empty($payer_address->get_line2())) {
                    $full_address .= "," . $payer_address->get_line2();
                }
                $out .= $this->createIn1(
                    $setid, "", $payer['company']['cms_id'], $payer['company']['name'], $payer_address1, 
                $payer_address2, $payer_addressCity, $payer_addressState, 
                $payer_addressZip, $payer_addressPhone, $payer['data']['group_number'], "", "", $payer['data']['subscriber_fname'], $payer['data']['subscriber_lname'], 
                $payer['data']['subscriber_mname'], $payer['data']['subscriber_relationship'], $payer['data']['subscriber_DOB'],
                $payer['data']['subscriber_street'],"",$payer['data']['subscriber_city'], $payer['data']['subscriber_state'],$payer['data']['subscriber_postal_code'], $payer['data']['policy_number'] );

                // $out .= "IN1" .
                //     $d1 . ++$setid .                                // Set ID
                //     $d1 .                                           // Insurance Plan Identifier ??
                //     $d1 . $this->hl7Text($payer['company']['id']) .        // Insurance Company ID
                //     $d2 . $this->hl7Text($payer['company']['cms_id']) .        // Insurance Carrier code
                //     $d1 . $this->hl7Text($payer['company']['name']) .    // Insurance Company Name
                //     $d1 . $this->hl7Text($full_address) .    // Street Address
                //     $d2 .
                //     $d2 . $this->hl7Text($payer_address->get_city()) .   // City
                //     $d2 . $this->hl7Text($payer_address->get_state()) .  // State
                //     $d2 . $this->hl7Zip($payer_address->get_zip()) .     // Zip Code
                //     $d1 .
                //     $d1 . $this->hl7Phone($payer_object->get_phone()) .    // Phone Number
                //     $d1 . $this->hl7Text($payer['data']['group_number']) . // Insurance Company Group Number
                //     str_repeat($d1, 7) .                            // IN1 9-15 all empty
                //     $d1 . $this->hl7Text($payer['data']['subscriber_lname']) .   // Insured last name
                //     $d2 . $this->hl7Text($payer['data']['subscriber_fname']) . // Insured first name
                //     $d2 . $this->hl7Text($payer['data']['subscriber_mname']) . // Insured middle name
                //     $d1 . $this->hl7Relation($payer['data']['subscriber_relationship']) .        //JC this may need to be edited JP this is okay!
                //     $d1 . $this->hl7Date($payer['data']['subscriber_DOB']) .     // Insured DOB
                //     $d1 . $this->hl7Text($payer['data']['subscriber_street']) .  // Insured Street Address
                //     $d2 .
                //     $d2 . $this->hl7Text($payer['data']['subscriber_city']) .  // City
                //     $d2 . $this->hl7Text($payer['data']['subscriber_state']) . // State
                //     $d2 . $this->hl7Zip($payer['data']['subscriber_postal_code']) . // Zip
                //     $d1 .
                //     $d1 .
                //     $d1 . $setid .                                  // 1=Primary, 2=Secondary, 3=Tertiary
                //     str_repeat($d1, 8) .                           // IN1-23 to 30 all empty
                //     $d1 . $this->hl7Workman($payer['data']['policy_type']) . // Policy Number
                //     str_repeat($d1, 4) .                           // IN1-32 to 35 all empty
                //     $d1 . $this->hl7Text($payer['data']['policy_number']) . // Policy Number
                //     str_repeat($d1, 12) .                           // IN1-37 to 48 all empty
                //     $d0;
                
                if ($payer_object->get_ins_type_code() === '2') { //medicare
                    $P[19] = $this->hl7Text($payer['data']['policy_number']);
                } elseif ($payer_object->get_ins_type_code() === '3') { // medicaid
                    $P[53] = $this->hl7Text($payer['data']['policy_number']);
                } else {
                    $P[40] = $this->hl7Text($payer['data']['policy_number']);
                }
                if ($setid === 2) {
                    $P[43] = $this->hl7Text($payer['company']['cms_id']);
                    $P[44] = $this->hl7Text($payer['company']['name']);
                    $P[45] = $this->hl7Text($full_address);
                    $P[46] = $this->hl7Text($payer_address->get_city());
                    $P[47] = $this->hl7Text($payer_address->get_state());
                    $P[48] = $this->hl7Zip($payer_address->get_zip());
                    $P[41] = $this->hl7Text($payer['data']['group_number']);
                    $P[52] = $this->hl7Workman($payer['data']['policy_type']);
                    break;
                }
                $P[34] = $this->hl7Text($payer['company']['cms_id']);
                $P[35] = $this->hl7Text($payer['company']['name']);
                $P[36] = $this->hl7Text($full_address);
                $P[37] = $this->hl7Text($payer_address->get_city());
                $P[38] = $this->hl7Text($payer_address->get_state());
                $P[39] = $this->hl7Zip($payer_address->get_zip());
                $P[41] = $this->hl7Text($payer['data']['group_number']);
                $P[52] = $this->hl7Workman($payer['data']['policy_type']);
            }
            if ($setid === 0) {
                return "\nInsurance is being billed but patient does not have any payers on record!";
            }
        } else { // no insurance record
            ++$setid;
            $out .= "IN1|$setid||||||||||||||||||||||||||||||||||||||||||||||$bill_type" . $d0;
        }

        $guarantors = $this->loadGuarantorInfo($porow['pid'], $porow['date_ordered']);
        foreach ($guarantors as $guarantor) {
            if (hl7Text($bill_type) != "C") {
                if ($guarantor['data']['subscriber_lname'] != "") {
                    // Guarantor. OpenEMR doesn't have these so use the patient.
                    $out .= "GT1" .
                        $d1 . "1" .                      // Set ID (always just 1 of these)
                        $d1 .
                        $d1 . $this->hl7Text($guarantor['data']['subscriber_lname']) .   // Insured last name
                        $d2 . $this->hl7Text($guarantor['data']['subscriber_fname']) . // Insured first name
                        $d2 . $this->hl7Text($guarantor['data']['subscriber_mname']); // Insured middle name
                    $out .=
                        $d1 .
                        $d1 . $this->hl7Text($guarantor['data']['subscriber_street']) .  // Insured Street Address
                        $d2 .
                        $d2 . $this->hl7Text($guarantor['data']['subscriber_city']) .  // City
                        $d2 . $this->hl7Text($guarantor['data']['subscriber_state']) . // State
                        $d2 . $this->hl7Zip($guarantor['data']['subscriber_postal_code']) . // Zip
                        $d1 . $this->hl7Phone($guarantor['data']['subscriber_phone']) .
                        $d1 .
                        $d1 . $this->hl7Date($guarantor['data']['subscriber_DOB']) .     // Insured DOB
                        $d1 . $this->hl7Sex($guarantor['data']['subscriber_sex']) .   // Sex: M, F or U
                        $d1 .
                        $d1 . $this->hl7Relation($guarantor['data']['subscriber_relationship']) .        //JC this may need to be edited JP this is okay!

                        $d1 . $this->hl7Date($guarantor['data']['subscriber_ss']) .     // Insured ssn
                        $d0;
                }
            }
            $P[20] = $this->hl7Text($guarantor['data']['subscriber_lname']) . '^' . $this->hl7Text($guarantor['data']['subscriber_fname']) . '^';
            $P[21] = $this->hl7Date($guarantor['data']['subscriber_ss']);
            $P[22] = $this->hl7Text($guarantor['data']['subscriber_street']);
            $P[23] = $this->hl7Text($guarantor['data']['subscriber_city']);
            $P[24] = $this->hl7Text($guarantor['data']['subscriber_state']);
            $P[25] = $this->hl7Zip($guarantor['data']['subscriber_postal_code']);
            // $P[26] = // employer;
            $P[27] = $this->hl7Relation($guarantor['data']['subscriber_relationship']);
            $P[56] = $this->hl7Phone($guarantor['data']['subscriber_phone']);
        }

        $setid2 = 0;
        // this gets the order default codes
        $relcodes = explode(';', $porow['order_diagnosis']);
        $relcodes = array_unique($relcodes);
        foreach ($relcodes as $codestring) {
            if ($codestring === '') {
                continue;
            }
            list($codetype, $code) = explode(':', $codestring);
            $desc = lookup_code_descriptions($codestring);
            $out .= "DG1" .
            $d1 . ++$setid2;
            $out .= $d1 . 'I10';
            $out .= $d1 . $code .
            $d1 . $this->hl7Text($desc) . $d0;
            // req
            if ($setid2 < 9) {
                $D[1] .= $code . '^';
            }
        }
        // now from each test order list
        while ($pdrow = sqlFetchArray($pdres)) {
            if (!empty($pdrow['diagnoses'])) {
                $relcodes = explode(';', $pdrow['diagnoses']);
                foreach ($relcodes as $codestring) {
                    if ($codestring === '') {
                        continue;
                    }
                    list($codetype, $code) = explode(':', $codestring);
                    $desc = lookup_code_descriptions($codestring);
                    $out .= "DG1" .
                    $d1 . ++$setid2;             // Set ID
                    $out .= $d1 . 'I10';         // Diagnosis Coding Method
                    $out .= $d1 . $code .        // Diagnosis Code
                    $d1 . $this->hl7Text($desc) . $d0;  // Diagnosis Description
                    if ($setid2 < 9) {
                        $D[1] .= $code . '^';
                    }
                }
            }
        }
        $D[1] = substr($D[1], 0, strlen($D[1]) - 1);
        $vvalue = strtoupper($_REQUEST['form_specimen_fasting']) == 'YES' ? "Y" : "N";
        $ht = str_pad(round($vitals['height']), 3, "0", STR_PAD_LEFT);
        $lb = floor((float)$vitals['weight']);
        $lb = str_pad($lb, 3, "0", STR_PAD_LEFT);
        $oz = round(((float)$vitals['weight'] * 16) - ($lb * 16));

        $out .= "ZCI|$ht|$lb^^$oz|0|$vvalue" . $d0;
        $setid = 0;
        while ($pcrow = sqlFetchArray($pcres)) {
            // Common Order.
            $out .= "ORC" .
                $d1 . "NW" .                     // New Order
                $d1 . $orderid .                 // Placer Order Number
                str_repeat($d1, 6) .             // ORC 3-8 not used
                $d1 . date('YmdHi') .           // Transaction date/time
                $d1 . $d1 .
                $d1 . $this->hl7Text($porow['docnpi']) .     // Ordering Provider
                $d2 . $this->hl7Text($porow['doclname']) . // Last Name
                $d2 . $this->hl7Text($porow['docfname']) . // First Name
                str_repeat($d2, 4) .
                $d2 . 'N' .
                str_repeat($d1, 7) .             // ORC 13-19 not used
                $d1 . "2" .                      // ABN Status: 2 = Notified & Signed, 4 = Unsigned
                $d0;

            // Observation Request.
            $specprocedure = sqlQuery("SELECT specimen FROM procedure_type WHERE procedure_code=?", [$pcrow['procedure_code']]);
            $out .= "OBR" .
                $d1 . ++$setid .                              // Set ID
                $d1 . $orderid .                              // Placer Order Number
                $d1 .
                $d1 . $this->hl7Text($pcrow['procedure_code']) .
                $d2 . $this->hl7Text($pcrow['procedure_name']) .
                $d2 . 'L' .
                $d1 . $this->hl7Priority($porow['order_priority']) . // S=Stat, R=Routine
                $d1 .
                $d1 . $this->hl7Time($porow['date_collected']) .     // Observation Date/Time
                str_repeat($d1, 3) .                          // OBR 8-15 not used
                $d1 . 'N' .
                str_repeat($d1, 1) .
                $d1 . $this->hl7Text($porow['clinical_hx']) . //clinical info
                $d1 .
                $d1 . $specprocedure['specimen'] .          // was 4
                $d1 . $this->hl7Text($porow['docnpi']) .           // Physician ID
                $d2 . $this->hl7Text($porow['doclname']) .         // Last Name
                $d2 . $this->hl7Text($porow['docfname']) .         // First Name
                str_repeat($d2, 4) .
                $d2 . 'N' .
                $d1 .
                $d1 . //(count($payers) ? 'I' : 'P') .          // I=Insurance, C=Client, P=Self Pay
                str_repeat($d1, 8) .                          // OBR 19-26 not used
                $d1 . '0' .                                   // ?
                $d0;

            // Order entry questions and answers.
            $qres = sqlStatement(
                "SELECT " .
                "a.question_code, a.answer, q.fldtype , q.tips " .
                "FROM procedure_answers AS a " .
                "LEFT JOIN procedure_questions AS q ON " .
                "q.lab_id = ? " .
                "AND q.procedure_code = ? AND " .
                "q.question_code = a.question_code " .
                "WHERE " .
                "a.procedure_order_id = ? AND " .
                "a.procedure_order_seq = ? " .
                "ORDER BY q.seq, a.answer_seq",
                array($porow['ppid'], $pcrow['procedure_code'], $orderid, $pcrow['procedure_order_seq'])
            );

            $setid2 = 0;
            $fastflag = false;
            while ($qrow = sqlFetchArray($qres)) {
                // Formatting of these answer values may be lab-specific and we'll figure
                // out how to deal with that as more labs are supported.
                $answer = trim($qrow['answer']);
                $qcode = trim($qrow['question_code']);
                $fldtype = $qrow['fldtype'];
                $datatype = 'ST';
                if ($qcode == 'FASTIN') {
                    $fastflag = true;
                }
                if ($fldtype == 'N') {
                    $datatype = "NM";
                } elseif ($fldtype == 'D') {
                    $answer = $this->hl7Date($answer);
                } elseif ($fldtype == 'G') {
                    $weeks = intval($answer / 7);
                    $days = $answer % 7;
                    $answer = $weeks . 'wks ' . $days . 'days';
                }
                $out .= "OBX" .
                $d1 . ++$setid2 .                           // Set ID
                $d1 . $datatype .                           // Structure of observation value
                $d1 . $qrow['tips'] .                       // Clinical question code
                $d1 .
                $d1 . $this->hl7Text($answer) .                    // Clinical question answer
                $d1 .
                $d1 .
                $d1 .
                $d1 .
                $d1 . "N" .
                $d1 . "F" .
                $d0;
            }
            $vvalue = strtoupper($_REQUEST['form_specimen_fasting']) === 'YES' ? "Y" : "N";
            $C[24] = $vvalue === "Y" ? ($vvalue . '12') : $vvalue;
            $T[$setid] = $this->hl7Text($pcrow['procedure_code']);
            if ($vvalue === "Y" && $fastflag === false) {
                $out .= "OBX" .
                $d1 . ++$setid2 .
                $d1 . "ST" .
                $d1 . "FASTIN^FASTING^L" .
                $d1 . $d1 . $vvalue . $d1 . $d1 . $d1 . $d1 . $d1 . "N" . $d1 . "F" .
                $d0;
            }
        }

        $reqStr = "";
        for ($i = 0; $i < 6; $i++) {
            $reqStr .= $H[$i] . '|';
        }$reqStr .= "\x0D";
        for ($i = 0; $i < 98; $i++) {
            $reqStr .= $P[$i] . '|';
        }$reqStr .= "\x0D";
        for ($i = 0; $i < 27; $i++) {
            $reqStr .= $C[$i] . '|';
        }$reqStr .= "\x0D";
        for ($i = 0; $i < 41; $i++) {
            $reqStr .= $A[$i] . '|';
        }$reqStr .= "\x0D";
        for ($i = 0; $i < 41; $i++) {
            $reqStr .= $T[$i] . '|';
        }$reqStr .= "\x0D";
        for ($i = 0; $i < 6; $i++) {
            $reqStr .= $M[$i] . '|';
        }$reqStr .= "\x0D";

        $reqStr .= $D[0] . '|' . $D[1] . '||' . "\x0D";
        $l = strlen($reqStr);
        $reqStr .= "L|$l|\x0D";
        $reqStr .= 'E|0|' . "\x0D";
        $reqStr = strtoupper($reqStr);
        return '';
    }


    private function createIn1(
        $setId,
        $insPlanId,
        $insCompanyId,
        $insCompanyName,
        $insAddress1,
        $insAddress2,
        $insCity,
        $insState,
        $insZip,
        $insPhone,
        $groupNumber,
        $insuredGroupEmpName,
        $planExpDate,
        $subscriberFirstName,
        $subscriberLastName,
        $subscriberMiddleName,
        $relationship,
        $subscriberDob,
        $subscriberAddress1,
        $subscriberAddress2,
        $subscriberCity,
        $subscriberState,
        $subscriberZip,
        $policyNumber
    ):string {
        $fields = [
            $this->buildHL7Field($setId),
            $this->buildHL7Field($insPlanId),
            $this->buildHL7Field($insCompanyId),
            $this->buildHL7Field($insCompanyName),
            $this->buildHL7Field([$insAddress1, $insAddress2, $insCity, $insState, $insZip]), //5
            "",
            $this->buildHL7Field($insPhone),
            $this->buildHL7Field($groupNumber),
            "",
            "",//10
            $this->buildHL7Field($insuredGroupEmpName),
            $this->buildHL7Field($planExpDate),
            "",
            "",
            "",//15
            $this->buildHL7Field([$subscriberLastName, $subscriberFirstName, $subscriberMiddleName]),
            $this->buildHL7Field($relationship),
            $this->buildHL7Field($subscriberDob),
            $this->buildHL7Field([$subscriberAddress1, $subscriberAddress2, $subscriberCity, $subscriberState, $subscriberZip]),//19
            "",//20
            "",//21
            "",//22
            "",
            "",
            "",//25
            "",
            "",
            "",
            "",
            "",//30
            "",
            "",
            "",
            "",
            "",//35
            $this->buildHL7Field($policyNumber),

        ];
        $segment = $this->buildHl7Segment("IN1", $fields);
        return $segment;
    }
    private function createPv1(
        $patientClass,
        $financialClass
    ):string {
        $fields = [
            "1",
            $this->buildHL7Field($patientClass),
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            "",
            $this->buildHL7Field($financialClass)
        ];
        $segment = $this->buildHl7Segment("PV1", $fields);
        return $segment;
    }
    private function createPid(
        $setPid,
        $pid,
        $patientIdentList,
        $altPid,
        $patientFirstName,
        $patientLastName,
        $patientMiddleName,
        $mothersMaidenName,
        $dob,
        $adminSex,
        $patAlias,
        $race,
        $patAddressStreet,
        $patAddressStreet2,
        $patAddressCity,
        $patAddressState,
        $patAddressZip,
        $countryCode,
        $phoneHome,
        $phoneBus,
        $primaryLanguage,
        $maritalStatus,
        $religion,
        $patAccNumber,
        $patSsn,
        $patDriversLicense,
        $mothersId,
        $ethnicGroup,
    ):string {

        $fields = [
            $this->buildHL7Field($setPid),
            $this->buildHL7Field($pid),
            $this->buildHL7Field($patientIdentList),
            $this->buildHL7Field($altPid),
            $this->buildHL7Field([$patientLastName, $patientFirstName, $patientMiddleName]),
            $this->buildHL7Field($mothersMaidenName),
            $this->hl7Date($dob),
            $this->hl7Sex($adminSex),
            $this->buildHL7Field($patAlias),
            $this->hl7Race($race),
            $this->buildHL7Field([$patAddressStreet, $patAddressStreet2, $patAddressCity, $patAddressState, $patAddressZip]),
            $this->buildHL7Field($countryCode),
            $this->buildHL7Field($phoneHome),
            $this->buildHL7Field($phoneBus),
            $this->buildHL7Field($primaryLanguage),
            $this->buildHL7Field($maritalStatus),
            $this->buildHL7Field($religion),
            $this->buildHL7Field($patAccNumber),
            $this->buildHL7Field($patSsn),
            $this->buildHL7Field($patDriversLicense),
            $this->buildHL7Field($mothersId),
            $this->buildHL7Field($ethnicGroup),
        ];


        $segment = $this->buildHl7Segment("PID", $fields);
        return $segment;
    }
    private function createMsh(
        $sendingApplication,
        $sendingFacility,
        $receivingApplication,
        $receivingFacility,
        $msgDateTime,
        $security,
        $msgCtrlId,
        $processingId,
        $sequenceNumber,
        $continuationPointer,
        $acceptAckType,
        $applicationAckType,
        $countryCode,
        $characterSet,
        $principleLangMsg,
        $altCharScheme
    ):string {

        // Combine encoding characters
        $encodingCharacters = $this->componentSeparator .
                              $this->repetitionSeparator .
                              $this->escapeSeparator .
                              $this->subComponentSeparator;
        $fields = [
            $encodingCharacters, //POS 1 & 2
            $this->buildHL7Field($sendingApplication),// POS 3
            $this->buildHL7Field($sendingFacility),//POS 4 - per dorn this should be the account number
            $this->buildHL7Field($receivingApplication),//POS 5
            $this->buildHL7Field($receivingFacility),//POS 6
            $this->buildHL7Field($msgDateTime),//POS 7
            $this->buildHL7Field($security),//POS 8
            $this->buildHL7Field(["OML","021","OML_021"]),//POS 9
            $this->buildHL7Field($msgCtrlId),//POS 10
            $this->buildHL7Field($processingId),//POS 11
            $this->buildHL7Field("2.5.1"),//POS 12
            $this->buildHL7Field($sequenceNumber),//POS 13
            $this->buildHL7Field($continuationPointer),//POS 14
            $this->buildHL7Field($acceptAckType),//POS 15
            $this->buildHL7Field($applicationAckType),//POS 16
            $this->buildHL7Field($countryCode),//POS 17
            $this->buildHL7Field($characterSet),//POS 18
            $this->buildHL7Field($principleLangMsg),//POS 19
            $this->buildHL7Field($altCharScheme),//POS 20
            $this->buildHL7Field("ELINCS_MT-OML-1_1.0 "),//POS 21
        ];

        foreach ($fields as $field) {
            $segment .= $this->fieldSeparator . $field;
        }
        $segment = "MSH" . $segment . $this->lineBreakChar;
        return $segment;
    }
    /**
     * Transmit HL7 for the specified lab.
     *
     * @param  integer $ppid  Procedure provider ID.
     * @param  string  $out   The HL7 text to be sent.
     * @return string         Error text, or empty if no errors.
     */
    public function sendHl7Order($ppid, $orderId, $out)
    {
        $responseMessage = "";
        global $srcdir;
        $pid = null;
        $porow = sqlQuery(
            "SELECT " .
            "po.date_collected, po.date_ordered, po.order_priority,po.billing_type,po.clinical_hx,po.account,po.order_diagnosis, " .
            "pp.*, " .
            "pd.pid, pd.pubpid, pd.fname, pd.lname, pd.mname, pd.DOB, pd.ss, pd.race, " .
            "pd.phone_home, pd.phone_biz, pd.sex, pd.street, pd.city, pd.state, pd.postal_code, " .
            "f.encounter, u.fname AS docfname, u.lname AS doclname, u.npi AS docnpi, u.id as user_id " .
            "FROM procedure_order AS po, procedure_providers AS pp, " .
            "forms AS f, patient_data AS pd, users AS u " .
            "WHERE " .
            "po.procedure_order_id = ? AND " .
            "pp.ppid = po.lab_id AND " .
            "f.formdir = 'procedure_order' AND " .
            "f.form_id = po.procedure_order_id AND " .
            "pd.pid = f.pid AND " .
            "u.id = po.provider_id",
            array($orderId)
        );
        if (!empty($porow)) {
            $pid = $porow['pid'];
        }

        $d0 = "\r";

        $ppSql ="SELECT * FROM procedure_providers AS pp
            INNER JOIN mod_dorn_routes AS mdr ON
                pp.ppid = mdr.ppid
            WHERE pp.ppid = ?";

        $pprow = sqlQuery($ppSql, array($ppid));
        if (empty($pprow)) {
            return xl('Procedure provider') . " $ppid " . xl('not found');
        }

        $labGuid = $pprow['lab_guid'];
        $labAccountNumber = $pprow['lab_account_number'];
        $protocol = $pprow['protocol'];

        // Extract MSH-10 which is the message control ID.
        $segmsh = explode(substr($out, 3, 1), substr($out, 0, strpos($out, $d0)));
        $msgid = $segmsh[9];
        if (empty($msgid)) {
            return xl('Internal error: Cannot find MSH-10');
        }


        if ($protocol == 'DL' || $pprow['orders_path'] === '') {
            header("Pragma: public");
            header("Expires: 0");
            header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
            header("Content-Type: application/force-download");
            header("Content-Disposition: attachment; filename=order_$msgid.hl7");
            header("Content-Description: File Transfer");
            echo $out;
            exit;
        } else {
            $response = ConnectorApi::sendOrder($labGuid, $labAccountNumber, $orderId, $pid, $out);
            if (!$response->isSuccess) {
                $responseMessage = $response->responseMessage;
            }
        }

        // Falling through to here indicates success.
        EventAuditLogger::instance()->newEvent(
            "proc_order_xmit",
            $_SESSION['authUser'],
            $_SESSION['authProvider'],
            1,
            "ID: $msgid Protocol: $protocol Host: DORN"
        );
        return $responseMessage;
    }
}
