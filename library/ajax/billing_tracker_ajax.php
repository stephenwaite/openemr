<?php

/**
 * Ajax endpoint for interface/billing/billing_tracker.php,
 * which is the interface that provides tracking information for a claim batch
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Ken Chapple <ken@mi-squared.com>
 * @author    Michael A. Smith <michael@opencoreemr.com>
 * @copyright Copyright (c) 2021 Ken Chapple <ken@mi-squared.com>
 * @copyright Copyright (c) 2026 OpenCoreEMR Inc <https://opencoreemr.com/>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

require_once __DIR__ . "/../../interface/globals.php";

use OpenEMR\BC\ServiceContainer;
use OpenEMR\Billing\BillingProcessor\X12RemoteTracker;
use OpenEMR\Billing\BillingProcessor\X12RemoteTrackerActions;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AccessDeniedResponseFormat;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Common\Session\SessionWrapperFactory;

$session = SessionWrapperFactory::getInstance()->getActiveSession();
$request = CurrentRequest::get();
// A POST acts on one file (send it again, or mark it as resolved); a GET lists the files.
$isAction = $request->isMethod('POST');
CsrfUtils::checkCsrfInput($isAction ? INPUT_POST : INPUT_GET, dieOnFail: true);

// Match the same ACL check as the parent UI (billing_tracker.php)
if (!AclMain::aclCheckCore('acct', 'eob', '', 'write') && !AclMain::aclCheckCore('acct', 'bill', '', 'write')) {
    AccessDeniedHelper::deny('Claim file tracker access denied', format: AccessDeniedResponseFormat::Json);
}

if ($isAction) {
    $user = $session->get('authUser');
    $user = is_string($user) ? $user : '';
    $id = $request->request->getInt('id');
    $actions = new X12RemoteTrackerActions(ServiceContainer::getClock());
    $done = match ($request->request->getString('action')) {
        'send_again' => $actions->sendAgain($id, $user),
        'resolve' => $actions->resolve($id, $user, $request->request->getString('note')),
        default => false,
    };
    header('Content-Type: application/json');
    echo json_encode(['success' => $done]);
    exit();
}

$remoteTracker = new X12RemoteTracker();
$claim_files = $remoteTracker->fetchAll();
$response = new stdClass();
$response->data = [];
foreach ($claim_files as $claim_file) {
    $element = new stdClass();
    $fileId = is_array($claim_file) ? ($claim_file['id'] ?? null) : null;
    $element->id = is_numeric($fileId) ? (int) $fileId : 0;
    $element->x12_partner_id = text($claim_file['x12_partner_id']);
    $element->x12_partner_name = text($claim_file['name']);
    $element->x12_filename = text($claim_file['x12_filename']);
    $claimStatus = is_string($claim_file['status'] ?? null) ? $claim_file['status'] : '';
    // Keep `status` as the raw enum so JS can compare against 'success'/'waiting'.
    $element->status = $claimStatus;
    // @phpstan-ignore argument.type (legacy on-the-fly translation of dynamic value; migration tracked in #11498)
    $element->status_label = xl($claimStatus);
    $element->can_send_again = X12RemoteTrackerActions::canSendAgain($claimStatus);
    $element->can_resolve = X12RemoteTrackerActions::canResolve($claimStatus);
    $element->created_at = $claim_file['created_at'];
    $element->updated_at = $claim_file['updated_at'];
    $element->claims = json_decode((string) $claim_file['claims']);
    $element->messages = json_decode($claim_file['messages'] ?? '');
    $response->data[] = $element;
}

echo json_encode($response);
exit();
