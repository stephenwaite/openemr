<?php

/**
 * Update the codes and prices tables by a percentage increase
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_LOAD_FEE_SCHEDULE')) {
    die('Set OPENEMR_ENABLE_LOAD_FEE_SCHEDULE=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php update_fee_schedule_by_percentage.php default 5.0 [dry_run]\n";
    echo "  - default: site name\n";
    echo "  - 5.0: percentage increase (e.g., 5.0 for 5% increase)\n";
    echo "  - dry_run: optional - show what would change without making changes\n";
    die;
}

if (!isset($argv[2])) {
    throw new RuntimeException("This script requires 2 arguments: site percentage [dry_run]");
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

$percentage = floatval($argv[2]);
$dry_run = isset($argv[3]) && strtolower($argv[3]) === 'dry_run';

if ($dry_run) {
    echo "***** DRY RUN MODE - NO CHANGES WILL BE MADE *****\n";
}
echo "Starting prices table increase...\n";
echo "Percentage increase: $percentage%\n";
echo str_repeat('-', 80) . "\n";

// Get all codes with prices
$sql = "SELECT `codes`.`id`, `codes`.`code`, `codes`.`modifier`, `codes`.`fee`,
               `prices`.`pr_id`, `prices`.`pr_price`
        FROM `codes`
        LEFT JOIN `prices` ON `prices`.`pr_id` = `codes`.`id`
        WHERE `prices`.`pr_price` IS NOT NULL AND `prices`.`pr_price` > 0
        ORDER BY `codes`.`code`, `codes`.`modifier`";

$price_records = sqlStatement($sql);

$count_processed = 0;
$count_errors = 0;

while ($price = sqlFetchArray($price_records)) {
    $old_fee = floatval($price['pr_price']);
    $new_fee = $old_fee * (1 + ($percentage / 100));
    $new_fee = round($new_fee); // Round to whole dollars
    $new_fee = number_format($new_fee, 2, '.', '');

    $code = $price['code'];
    $modifier = trim($price['modifier'] ?? '');
    $pr_id = $price['pr_id'];
    $code_id = $price['id'];

    try {
        if (!$dry_run) {
            // Update the prices table
            $update_prices_sql = "UPDATE `prices` SET `pr_price` = ? WHERE `pr_id` = ?";
            sqlQuery($update_prices_sql, [$new_fee, $pr_id]);

            // Update the codes table fee field
            $update_codes_sql = "UPDATE `codes` SET `fee` = ? WHERE `id` = ?";
            sqlQuery($update_codes_sql, [$new_fee, $code_id]);
        }

        echo sprintf(
            "%s %s:%s from %7.2f to %7.2f (+%.2f%%)\n",
            $dry_run ? "Would increase" : "Increased",
            $code,
            $modifier ?: 'none',
            $old_fee,
            $new_fee,
            $percentage
        );

        $count_processed++;

    } catch (Exception $e) {
        echo "ERROR processing $code:$modifier - " . $e->getMessage() . "\n";
        $count_errors++;
    }
}

echo str_repeat('-', 80) . "\n";
if ($dry_run) {
    echo "***** DRY RUN COMPLETE - NO CHANGES WERE MADE *****\n";
}
echo "Summary:\n";
echo "  Prices " . ($dry_run ? "that would be updated" : "updated") . ": $count_processed\n";
echo "  Errors encountered: $count_errors\n";
echo "\nDone!\n";
