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

 class LabRouteSetup
 {
    public static function CreateProcedureProviders($labName,$npi,$labGuid)
    {
        $send_app_id = "";
        $send_fac_id = ""; 
        $recv_app_id = "";
        $recv_fac_id = "";
        $DorP = "P";
        $direction = "B";
        $protocol = "fs";
        $remote_host = "";
        $orders_path = "/tmp/orders";
        $results_path = "/tmp/hl7";
        $notes = "created automatically - LabGuid:" . $labGuid;
        $lab_director= "5";
        $active = 1;
        $type = null;

      
        $sql_pp_insert = "INSERT INTO procedure_providers (name, npi, send_app_id, send_fac_id, recv_app_id
        ,recv_fac_id, DorP, direction, protocol, remote_host,orders_path,results_path,notes,lab_director, active,type) 
        VALUES (?, ?, ?, ?, ?,?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $sql_pp_insert_sqlarr = array($labName, $npi, $send_app_id, $send_fac_id, $recv_app_id,$recv_fac_id, $DorP, $direction
        ,$protocol, $remote_host,$orders_path,$results_path,$notes,$lab_director, $active,$type);

        $result = sqlStatement($sql_pp_insert, $sql_pp_insert_sqlarr);
       
        if (sqlNumRows($result) <= 0) {            
            $sql_pp_search = "SELECT ppid FROM procedure_providers WHERE npi = ? AND active = ? AND notes LIKE CONCAT('%', ?, '%') LIMIT 1";

            $sql_pp_search_sqlarr = array($npi, $active,$notes);
            $ppDataResult = sqlStatement($sql_pp_search,$sql_pp_search_sqlarr);
            $ppid = null; // Initialize the variable

            if (sqlNumRows($ppDataResult) == 1) {
                foreach ($ppDataResult as $row) {
                    $ppid = $row['ppid']; // Assuming 'ppid' is the first column in your SELECT statement.
                    break;
                }
            }
         
        }
        return $ppid;
    }
    public static function CreateDornRoute($labName,$routeGuid,$labGuid,$ppid)
    {
        $sql = "INSERT INTO mod_dorn_routes (lab_guid,lab_name,ppid,route_guid) VALUES (?,?,?,?)";
        $sqlarr = array($labGuid,$labName,$ppid,$routeGuid);
        $result = sqlStatement($sql, $sqlarr);
        if (sqlNumRows($result) <= 0) {
            return false;
        }
        return true;
    }
 }
?>