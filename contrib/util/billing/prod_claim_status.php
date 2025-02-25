<?php

/**
 * Check claim status from command line
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

require_once(__DIR__ . '/../../../vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Request;
use OpenEMR\Billing\BillingProcessor\BillingClaimBatchControlNumber;
use OpenEMR\Billing\SLEOB;
use OpenEMR\Common\Crypto\CryptoGen;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\FacilityService;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default 83\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../../interface/globals.php";

$enc = intval($argv[2]);
//var_dump($enc);
$encounter = (new EncounterService())->getEncounterById($enc)->getData()[0];
//var_dump($encounter);

$facility = (new FacilityService())->getOne($encounter['billing_facility'])->getData();
//var_dump($facility);
if (empty($facility)) {
    $facility = (new FacilityService())->getPrimaryBillingLocation();
    //var_dump($facility);
}

$pid = $encounter['pid'];
$dos = date_format(date_create($encounter['date']), 'Ymd');
$type = $encounter['last_level_closed'] + 1; // add 1 for claim status
$payerId = intval((new SLEOB())->arGetPayerID($pid, $dos, $type));
//var_dump($payerId);
$insCo = (new InsuranceCompanyService())->getOneById($payerId);
//var_dump($insCo);
$trading_partner_name = $insCo['name'];

$insTypeArr = ['1' => 'primary', '2' => 'secondary', '3' => 'tertiary'];
$insData = (new InsuranceService())->getOneByPid($pid, $insTypeArr[$type]);
//var_dump($insData);
//exit;

$trading_partner_name = $insCo['name'];
$x12PartnerId = (!empty($insCo['x12_default_eligibility_id'])) ? $insCo['x12_default_eligibility_id'] : $insCo['x12_default_partner_id'];
$trading_partner_service_id = (!empty($insCo['eligibility_id'])) ? $insCo['eligibility_id'] : $insCo['cms_id'];
//$trading_partner_service_id = 'BCVTC';
$x12Partner = new X12Partner($x12PartnerId);
//var_dump($x12Partner);
$claimStatusEndpoint = $x12Partner->get_x12_claim_status_endpoint();
$clientId = (new CryptoGen())->decryptStandard($x12Partner->get_x12_client_id());
$clientSecret = (new CryptoGen())->decryptStandard($x12Partner->get_x12_client_secret());
//echo $clientId . "\n";
//echo $clientSecret . "\n";

$policy = $insData['policy_number'];
$firstName = $insData['subscriber_fname'];
$lastName = $insData['subscriber_lname'];
if (stripos($insData['group_number'], 'NA') !== false) {
    $groupNumber = '';
} else {
    $groupNumber = $insData['group_number'];
}
$subscriberDob = date_format(date_create($insData['subscriber_DOB']), 'Ymd');
$sex = substr($insData['subscriber_sex'], 0, 1);

$control_number = BillingClaimBatchControlNumber::getIsa13();


$control_array = array(
    'controlNumber' => $control_number,
    'tradingPartnerServiceId' => $trading_partner_service_id,
);

$datum = new stdClass();
$datum->organizationName = 'ALLAN EISEMANN MD';
$datum->taxId = '495504839';
$datum->npi = '1801993811';
$datum->providerType = 'BillingProvider';
$datum_array = array($datum);
$providers = array(
    'providers' => $datum_array
);

$subscriber = array(
    'subscriber' => array(
        'memberId' => $policy,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'gender' => $sex,
        'dateOfBirth' => $subscriberDob,
    )
);

if ($g_group_number ?? null) {
    $subscriber['subscriber']['groupNumber'] = $g_group_number;
}

$encounter = array(
    'encounter' => array(
        'beginningDateOfService' => $dos,
        'endDateOfService'       => $dos,
        'trackingNumber'         => BillingClaimBatchControlNumber::getIsa13(),
        //'submittedAmount'        => $cc_amount
    )
);


$body = array_merge(
    $control_array,
    $providers,
    $subscriber,
    $encounter
);

//echo json_encode($body, JSON_PRETTY_PRINT);
//exit;

$base_uri = $claimStatusEndpoint;
$guzzle = new Client();
$response = $guzzle->post($base_uri . 'apip/auth/v2/token', [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $clientId,
        'client_secret' => $clientSecret,
    ],
]);
$bearer = json_decode((string) $response->getBody(), true)['access_token'];
//echo $bearer;
//exit;

$client = new Client();
$headers = [
    'Content-Type' => 'application/json',
    'Authorization' => 'Bearer ' . $bearer,
    'http_errors' => false
];


$request = new Request(
    'POST',
    $base_uri . 'medicalnetwork/claimstatus/v2',
    $headers,
    json_encode($body)
);

try {
    $res = $client->sendAsync($request)->wait();
} catch (Exception $e) {
    throw new Exception($e->getResponse()->getBody()->getContents());
    exit;
}


$responseJson = json_encode(json_decode($res->getBody()), JSON_PRETTY_PRINT);
echo $responseJson;
