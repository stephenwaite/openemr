<?php
require_once 'WSDLInterpreter.php';

function WSDLDownload($wsdl) {
	$wsdl_login = 'HORI44228test';
	$wsdl_password = 'Quest123!';
	$cache_dir = '/tmp/';
	$cache_url = '/tmp/';
	$file = md5(uniqid()).'.xml';

	if (($fp = fopen($cache_dir.$file, "w")) == false) {
		throw new Exception('Could not create local WSDL file ('.$cache_dir.$file.')');
	}

	$ch = curl_init();
	$credit = ($wsdl_login.':'.$wsdl_password);
	curl_setopt($ch, CURLOPT_URL, $wsdl);
	curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
	curl_setopt($ch, CURLOPT_USERPWD, $credit);
	curl_setopt($ch, CURLOPT_TIMEOUT, 15);
	curl_setopt($ch, CURLOPT_FILE, $fp);
			
	// testing only!!
	curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			
	if (($xml = curl_exec($ch)) === false) {
		//curl_close($ch);
		fclose($fp);
		unlink($cache_dir.$file);
				
		throw new Exception(curl_error($ch));
	}

	curl_close($ch);
	fclose($fp);
	$wsdl = $cache_url.$file;
	
	return $wsdl;
}

$myWSDLlocation = WSDLDownload('https://cert.hub.care360.com/orders/service?wsdl');
//$myWSDLlocation = WSDLDownload('https://cert.hub.care360.com/observation/result/service?wsdl');
//$myWSDLlocation = WSDLDownload('https://cert.hub.care360.com/resultsHub/observations/printable?wsdl');

$wsdlInterpreter = new WSDLInterpreter($myWSDLlocation);
$wsdlInterpreter->savePHP('/tmp/');
?>
<html><head></head><body>
<h1>WSDL Interpreter Completed...</h1>
</body></html>