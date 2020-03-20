<?php
/**
 * Created by PhpStorm.
 * User: stee
 * Date: 3/3/19
 * Time: 11:31 AM
 */
require_once("../globals.php");
require_once("$srcdir/forms.inc");
require_once("$srcdir/patient.inc");
require_once("$srcdir/options.inc.php");

sqlStatement("TRUNCATE billing");


set_time_limit(0);
// exit;

if (!empty($_POST)) {
    if (!verifyCsrfToken($_POST["csrf_token_form"])) {
        csrfNotVerified();
    }
}

echo "<pre>";

//$cms = array();
// q5 is special report from sid-l off of charcur
$fh = fopen('q5','r');
$line = fgets($fh);

while ($line = fgets($fh)) {
    // <... Do your work with the line ...>
    // echo($line);
    /*FD  CHARCUR.
    01  CHARCUR01.
    02 CHARCUR-KEY.          count // offset (starts at 0)
    03 CC-KEY8 PIC X(8).       8 // 0
    03 CC-KEY3 PIC XXX.       11 // 7
    02 CC-PATID PIC X(8).     19 // 10
    02 CC-CLAIM PIC X(6).     25 // 18
    02 CC-SERVICE PIC X.      26 // 24
    02 CC-DIAG PIC X(7).      33 // 25
    02 CC-PROC PIC X(7).      40 // 32
    02 CC-MOD2 PIC XX.        42
    02 CC-MOD3 PIC XX.        44
    02 CC-MOD4 PIC XX.        46
    02 CC-AMOUNT PIC S9(4)V99.52
    02 CC-DOCR PIC X(3).      55
    02 CC-DOCP PIC X(2).      57
    02 CC-PAYCODE PIC XXX.    60
    02 CC-STUD PIC X.         61
    02 CC-WORK PIC XX.        63
    02 CC-DAT1 PIC X(8).      71
    02 CC-RESULT PIC X.       72
    02 CC-ACT PIC X.          73
    02 CC-SORCREF PIC X.      74
    02 CC-COLLT PIC X.        75
    02 CC-AUTH PIC X.         76
    02 CC-PAPER PIC X.        77
    02 CC-PLACE PIC X.        78
    02 CC-EPSDT PIC X.        79
    02 CC-DATE-T PIC X(8). // 87
    02 CC-DATE-A PIC X(8).    95
    02 CC-DATE-P PIC X(8).   103
    02 CC-REC-STAT PIC X.    104
    02 CC-DX2 PIC X(7).      111
    02 CC-DX3 PIC X(7).      118
    02 CC-ACC-TYPE PIC X.    119
    02 CC-DATE-M PIC X(8).   127
    02 CC-ASSIGN PIC X.      128
    02 CC-NEIC-ASSIGN PIC X. 129
    02 CC-DX4 PIC X(7).      136
    02 CC-DX5 PIC X(7).      143
    02 CC-DX6 PIC X(7).      150
    02 CC-FUTURE PIC X(6).   156 */
    $garno = substr($line,0,8);
    $dos = substr($line, 79, 8);
    $cpt = substr($line, 33, 5);
    $mod = trim(substr($line, 38, 2));
    $dx1 = substr($line, 26, 3) . "." . trim(substr($line, 29, 4));
    //$cms['paycode'] = substr($line, 57, 3);

    if (($dos < "20190101") or ($dos > "20191231")) {
        continue;
    }
    //echo $cms['dos']. "\n";
    $startdate = $dos;
    $enddate   = $dos;

    $sql = "select pid from patient_data where pubpid = ? limit 1";
    $row = sqlQuery($sql, array($garno));
    $pid = $row['pid'];


    //echo "pid is $pid startdate is $startdate enddate is $enddate </br>";
    $enc = getEncounters($pid, $startdate, $enddate);
    //var_dump($enc);
    $enc_id = $enc[0]['encounter'];
    //echo "encounter id is " . $cms['enc_id'] . "</br>";

    //print_r($cms);
    //if ($cms['ins_code'] == '003') {
    //$ins_query = "INSERT INTO insurance_data(type, provider, date, pid) VALUES ('primary', ?, ?, ?)";
    //sqlStatement($ins_query, array($cms['ins_code'], $cms['dos'], $cms['pt_id']));
    //}

    $query = "INSERT INTO billing (`date`, code_type, code, pid, provider_id, encounter, modifier) VALUES (NOW(), ?, ?, ?, ?, ?, ?)";

    sqlStatement($query, array('CPT4', $cpt . $mod, $pid, '2', $enc_id, $mod));

    echo "garno is $garno and dx1 is $dx1 </br>";
    sqlStatement($query, array('ICD10', $dx1, $pid, '2', $enc_id, ''));

    $dx2 = substr($line, 104, 3) . "." . trim(substr($line, 107, 4));
    echo "garno is $garno and dx2 is $dx2 </br>";
    if ($dx2 !== '000.0000') {
        sqlStatement($query, array('ICD10', $dx2, $pid, '2', $cms['enc_id'], ''));
    } else {
        continue;
    }

    $dx3 = substr($line, 111, 3) . "." . trim(substr($line, 114, 4));
    echo "garno is $garno and dx3 is $dx3 </br>";
    if ($dx3 !== '000.0000') {
        error_log("prob in dx_tmp3 " . $dx_tmp3 . " is pt " . $pid . " and enc " . $cms['enc_id']);
        sqlStatement($query, array('ICD10', $dx_tmp3, $pid, '2', $cms['enc_id'], ''));

    } else {
        continue;
    }

    echo "garno is $garno and dx4 is $dx4 </br>";
    $dx4 = substr($line, 129, 3) . "." . trim(substr($line, 132, 4));
    if ($dx4 !== '000.0000') {
        sqlStatement($query, array('ICD10', $dx_tmp4, $pid, '2', $cms['enc_id'], ''));
    } else {
        continue;
    }


}

fclose($fh);


