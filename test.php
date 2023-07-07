<?php

require_once('vendor/autoload.php');

use GuzzleHttp\Client;
use GuzzleHttp\Psr7\Utils;
use GuzzleHttp\Psr7\Request;

$base_uri = 'https://localhost:9300/oauth2/default/token';
$guzzle = new Client(
    ['verify' => false],
    ['debug' => true]
);
$response = $guzzle->post($base_uri, [
    'form_params' => [
        'grant_type' => 'password',
        'client_id' => getenv('OEMR_TEST_CLIENT_ID'),
        'scope' => "openid offline_access api:oemr api:fhir api:port user/allergy.read user/allergy.write user/appointment.read user/appointment.write user/dental_issue.read user/dental_issue.write user/document.read user/document.write user/drug.read user/encounter.read user/encounter.write user/facility.read user/facility.write user/immunization.read user/insurance.read user/insurance.write user/insurance_company.read user/insurance_company.write user/insurance_type.read user/list.read user/medical_problem.read user/medical_problem.write user/medication.read user/medication.write user/message.write user/patient.read user/patient.write user/practitioner.read user/practitioner.write user/prescription.read user/procedure.read user/soap_note.read user/soap_note.write user/surgery.read user/surgery.write user/transaction.read user/transaction.write user/vital.read user/vital.write user/AllergyIntolerance.read user/CareTeam.read user/Condition.read user/Coverage.read user/Encounter.read user/Immunization.read user/Location.read user/Medication.read user/MedicationRequest.read user/Observation.read user/Organization.read user/Organization.write user/Patient.read user/Patient.write user/Practitioner.read user/Practitioner.write user/PractitionerRole.read user/Procedure.read patient/encounter.read patient/patient.read patient/AllergyIntolerance.read patient/CareTeam.read patient/Condition.read patient/Coverage.read patient/Encounter.read patient/Immunization.read patient/MedicationRequest.read patient/Observation.read patient/Patient.read patient/Procedure.read",
        'user_role' => 'users',
        'username' => 'admin',
        'password' => 'pass'
    ],
]);
$bearer = json_decode((string) $response->getBody(), true)['access_token'];
//echo $bearer;
//exit;

$client = new Client(['verify' => false],
['debug' => true]
);
$headers = [
  'Authorization' => 'Bearer ' . $bearer,
  'Accept' => 'application/json',
];
$request = new Request('GET', 'https://localhost:9300/apis/default/fhir/Patient?identifier=112979', $headers);
$res = $client->sendAsync($request)->wait();
echo $res->getBody();
