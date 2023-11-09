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
        if ($compendiumResponse->isSuccess && $compendiumResponse->compendium) {
            $result = LabRouteSetup::getProcedureIdProviderByLabGuid($labGuid);
            while ($record = sqlFetchArray($result)) {
                $lab_id = $record["ppid"];
                LabCompendiumInstall::uninstall($lab_id);
                $parentId =LabCompendiumInstall::loadGroupRecord($compendiumResponse->compendium, $lab_id);
                foreach ($compendiumResponse->compendium->orderableItems as $item) {
                    LabCompendiumInstall::loadOrderableItem($item, $parentId, $lab_id);
                }
            }
            echo "Compendium has been updated for lab: " . $compendiumResponse->compendium->labName;
        } else {
            echo "Error Getting Compendium! " . $compendiumResponse->responseMessage;
        }
    }
    public static function loadGroupRecord($compendium, $lab_id)
    {
        $sql = "SELECT * FROM procedure_type WHERE parent = ? AND lab_id = ? AND procedure_type = ?";
        $parentRecord = sqlQuery($sql, [0, $lab_id, 'grp']);
  
        if ($parentRecord) {
            $sql = "SELECT * FROM procedure_type WHERE parent = ? AND lab_id = ? AND procedure_type = ? AND description = ?";
            $orderingTests = sqlQuery($sql, [$parentRecord["procedure_type_id"], $lab_id, "grp", "Ordering Tests"]);
            if ($orderingTests) {
                $orderingTestsId = $orderingTests["procedure_type_id"];
                return $orderingTestsId;
            }
        }


        $sql = "INSERT INTO procedure_type (name, lab_id, procedure_type, description) 
        VALUES (?, ?, ?, ?)";

        $sqlArr = array($compendium->labName, $lab_id, 'grp','DORN:' . $compendium->labName . ' Orders');
        $id = sqlInsert($sql, $sqlArr);

        $sql = "INSERT INTO procedure_type (parent,name, lab_id, procedure_type, description) 
                VALUES (?, ?, ?, ?, ?)";

        $sqlArr = array($id,$compendium->labName, $lab_id, 'grp','Ordering Tests');
        $id = sqlInsert($sql, $sqlArr);
    
       
        return $id;
    }
    public static function loadOrderableItem($item, $parentId, $lab_id)
    {
        if (!$item->loinc) {
            $item->loinc = "";
        }

        $sql = "SELECT procedure_type_id FROM procedure_type 
            WHERE lab_id = ? AND parent = ? AND procedure_code = ? AND procedure_type = ? AND standard_code = ?";
        $procOrder = sqlQuery($sql, [$lab_id ,$parentId, $item->code, "ord" ,$item->loinc]);
        if ($procOrder) {
            $id = $procOrder["procedure_type_id"];
            $sql = "UPDATE procedure_type SET Activity = ? WHERE procedure_type_id = ?";
            sqlStatement($sql, [1,$id]);
        } else {
            $sql = "INSERT INTO procedure_type (parent, name, lab_id, procedure_type, procedure_code, standard_code) 
            VALUES (?, ?, ?, ?, ?, ?)";
  
            $sqlArr = array($parentId, $item->name, $lab_id, 'ord', $item->code, $item->loinc);
            $id = sqlInsert($sql, $sqlArr);
        }

        foreach ($item->components as $component) {
            LabCompendiumInstall::loadResult($component, $id, $lab_id);
        }
    }
    public static function loadResult($component, $parentId, $lab_id)
    {
        echo "loading result";
        $sql = "INSERT INTO procedure_type (parent, name, lab_id, procedure_type, procedure_code, standard_code) 
        VALUES (?, ?, ?, ?, ?, ?)";
        $sqlArr = array($parentId, $component->name, $lab_id, 'res', $component->code, $component->loinc);
        $id = sqlInsert($sql, $sqlArr);
    }
    public static function uninstall($lab_id)
    {
        sqlStatement("DELETE FROM procedure_type WHERE lab_id = ? AND (procedure_type = 'det' OR procedure_type = 'res') ", array($lab_id));
        // Mark everything else for the indicated lab as inactive.
        sqlStatement("UPDATE procedure_type SET activity = 0, related_code = '' WHERE lab_id = ? AND procedure_type != 'grp' ", array($lab_id));
    }
}
