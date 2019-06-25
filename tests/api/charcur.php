<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 6/21/19
 * Time: 12:16 PM
 */

require_once(dirname(__FILE__) . "/../../interface/globals.php");
//require_once (dirname(__FILE__) . "/../../library/patient.inc")

echo "<b>pt service call:</b><br>";
//$pat = new PatientService();


$handle = fopen("/tmp/wsteve", "r");
if ($handle) {
    while (($line = fgets($handle)) !== false) {
        // process the line read.
        $charcur_key = substr($line, 0, 11);
        $cc_key8 = substr($line, 0, 8);
        $cc_key3 = substr($line, 8, 3);
        echo "CHARCUR-KEY is $charcur_key";
        echo " CC-KEY8 is " . $cc_key8;
        echo "<br>";

        $sql = "select pid from patient_data where pubpid=?";
        $res = sqlStatement($sql, $cc_key8);
        $pat_array = sqlFetchArray($res);
        if ($pat_array['pid']) {
            echo "<br> pid is " . $pat_array['pid'] . "<br>";
            $pid = $pat_array['pid'];
            $pat->setPid("$pid");
            $patient = $pat->getOne();
            var_dump($patient);
            echo "<br><br>";
        } else {
            echo "<br><i>No match for $gar_no!</i><br><br>";
        }
    }
}

//
//        FD  CHARCUR
//*    BLOCK CONTAINS 3 RECORDS
//           DATA RECORD IS CHARCUR01.
//01  CHARCUR01.
//02 CHARCUR-KEY.
//03 CC-KEY8 PIC X(8).
//03 CC-KEY3 PIC XXX.
//02 CC-PATID PIC X(8).
//02 CC-CLAIM PIC X(6).
//02 CC-SERVICE PIC X.
//02 CC-DIAG PIC X(7).
//02 CC-PROC PIC X(7).
//02 CC-MOD2 PIC XX.
//02 CC-MOD3 PIC XX.
//02 CC-MOD4 PIC XX.
//02 CC-AMOUNT PIC S9(4)V99.
//02 CC-DOCR PIC X(3).
//02 CC-DOCP PIC X(2).
//02 CC-PAYCODE PIC XXX.
//02 CC-STUD PIC X.
//02 CC-WORK PIC XX.
//02 CC-DAT1 PIC X(8).
//02 CC-RESULT PIC X.
//02 CC-ACT PIC X.
//02 CC-SORCREF PIC X.
//02 CC-COLLT PIC X.
//02 CC-AUTH PIC X.
//02 CC-PAPER PIC X.
//02 CC-PLACE PIC X.
//02 CC-EPSDT PIC X.
//02 CC-DATE-T PIC X(8).
//02 CC-DATE-A PIC X(8).
//02 CC-DATE-P PIC X(8).
//02 CC-REC-STAT PIC X.
//02 CC-DX2 PIC X(7).
//02 CC-DX3 PIC X(7).
//02 CC-ACC-TYPE PIC X.
//02 CC-DATE-M PIC X(8).
//02 CC-ASSIGN PIC X.
//02 CC-NEIC-ASSIGN PIC X.
//02 CC-DX4 PIC X(7).
//02 CC-DX5 PIC X(7).
//02 CC-DX6 PIC X(7).
//02 CC-FUTURE PIC X(6).