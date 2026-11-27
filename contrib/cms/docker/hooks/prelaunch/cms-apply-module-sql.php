<?php

/**
 * Runs a custom module's table.sql on one OpenEMR site the way Modules >
 * Install does (helper for the 20-cms-site-settings prelaunch hook; not run by
 * run-parts itself). OpenEMR's SQLUpgradeService honors the #IfMissingColumn
 * and #IfNotRow guards in the file, so tables and columns the site already has
 * are left alone and the missing ones are added.
 *
 * Usage: CMS_SITE=<site> CMS_MODULE=<module folder> php < cms-apply-module-sql.php
 * Run it as apache: it loads OpenEMR, which writes its caches as the web user.
 * The hook feeds it on stdin, since apache cannot read /root/hooks.
 *
 * Exit status: 0 applied, 3 the module is not installed on the site (nothing
 * done), anything else failed.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

use OpenEMR\Services\Utils\SQLUpgradeService;

$siteName = (string) getenv('CMS_SITE');
$moduleName = (string) getenv('CMS_MODULE');
$oeRoot = (string) (getenv('OE_ROOT') ?: '/var/www/localhost/htdocs/openemr');
if ($siteName === '' || preg_match('/^[A-Za-z0-9_-]+$/', $moduleName) !== 1) {
    fwrite(STDERR, "usage: CMS_SITE=<site> CMS_MODULE=<module folder> php < cms-apply-module-sql.php\n");
    exit(2);
}
$moduleDir = "{$oeRoot}/interface/modules/custom_modules/{$moduleName}";
$sqlconfFile = "{$oeRoot}/sites/{$siteName}/sqlconf.php";
if (!is_file("{$moduleDir}/table.sql") || !is_file($sqlconfFile)) {
    fwrite(STDERR, "no {$moduleDir}/table.sql or no {$sqlconfFile}\n");
    exit(1);
}

// Is the module installed here? Asked over a plain connection first, so a site
// without the module (most of them) doesn't load OpenEMR at every start.
// sqlconf.php sets $host, $port, $login, $pass, $dbase and $config.
$conf = (static function (string $file): array {
    require $file;
    return get_defined_vars();
})($sqlconfFile);
$setting = static fn(string $name): string => is_scalar($conf[$name] ?? null) ? (string) $conf[$name] : '';
if ($setting('config') === '' || $setting('config') === '0') {
    exit(3);
}
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$installed = false;
$error = null;
try {
    $db = new mysqli($setting('host'), $setting('login'), $setting('pass'), $setting('dbase'), (int) $setting('port'));
    // $moduleName is letters, digits, _ and - only (checked above).
    $result = $db->query("SELECT 1 FROM `modules` WHERE `mod_directory` = '{$moduleName}' AND `sql_run` = 1 LIMIT 1");
    $installed = $result instanceof mysqli_result && $result->num_rows > 0;
    $db->close();
} catch (mysqli_sql_exception $e) {
    $error = $e->getMessage();
}
if ($error !== null) {
    fwrite(STDERR, "{$siteName}: {$error}\n");
    exit(1);
}
if (!$installed) {
    exit(3);
}

$ignoreAuth = true;
// globals.php selects the site for CLI scripts from $_GET['site'].
// @phpstan-ignore openemr.forbiddenRequestGlobals
$_GET['site'] = $siteName;
require "{$oeRoot}/interface/globals.php";

// A statement that fails throws, and the uncaught exception ends the script
// with a nonzero status, which the hook reports.
$upgrade = new SQLUpgradeService();
$upgrade->setRenderOutputToScreen(false);
$upgrade->setThrowExceptionOnError(true);
$upgrade->upgradeFromSqlFile('table.sql', $moduleDir);
exit(0);
