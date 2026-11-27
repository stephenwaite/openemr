<?php

/**
 * Applies a SQL file to one OpenEMR site's database (helper for the
 * 20-cms-site-settings prelaunch hook; not run by run-parts itself).
 *
 * Usage: CMS_SQLCONF=<sites/<site>/sqlconf.php> CMS_SQL_FILE=<file.sql> php cms-apply-sql.php
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$sqlconfFile = (string) getenv('CMS_SQLCONF');
$sqlFile = (string) getenv('CMS_SQL_FILE');
if ($sqlconfFile === '' || $sqlFile === '') {
    fwrite(STDERR, "usage: CMS_SQLCONF=<sqlconf.php> CMS_SQL_FILE=<file.sql> php cms-apply-sql.php\n");
    exit(2);
}

$sql = file_get_contents($sqlFile);
if ($sql === false) {
    fwrite(STDERR, "cannot read {$sqlFile}\n");
    exit(1);
}

// A file of only comments (e.g. a site file with its settings commented out) has nothing to apply.
$statements = preg_replace(['#/\*.*?\*/#s', '/^\s*(--|\#).*$/m', '/[\s;]+/'], '', $sql);
if ($statements === '') {
    exit(0);
}

// sqlconf.php sets $host, $port, $login, $pass, $dbase and $config.
$site = (static function (string $file): array {
    require $file;
    return get_defined_vars();
})($sqlconfFile);
$setting = static fn(string $name): string => is_scalar($site[$name] ?? null) ? (string) $site[$name] : '';
if ($setting('config') === '' || $setting('config') === '0') {
    fwrite(STDERR, "{$sqlconfFile}: site is not configured\n");
    exit(1);
}

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$error = null;
try {
    $db = new mysqli($setting('host'), $setting('login'), $setting('pass'), $setting('dbase'), (int) $setting('port'));
    $db->set_charset('utf8mb4');
    $db->multi_query($sql);
    do {
        $result = $db->store_result();
        if ($result instanceof mysqli_result) {
            $result->free();
        }
    } while ($db->more_results() && $db->next_result());
} catch (mysqli_sql_exception $e) {
    $error = $e->getMessage();
}

if ($error !== null) {
    fwrite(STDERR, "{$sqlFile}: {$error}\n");
    exit(1);
}
