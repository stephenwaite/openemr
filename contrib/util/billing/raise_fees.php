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
exit;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1] ?? 'default';
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

use League\Csv\Reader;

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


            $newFee = number_format(ceil($ourFee * 1.07), 2, '.', '');
            $format = "raise existing fee of %7.2f for %5s : %2s to %7.2f";
                echo sprintf($format, $ourFee, $ourCode, $ourMod, $newFee) . "\n";
                // uncomment below 3 lines to update prices accordingly
                //echo "update prices table for code $our_code:$our_mod from " . $our_fee .
                //    " to ". $ceil_fee . " with price id " . $price_id . "\n";
                //$update_prices = sqlQuery("UPDATE `prices` SET `pr_price` = ? WHERE `pr_id` = ?", [$newFee, $priceId]);
}
