<?php

/**
 * Raise fees by a percentage
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default 3 0\n";
    echo "will raise fees by 3% if set to true\n";
    die;
}

$_GET['site'] = $argv[1] ?? 'default';
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

$feeBump = 1 + (.01 * floatval($argv[2] ?? 0));
$liveRun = boolval($argv[3] ?? 0);

$codes_sql = sqlStatement("SELECT * FROM `codes`");

while ($code = sqlFetchArray($codes_sql)) {
        $prices_sql = sqlQuery("SELECT `codes`.*, `prices`.`pr_id`, `prices`.`pr_price` as fee FROM `codes` LEFT JOIN `prices` ON `prices`.`pr_id` = `codes`.`id` WHERE `code` = ? AND `modifier` = ?", [$code['code'], $code['modifier']]);
    if (!empty($prices_sql)) {
        $priceId = $prices_sql['id'];
        $ourCode = $prices_sql['code'];
        $ourFee = $prices_sql['fee'];
        $ourMod = trim($prices_sql['modifier'] ?? '');
    } else {
        continue;
    }

    if (empty(intval($ourFee))) {
        continue;
    }

    $newFee = number_format(ceil($ourFee * $feeBump), 2, '.', '');
    $format = "raise existing fee by of %7.2f for %5s : %2s to %7.2f";
    echo sprintf($format, $ourFee, $ourCode, $ourMod, $newFee) . "\n";
    if ($liveRun) {
        echo "update prices table for code $ourCode:$ourMod from " . $ourFee .
            " to " . $newFee . " with price id " . $priceId . "\n";
        $update_prices = sqlQuery("UPDATE `prices` SET `pr_price` = ? WHERE `pr_id` = ?", [$newFee, $priceId]);
    }
}
