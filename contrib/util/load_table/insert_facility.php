<?php

/**
 * Create any number of facilities with default values
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Brady Miller <brady.g.miller@gmail.com>
 * @copyright Copyright (c) 2021 Brady Miller <brady.g.miller@gmail.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// comment this out when using this script (and then uncomment it again when done using script)
// exit;

if (php_sapi_name() !== 'cli' || count($argv) != 4) {
    echo "Only php cli can execute a command\n";
    echo "use: php import_ccda.php <ccda-directory> <site> <openemr-directory> <development-mode>\n";
    echo "example use: php import_ccda.php /var/www/localhost/htdocs/openemr/synthea default /var/www/localhost/htdocs/openemr true\n";
    echo "example use: php import_ccda.php /var/www/localhost/htdocs/openemr/synthea default /var/www/localhost/htdocs/openemr false\n";
    die;
}

function outputMessage($message)
{
    echo $message;
    file_put_contents("log.txt", $message, FILE_APPEND);
}

// collect parameters (need to do before globals)
$fac_num = $argv[1];
if (is_nan($fac_num)) {
  echo "please enter a number of facilities to create";
  die;
}

$_GET['site'] = $argv[2];
$openemrPath = $argv[3];

$ignoreAuth = 1;
require_once($openemrPath . "/interface/globals.php");

use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\FacilityService;

$facilityService = new FacilityService();
// show parameters (need to do after globals)
outputMessage("# of facilities: " . $argv[1] . "\n");
outputMessage("site: " . $_SESSION['site_id'] . "\n");
outputMessage("openemr path: " . $openemrPath . "\n");

outputMessage("Starting facility creation\n");
$data = [];
for($i = 0; $i < $fac_num; $i++) {
  $data['name'] = "facility" . $i;
  $data['facility_npi'] = rand(1000000000,1999999999);
  $data['primary_business_entity'] = 0;
  var_dump($facilityService->insert($data));
}
UuidRegistry::populateAllMissingUuids(false);

