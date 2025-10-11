<?php

// ============================================================================
// USAGE EXAMPLE
// ============================================================================
// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_OEMR_CLI_CLIENT')) {
    die('Set OPENEMR_ENABLE_OEMR_CLI_CLIENT=1 environment variable to enable this script');
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute command\n";
    echo "example use: php default feesched.txt 10 33 2023-10-01\n";
    die;
}

$_GET['site'] = $argv[1];
$ignoreAuth = true;
require_once __DIR__ . "/../../interface/globals.php";

$base_url = getenv('BASE_OEMR_URL');
'grant_type' => 'password',
        'client_id' => getenv('SUNPED_CLIENT_ID'),
        'redirect_uri' => getenv("SUNPED_REDIRECT_URI"),
        'scope' => "openid api:oemr user/appointment.read user/document.read user/document.write user/encounter.read user/encounter.write user/patient.read user/patient.write",

Registering a oauth2 client to site default
client id: "-VKB7WXkLSNrlNXJYDJJGsEO-ky6wswqisInVzeR1hg"
client secret: "4KkZtMNgRRiAY4dLNnySVPt-yUOuwC6VbCpwyXN4RixLf6-hNanNl9F47M6EVoaktnTa6pg-yBSHTNJzXbNi4A"

// Configuration
$config = [
    'base_url' => 'https://your-openemr-instance.com',
    'client_id' => 'your-client-id',
    'client_secret' => 'your-client-secret', // Optional for public clients
    'scope' => 'openid api:oemr api:fhir user/Patient.read'
];

try {
    // Create client
    $client = new OpenEMRCLIClient($config);

    // Get access token (will authorize if needed)
    $accessToken = $client->getAccessToken();

    echo "Access Token: " . substr($accessToken->getToken(), 0, 20) . "...\n";
    echo "Expires: " . date('Y-m-d H:i:s', $accessToken->getExpires()) . "\n\n";

    // Make API requests
    echo "=== Fetching Patients ===\n";
    $patients = $client->apiRequest('/apis/default/api/patient');
    echo "Found " . count($patients['data'] ?? []) . " patients\n\n";

    if (!empty($patients['data'])) {
        $patient = $patients['data'][0];
        echo "First Patient:\n";
        echo "  ID: " . $patient['pid'] . "\n";
        echo "  Name: " . ($patient['fname'] ?? '') . " " . ($patient['lname'] ?? '') . "\n";
        echo "  DOB: " . ($patient['DOB'] ?? 'N/A') . "\n\n";
    }

    // Get facilities
    echo "=== Fetching Facilities ===\n";
    $facilities = $client->apiRequest('/apis/default/api/facility');
    echo "Found " . count($facilities ?? []) . " facilities\n\n";

    // FHIR API example
    echo "=== FHIR Patient Search ===\n";
    $fhirPatients = $client->apiRequest('/apis/default/fhir/Patient');
    echo "FHIR Bundle: " . ($fhirPatients['resourceType'] ?? 'Unknown') . "\n";
    echo "Total: " . ($fhirPatients['total'] ?? 0) . " patients\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    exit(1);
}
