<?php

/**
 * Runs Process Results for one lab (or all) on a site, as the Electronic
 * Reports button does, and prints a summary without patient details: the
 * returned error, patient-match prompts, and an error/info line per file.
 *
 * Usage, inside the openemr container as apache:
 *   php poll-labs.php --site=<site> [--lab=<ppid>]     (no --lab: all labs)
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$options = getopt('', ['site:', 'lab:']);
$site = is_array($options) && is_string($options['site'] ?? null) ? $options['site'] : '';
$labOption = is_array($options) ? ($options['lab'] ?? '0') : '0';
$lab = is_string($labOption) && ctype_digit($labOption) ? (int) $labOption : -1;
if ($site === '' || $lab < 0) {
    fwrite(STDERR, "usage: php poll-labs.php --site=<site> [--lab=<ppid>]\n");
    exit(2);
}

$ignoreAuth = true;
$sessionAllowWrite = true;
// globals.php selects the site for CLI scripts from $_GET['site'].
// @phpstan-ignore openemr.forbiddenRequestGlobals
$_GET['site'] = $site;
require '/var/www/localhost/htdocs/openemr/interface/globals.php';
require '/var/www/localhost/htdocs/openemr/interface/orders/receive_hl7_results.inc.php';

$info = [];
$error = poll_hl7_results($info, $lab);
echo "returned: ", ($error === '' ? '(no error)' : $error), "\n";
$matches = $info['match'] ?? [];
echo "patient match prompts: ", is_array($matches) ? count($matches) : 0, "\n";
// One line per file the labs saw: error, info, or just seen. Keys are
// "<lab>/<ppid>/<file>"; message texts are left out (they can name patients).
foreach ($info as $key => $value) {
    if ($key === 'match' || $key === 'select' || !is_array($value)) {
        continue;
    }
    $messages = is_array($value['mssgs'] ?? null) ? $value['mssgs'] : [];
    if ($messages === []) {
        echo "seen  ", $key, "\n";
    }
    foreach ($messages as $message) {
        echo is_string($message) && str_starts_with($message, '*') ? 'error ' : 'info  ', $key, "\n";
    }
}
