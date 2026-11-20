<?php

/**
 * Decrypts an archived HL7 result file (sites/<site>/documents/procedure_results/)
 * so it can be imported again in a dry run. Archives are encrypted with the
 * site's keys when drive encryption is on. Every layer is removed (a file that
 * was re-imported while still encrypted gets a second one). Prints only the
 * layer count and segment types.
 *
 * Usage, inside the openemr container as apache:
 *   php decrypt-hl7.php --site=<site> --in=<archived file> --out=<output file>
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

$options = getopt('', ['site:', 'in:', 'out:']);
$site = is_array($options) && is_string($options['site'] ?? null) ? $options['site'] : '';
$in = is_array($options) && is_string($options['in'] ?? null) ? $options['in'] : '';
$out = is_array($options) && is_string($options['out'] ?? null) ? $options['out'] : '';
if ($site === '' || $in === '' || $out === '') {
    fwrite(STDERR, "usage: php decrypt-hl7.php --site=<site> --in=<archived file> --out=<output file>\n");
    exit(2);
}

$ignoreAuth = true;
$sessionAllowWrite = true;
// globals.php selects the site for CLI scripts from $_GET['site'].
// @phpstan-ignore openemr.forbiddenRequestGlobals
$_GET['site'] = $site;
require '/var/www/localhost/htdocs/openemr/interface/globals.php';

$content = file_get_contents($in);
if ($content === false) {
    fwrite(STDERR, "cannot read {$in}\n");
    exit(1);
}
$crypto = OpenEMR\BC\ServiceContainer::getCrypto();
$layers = 0;
while ($layers < 5 && $crypto->cryptCheckStandard($content)) {
    $content = $crypto->decryptFromFilesystem($content);
    $layers++;
}

preg_match_all('/(?:^|[\r\n\x0b])([A-Z][A-Z0-9]{2})\|/', $content, $matches);
echo "layers decrypted: {$layers}\n";
echo "segments: ", implode(' ', array_slice($matches[1], 0, 12)), "\n";
if (!in_array('MSH', $matches[1], true)) {
    fwrite(STDERR, "not an HL7 message after decryption; nothing written\n");
    exit(1);
}
file_put_contents($out, $content);
chmod($out, 0640);
echo "written: {$out}\n";
