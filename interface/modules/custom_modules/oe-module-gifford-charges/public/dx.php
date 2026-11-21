<?php

/**
 * Gifford diagnoses (Fees > Gifford Diagnoses): the coder confirms the diagnoses each
 * accession posts with, from its report and the suggestion read from it.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use Cmsvt\OpenEMR\Modules\GiffordCharges\Dx\DxRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Globals\GiffordGlobals;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReviewRepository;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('acct', 'bill')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for acct/bill: Gifford Diagnoses', xl('Gifford Diagnoses'));
}

/** Accessions shown at a time: each carries its report. */
const GIFFORD_DX_PAGE = 25;

$globals = OEGlobalsBag::getInstance();
$clock = ServiceContainer::getClock();
$dx = new DxRepository();
$confirmOn = $globals->getBoolean(GiffordGlobals::CONFIRM_DX);
$settings = PostSettings::load($globals->getInt(GiffordGlobals::FACILITY_ID), $globals->getInt(GiffordGlobals::BILLING_FACILITY_ID));
$request = CurrentRequest::get();
$authUser = SessionWrapperFactory::getInstance()->getActiveSession()->get('authUserID');
$userId = is_numeric($authUser) ? (int) $authUser : 0;
$messages = [];
$entered = [];

if ($request->isMethod('POST')) {
    CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);
    $form = $request->request;
    switch ($form->getString('form_action')) {
        case 'confirm':
            $accession = $form->getString('accession');
            // The "Use Gifford codes" button sends its codes in place of the field's.
            $codes = $form->getString('use_codes') !== '' ? $form->getString('use_codes') : $form->getString('codes');
            $errors = $dx->confirm($accession, $codes, $userId, $clock->now());
            if ($errors === []) {
                $messages[] = ['success', xl('Saved. The charge posts with these diagnoses on the next run.')];
            } else {
                $messages[] = ['danger', xl('Not saved') . ': ' . implode('; ', $errors)];
                $entered[$accession] = $codes;
            }
            break;
        case 'change_code':
            $errors = (new ReviewRepository(new ChargeRowParser(), new PayerMap()))
                ->changeCode($form->getString('accession'), $form->getString('from_code'), $form->getString('new_code'), $clock->now());
            $messages[] = $errors === []
                ? ['success', xl('Charge code changed. Check the diagnoses against the new code, then Save.')]
                : ['danger', xl('Not changed') . ': ' . implode('; ', $errors)];
            break;
        case 'post':
            if ($settings === null) {
                $messages[] = ['danger', xl('Set the Facility ID and Billing Facility ID in Admin > Config > Gifford Charge Import first.')];
                break;
            }
            $result = ChargePoster::forSite($clock, $confirmOn, $globals->getString(GiffordGlobals::HOLD_CODES))->run($settings);
            $messages[] = ['success', sprintf(xl('Posted %d lines; %d held; %d duplicates.'), $result->posted, $result->held, $result->duplicates)];
            break;
    }
}

// A site that stages without posting never runs the poster: settle the exam-coded here too.
if ($confirmOn) {
    $dx->settleByExam($clock->now());
}

$twig = ServiceContainer::getTwig();
$loader = $twig->getLoader();
if (!$loader instanceof FilesystemLoader) {
    throw new RuntimeException('Unexpected Twig loader');
}
$loader->addPath(__DIR__ . '/../templates', 'gifford');
echo $twig->render('@gifford/dx.html.twig', [
    'messages' => $messages,
    'confirmOn' => $confirmOn,
    'postingOn' => $settings !== null,
    'pending' => $dx->pending(GIFFORD_DX_PAGE),
    'pendingCount' => $dx->pendingCount(),
    'pageSize' => GIFFORD_DX_PAGE,
    'entered' => $entered,
    'tally' => $dx->tally(),
    'maxCodes' => DxRepository::MAX_CODES,
]);
