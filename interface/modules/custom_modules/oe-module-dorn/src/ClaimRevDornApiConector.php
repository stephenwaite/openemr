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

use OpenEMR\Common\Http\HttpRestRequest;
use OpenEMR\Modules\Dorn\Bootstrap;

class ClaimRevDornApiConector
{
    public static function CreateRoute($routeData)
    {

    }
    public static function SearchLabs($labName, $phoneNumber, $faxNumber, $city, $state, $zipCode, $isActive, $isConnected)
    {
        $token = "";
        $content = 'content-type: application/json';
        $bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $globalsConfig = $bootstrap->getGlobalConfig();        
        $api_server = $globalsConfig->getApiServer();

        $url = $api_server . "/api/Labs/v1/SearchLabs";

        $bearer = 'authorization: Bearer ' . $token;
        $headers = [
            $content,
            $bearer
         ];
         
         $params = []; // Initialize an empty params array

         if (!empty($labName)) {
             $params['labName'] = $labName;
         }
         if (!empty($phoneNumber)) {
             $params['phoneNumber'] = $phoneNumber;
         }
         if (!empty($faxNumber)) {
             $params['faxNumber'] = $faxNumber;
         }
         if (!empty($city)) {
             $params['city'] = $city;
         }
         if (!empty($state)) {
             $params['state'] = $state;
         }
         if (!empty($zipCode)) {
             $params['zipCode'] = $zipCode;
         }
         if (!empty($isActive)) {
            if($isActive == "yes"){
                $params['isActive'] = "true";
            }
            else if($isActive == "no") {
                $params['isActive'] = "false";
            }  
         }
         if (!empty($isConnected)) {
            if($isConnected == "yes"){
                $params['isConnected'] = "true";
            }
            else if($isConnected == "no") {
                $params['isConnected'] = "false";
            }            
         }
       
        $url = $url . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode != 200) {
            return "";
        }
        $data = json_decode($result);
        return $data;
    }
    public static function SavePrimaryInfo($data)
    {
        $token = "";
        $content = 'content-type: application/json';
        $bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $globalsConfig = $bootstrap->getGlobalConfig();        
        $api_server = $globalsConfig->getApiServer();

        $url = $api_server . "/api/Customer/v1/SaveCustomerPrimaryInfo";

        $bearer = 'authorization: Bearer ' . $token;
        $headers = [
            $content,
            $bearer
         ];
        
        $payload = json_encode($data, JSON_UNESCAPED_SLASHES);
        error_log($payload);
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode != 200) {
            return "";
        }
        $data = json_decode($result);
        return $data;
    }
    public static function GetPrimaryInfoByNpi($npi)
    {
        $token = "";
        $content = 'content-type: application/json';
        $bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $globalsConfig = $bootstrap->getGlobalConfig();        
        $api_server = $globalsConfig->getApiServer();

        $url = $api_server . "/api/Customer/v1/GetPrimaryInfoByNpi";

        $bearer = 'authorization: Bearer ' . $token;
        $headers = [
            $content,
            $bearer
         ];
         
        if($npi){
            $params = array('npi' => $npi);
            $url = $url . '?' . http_build_query($params);
        }
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode != 200) {
            return "";
        }
        $data = json_decode($result);
        return $data;
    }
    public static function GetPrimaryInfos($npi)
    {
        $token = "";
        $content = 'content-type: application/json';
        $bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $globalsConfig = $bootstrap->getGlobalConfig();        
        $api_server = $globalsConfig->getApiServer();

        $url = $api_server . "/api/Customer/v1/SearchPrimaryInfo";

        $bearer = 'authorization: Bearer ' . $token;
        $headers = [
            $content,
            $bearer
         ];
         
        if($npi){
            $params = array('npi' => $npi);
            $url = $url . '?' . http_build_query($params);
        }
       
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode != 200) {
            return "";
        }
        $data = json_decode($result);
        return $data;
    }
}

?>