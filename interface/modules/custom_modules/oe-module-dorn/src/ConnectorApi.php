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

use OpenEMR\Modules\Dorn\Bootstrap;

class ConnectorApi
{
    public static function getCompendium($labGuid)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Labs/v1/" . $labGuid . "/Compendium";
        $returnData = ConnectorApi::getData($url);
        return $returnData;
    }
    public static function createRoute($data)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Route/v1/CreateRoute";
        return ConnectorApi::postData($url, $data);
    }
    public static function getLab($labGuid)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Labs/v1/" . $labGuid;
        $returnData = ConnectorApi::getData($url);
        return $returnData;
    }
    public static function searchLabs($labName, $phoneNumber, $faxNumber, $city, $state, $zipCode, $isActive, $isConnected)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Labs/v1/SearchLabs";
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
            if ($isActive == "yes") {
                $params['isActive'] = "true";
            } elseif ($isActive == "no") {
                $params['isActive'] = "false";
            }
        }
        if (!empty($isConnected)) {
            if ($isConnected == "yes") {
                $params['isConnected'] = "true";
            } elseif ($isConnected == "no") {
                $params['isConnected'] = "false";
            }
        }
       
        $url = $url . '?' . http_build_query($params);

        $returnData = ConnectorApi::getData($url);
        return $returnData;
    }

    public static function savePrimaryInfo($data)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Customer/v1/SaveCustomerPrimaryInfo";
        return ConnectorApi::postData($url, $data);
    }

    public static function getPrimaryInfoByNpi($npi)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Customer/v1/GetPrimaryInfoByNpi";
            
        if ($npi) {
            $params = array('npi' => $npi);
            $url = $url . '?' . http_build_query($params);
        }
       
        $returnData = ConnectorApi::getData($url);
        return $returnData;
    }
    public static function getPrimaryInfos($npi)
    {
        $api_server = ConnectorApi::getServerInfo();
        $url = $api_server . "/api/Customer/v1/SearchPrimaryInfo";

        if ($npi) {
            $params = array('npi' => $npi);
            $url = $url . '?' . http_build_query($params);
        }
       
        $returnData = ConnectorApi::getData($url);
        return $returnData;
    }


    public static function getData($url)
    {
        $headers = ConnectorApi::buildHeader();
         
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode == 200 || $httpcode == 400) {
            $responseJsonData = json_decode($result);
            return $responseJsonData;
        }
        error_log("Error " . "Status Code". $httpcode . " sending in api " . $url . " Message " . $result);
        return "";
    }
    public static function postData($url, $sendData)
    {
        $headers =ConnectorApi::buildHeader();
        $payload = json_encode($sendData, JSON_UNESCAPED_SLASHES);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        if ($httpcode == 200 || $httpcode == 400) {
            $responseJsonData = json_decode($result);
            return $responseJsonData;
        }
        error_log("Error " . "Status Code". $httpcode . " sending in api " . $url . " Message " . $result);
        return "";
    }
    public static function getServerInfo()
    {
        $bootstrap = new Bootstrap($GLOBALS['kernel']->getEventDispatcher());
        $globalsConfig = $bootstrap->getGlobalConfig();
        $api_server = $globalsConfig->getApiServer();
        return $api_server;
    }
    public static function buildHeader()
    {
        $token = "";
        $content = 'content-type: application/json';
        $bearer = 'authorization: Bearer ' . $token;
        $headers = [
            $content,
            $bearer
         ];
         return $headers;
    }
}
