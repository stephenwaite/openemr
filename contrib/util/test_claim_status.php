<?php

/**
 * Command line check claim status for change healthcare clearinghouse
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2023 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
*/

// comment this out when using this script (and then uncomment it again when done using script)
//exit;

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php test_claim_status.php default pid encounter primary \n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Request;
use OpenEMR\Billing\BillingProcessor\BillingClaim;
use OpenEMR\Billing\BillingProcessor\BillingClaimBatch;
use OpenEMR\Billing\BillingProcessor\BillingClaimBatchControlNumber;
use OpenEMR\Common\Uuid\UuidRegistry;
use OpenEMR\Services\EncounterService;
use OpenEMR\Services\FacilityService;
use OpenEMR\Services\InsuranceCompanyService;
use OpenEMR\Services\InsuranceService;

$control_number = BillingClaimBatchControlNumber::getIsa13();

$pid = $argv[2];
$encounter_id = $argv[3];
$insurance_type = $argv[4];
$insurance_data = (new InsuranceService())->getOneByPid($pid, $insurance_type);
$insurance_company = (new InsuranceCompanyService())->getOneById($insurance_data['provider']);
//var_dump($insurance_company);
$insurance_company_address = (new InsuranceCompanyService())->getOne($insurance_company['uuid'])->getData()[0];
//var_dump($insurance_company_address);

//exit;
$x12_partner = new X12Partner($insurance_company['x12_default_partner_id']);
$x12_token_endpoint = $x12_partner->get_x12_token_endpoint();
$x12_client_id = $x12_partner->get_x12_client_id();
$x12_client_secret = $x12_partner->get_x12_client_secret();
$x12_claim_status_endpoint = $x12_partner->get_x12_claim_status_endpoint();
//echo $x12_token_endpoint . "\n";
//exit;

$guzzle = new Client();
$response = $guzzle->post($x12_token_endpoint, [
    'form_params' => [
        'grant_type' => 'client_credentials',
        'client_id' => $x12_client_id,
        'client_secret' => $x12_client_secret
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

$insurance_payer_id = $insurance_company['cms_id'];
$insurance_eligibility_id = $insurance_company['eligibility_id'];
$ins_name = $insurance_company['name'];
$ins_street = $insurance_company_address['line1'];
$ins_city = $insurance_company_address['city'];
$ins_state = $insurance_company_address['state'];
$ins_zip = $insurance_company_address['zip'];
if (strlen($ins_zip) == 5) {
    $ins_zip = $ins_zip . "9999";
}
$g_pripol = $insurance_data['policy_number'];
$firstName = $insurance_data['subscriber_fname'];
$lastName = $insurance_data['subscriber_lname'];
$g_garno = $pid;
$encounter = (new EncounterService())->getEncounterById($encounter_id)->getData()[0];
//var_dump($encounter);
//exit;

$cc_date_t = date("Ymd", strtotime($encounter['date']));
//echo "$cc_date_t cc date t \n";
//exit;
//$cc_date_a = substr($row, 144, 8);
$g_group_number = $insurance_data['group_number'] ?: '00000';
$g_dob = date('Ymd', strtotime($insurance_data['subscriber_DOB']));
$g_sex = substr($insurance_data['subscriber_sex'], 0, 1);

$trading_partner_service_id = $insurance_eligibility_id;
$trading_partner_name = $ins_name;       

/* $control_array = array(
    'controlNumber' => '000000001',
    'tradingPartnerServiceId' => 'serviceId',
); */

$control_array = array(
    'controlNumber' => $control_number,
    'tradingPartnerServiceId' => $trading_partner_service_id,
);

$organization = (new FacilityService())->getById($encounter['billing_facility']);
//var_dump($organization);
//exit;

$datum = new stdClass();
$datum->organization_name = $organization['name'];
$datum->taxId = $organization['federal_ein'];
$datum->npi = $organization['facility_npi'];
$datum->providerType = 'BillingProvider';
$datum_array = array($datum);
$providers = array(
    'providers' => $datum_array
);
    /* array(
        "organizationName" => "happy doctors group",
        "npi" => "1760854442",
        "providerType" => "ServiceProvider"
    ) */


/* $providers = array(
    'providers' => array(
        'organizationName' => 'RUTLAND RADIOLOGISTS',
        'taxId'            => '030238095',
        'providerType'     => 'BillingProvider'
    )
); */

/* $subscriber = array(
    'subscriber' => array(
        'memberId' => '0000000000',
        'firstName' => 'johnone',
        'lastName' => 'doeone',
        'gender' => 'M',
        'dateOfBirth' => '18800102',
        'groupNumber' => '0000000000'
    )
); */

$subscriber = array(
    'subscriber' => array(
        'memberId' => $g_pripol,
        'firstName' => $firstName,
        'lastName' => $lastName,
        'gender' => $g_sex,
        'dateOfBirth' => $g_dob,
        'groupNumber' => $g_group_number
    )
);

/* $encounter = array(
    'encounter' => array(
        'beginningDateOfService' => '20100101',
        'endDateOfService'       => '20100102',
        'trackingNumber'         => 'ABCD'
    )
); */

$encounter = array(
    'encounter' => array(
        'beginningDateOfService' => $cc_date_t,
        'endDateOfService'       => $cc_date_t,
        'trackingNumber'         => BillingClaimBatchControlNumber::getIsa13()
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

/* $body = '{
  "controlNumber": "000000001",
  "tradingPartnerServiceId": "serviceId",
  "providers": [
    {
      "organizationName": "TestProvider",
      "taxId": "0123456789",
      "providerType": "BillingProvider"
    },
    {
      "organizationName": "happy doctors group",
      "npi": "1760854442",
      "providerType": "ServiceProvider"
    }
  ],
  "subscriber": {
    "memberId": "0000000000",
    "firstName": "johnone",
    "lastName": "doeone",
    "gender": "M",
    "dateOfBirth": "18800102",
    "groupNumber": "0000000000"
  },
  "encounter": {
    "beginningDateOfService": "20100101",
    "endDateOfService": "20100102",
    "trackingNumber": "ABCD"
  }
}'; */
$request = new Request(
    'POST', 
    $x12_claim_status_endpoint,
    $headers,
    json_encode($body)
);

try {
    $res = $client->sendAsync($request)->wait();
} catch (Exception $e) {
    throw new Exception($e->getResponse()->getBody()->getContents());
} 
echo json_encode(json_decode($res->getBody()), JSON_PRETTY_PRINT);

