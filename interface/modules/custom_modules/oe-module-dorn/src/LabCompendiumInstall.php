<?php

/**
 *
 * @package OpenEMR
 * @link    http://www.open-emr.org
 *
 * @author    Brad Sharp <brad.sharp@claimrev.com>
 * @copyright Copyright (c) 2022 Brad Sharp <brad.sharp@claimrev.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */
namespace OpenEMR\Modules\Dorn;

use OpenEMR\Modules\Dorn\ConnectorApi;
use OpenEMR\Modules\Dorn\LabRouteSetup;

class LabCompendiumInstall
{
    public static function install($labGuid)
    {
        $compendiumResponse = ConnectorApi::getCompendium($labGuid);

        if ($compendiumResponse->isSuccess) {
            $result = LabRouteSetup::getProcedureIdProviderByLabGuid($labGuid);
            while ($record = sqlFetchArray($result)) {
                $lab_id = $record["ppid"];
                LabCompendiumInstall::uninstall($lab_id);
                foreach ($compendiumResponse->compendium->orderableItems as $item) {
                    LabCompendiumInstall::loadOrderableItem($item);
                }
                
    
    
            }
            

        } else {
            echo "Error Getting Compendium! " . $compendiumResponse->responseMessage;
        }
    }
    public static function loadOrderableItem($item)
    {

    }
    public static function uninstall($lab_id)
    {
        sqlStatement("DELETE FROM procedure_type WHERE lab_id = ? AND (procedure_type = 'det' OR procedure_type = 'res') ", array($lab_id));
        // Mark everything else for the indicated lab as inactive.
        sqlStatement("UPDATE procedure_type SET activity = 0, related_code = '' WHERE lab_id = ? AND procedure_type != 'grp' ", array($lab_id));

    }
}
