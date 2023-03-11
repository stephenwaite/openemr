<?php
exit;
// collect parameters (need to do before globals)
$_GET['site'] = $argv[1];
$ignoreAuth = 1;
require_once(__DIR__ . "/../../interface/globals.php");
$filename = $argv[2];

$sql = "select pid, encounter, date, facility from form_encounter where facility_id = '13' OR facility_id = '15' order by pid asc";
$res = sqlStatement($sql);

while ($row = sqlFetchArray($res)) {
    $icn = '';
    $pid = $row['pid'];
    $enc = $row['encounter'];
    $nsql = sqlQuery("SELECT fname, lname FROM patient_data where pid = ?", array($pid));
    $name = $nsql['lname'] . "; " . $nsql['fname'];
    $enc_date = substr($row['date'], 0, 10);
    $enc_no = $pid . "-" . $enc;
    //echo $row['pid'] . "-" . $row['encounter'] . " " . $enc_date . "\n";
    $query = "SELECT billing.`pid`, billing.`encounter`, insurance_companies.`name`, billing.`code`
                  FROM `insurance_companies`
                  INNER JOIN `billing` ON insurance_companies.`id` = billing.`payer_id`
                  WHERE billing.`pid` = ? AND billing.`encounter` = ? AND `code_type` = 'CPT4'";
    $rez = sqlStatement($query, array($pid, $enc));
    while ($billrow = sqlFetchArray($rez)) {
        if (!empty($proc)) {
            $proc = $billrow['code'];
        } else {
            $proc .= " " . $billrow['code'];
        }
    }
    $sql2 = "SELECT id.provider AS id, id.type, id.date, id.policy_number, " .
        "ic.x12_default_partner_id AS ic_x12id, ic.name AS provider " .
        "FROM insurance_data AS id, insurance_companies AS ic WHERE " .
        "ic.id = id.provider AND " .
        "id.pid = ? AND " .
        "(id.date <= ? OR id.date IS NULL) " .
        "ORDER BY id.type ASC, id.date DESC";
    $result = sqlStatement(
        $sql2,
        array(
        $pid,
        $enc_date
        )
    );
    while ($r = sqlFetchArray($result)) {
        if ($r['type'] == 'primary') {
            if ($file = fopen($filename, "r")) {
                while (!feof($file)) {
                    $csv = fgetcsv($file);
                    if (
                        (($csv[2] ?? null) == $enc_no)
                        && (
                            !(
                                ($csv[3] == '2')
                                || ($csv[3] == '4')
                            )
                        )
                    ) {
                        //var_dump($csv);
                        //echo "found ICN " . $csv[6] . " in csv file \n";
                        var_dump($csv);
                        $icn .= " " . $csv[6];
                    }
                }
            }
            if (strpos($r['provider'], 'MEDICARE B') !== false) {
                echo  $enc_no . "," . $name . "," . $r['policy_number'] . "," . $enc_date . "," . trim($icn) . "," . $proc . "," . $row['facility'] . "," .
                trim($r['provider']) . "," . $r['type'] . "\n";
            }
        }

        //var_dump($r);
    }
}
