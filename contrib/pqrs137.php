<?php

/* measure 137 melanoma: continuity of care
*/

ini_set('max_execution_time', '0');
$ignoreAuth = true;
$_GET['site'] = 'default';
//$argv = $_GET['argv'];
require_once(dirname(__FILE__) . "/../interface/globals.php");

$diags = array('C43.0', 'C43.10', 'C43.111', 'C43.112', 'C43.121', 'C43.122', 'C43.20', 'C43.21', 'C43.22', 'C43.30',
    'C43.31', 'C43.39', 'C43.4', 'C43.51', 'C43.52', 'C43.59',
    'C43.60', 'C43.61', 'C43.62', 'C43.70', 'C43.71', 'C43.72',
    'C43.8', 'C43.9', 'D03.0', 'D03.10', 'D03.111', 'D03.112', 'D03.121', 'D03.122',
    'D03.20', 'D03.21', 'D03.22', 'D03.30', 'D03.39', 'D03.4',
    'D03.51', 'D03.52', 'D03.59', 'D03.60', 'D03.61', 'D03.62',
    'D03.70', 'D03.71', 'D03.72', 'D03.8', 'D03.9',
    );

// have to put Z85.820 back in for pqrs137

//$cpts = array('99201', '99202', '99203', '99204', '99205', '99211', '99212', '99213', '99214', '99215');
$cpts = array('11600', '11601', '11602', '11603', '11604', '11606',
'11620', '11621', '11622', '11623', '11624', '11626', '11640', '11641', '11642', '11643', '11644', '11646', '14000', '14001',
'14020', '14021', '14040', '14041', '14060', '14061', '14301', '17311', '17313');

$query = "SELECT b.pid, b.encounter, fe.date, b.code, b.modifier, fe.pos_code FROM billing AS b " .
    "INNER JOIN form_encounter AS fe USING (encounter) " .
    "WHERE (fe.date > '2019-12-31' AND fe.date < '2021-01-01') " .
    "AND (fe.pos_code NOT IN ('02', '99')) ORDER BY b.pid";
// have to take out above fe.pos_code if looking for surgery cpts just in case billed weird

$result = sqlStatement($query);

while ($row = sqlFetchArray($result)) {
    $pqrs137[$row['encounter']][] = [
        'enc'  => $row['encounter'],
        'cpt'  => $row['code'],
        'pid'  => $row['pid'],
        'date' => $row['date'],
        'mod'  => $row['mod']
    ];
}

$encs    = array_filter($pqrs137, 'checkCpt');
$encs    = array_filter($encs, 'checkDx');

foreach ($encs as $dx_enc) {
    foreach ($dx_enc as $arr) {
        if (in_array($arr['cpt'], $diags)) {
            $one_pid[$arr['pid']] = $arr;
        }
    }
}

foreach ($one_pid as $arr) {
    echo $arr['pid'] . ", " . $arr['enc'] . ", " . $arr['cpt'] . ", " . $arr['mod'] . ", " .
        substr($arr['date'],0, 10) . "\n";
}

function checkCpt($arr) {
    global $cpts;
    foreach ($arr as $enc) {
        if (in_array($enc['cpt'], $cpts)) {
            if (! in_array($enc['mod'], ['GQ', 'GT', '95'])) {
                return true;
            }
        }
    }
}

function checkDx($arr) {
    global $diags;
    foreach ($arr as $enc) {
        if (in_array($enc['cpt'], $diags)) {
            return true;
        }
    }
}





