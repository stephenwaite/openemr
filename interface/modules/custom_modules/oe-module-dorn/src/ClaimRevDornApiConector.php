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