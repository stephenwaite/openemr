<?php

/**
 * The RRI coding page: the unbilled charges of a recent ordfile, where the
 * biller sets each charge's diagnoses (and its provider when it has none).
 * The page saves itself every few seconds (setting "Coding Page Auto-Save
 * Seconds") and with its Save button. Needs Fees > Billing access.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Nielson <snielson@discoverandchange.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2024-2026 Care Management Solutions, Inc. <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use Cmsvt\OpenEMR\Modules\RriBilling\Coding\CodingRepository;
use Cmsvt\OpenEMR\Modules\RriBilling\Coding\CodingUpdate;
use Cmsvt\OpenEMR\Modules\RriBilling\Globals\RriSettings;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Services\CodeTypesService;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('acct', 'bill')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for acct/bill: RRI Billing', xl('CMSVT Billing'));
}

$request = CurrentRequest::get();
$repository = new CodingRepository(new CodeTypesService());

if ($request->isMethod('POST')) {
    CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);
    // A failed save rolls back and reaches OpenEMR's error handler; the page shows "Records failed to save".
    $saved = $repository->save(CodingUpdate::fromForm($request->request->all('form_records')));
    header('Content-Type: application/json');
    echo json_encode(['saved' => $saved]);
    exit;
}

$ordfiles = $repository->recentOrdfiles();
$selected = $request->query->getInt('ordfile');
if (!in_array($selected, array_column($ordfiles, 'id'), true)) {
    $selected = $ordfiles[0]['id'] ?? 0;
}
$settings = new RriSettings(OEGlobalsBag::getInstance(), ServiceContainer::getCrypto());

$twig = ServiceContainer::getTwig();
$loader = $twig->getLoader();
if (!$loader instanceof FilesystemLoader) {
    throw new RuntimeException('Unexpected Twig loader');
}
$loader->addPath(__DIR__ . '/../templates', 'rri');
echo $twig->render('@rri/coding.html.twig', [
    'ordfiles' => $ordfiles,
    'selectedOrdfile' => $selected,
    'rows' => $selected > 0 ? $repository->rows($selected) : [],
    'providers' => $repository->providers(),
    'autoSaveInterval' => $settings->autosaveMilliseconds(),
    'debug' => $settings->debug(),
]);
