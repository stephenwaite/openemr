<?php

/**
 * Gifford charge review (Fees > Gifford Charges): map new payer mnemonics, and skip or
 * release held lines.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use Cmsvt\OpenEMR\Modules\GiffordCharges\Globals\GiffordGlobals;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerChange;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerChangeKind;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerImporter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMapping;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargeLineRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PriceBook;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\SiteWriter;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Review\ReviewRepository;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Core\OEGlobalsBag;
use OpenEMR\Services\InsuranceCompanyService;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('acct', 'bill')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for acct/bill: Gifford Charges', xl('Gifford Charges'));
}

$globals = OEGlobalsBag::getInstance();
$clock = ServiceContainer::getClock();
$parser = new ChargeRowParser();
$payers = new PayerMap();
$review = new ReviewRepository($parser, $payers);
$settings = PostSettings::load($globals->getInt(GiffordGlobals::FACILITY_ID), $globals->getInt(GiffordGlobals::BILLING_FACILITY_ID));

$request = CurrentRequest::get();
$messages = [];
if ($request->isMethod('POST')) {
    CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);
    $form = $request->request;
    $mnemonic = strtoupper(trim($form->getString('mnemonic')));
    $lineId = $form->getInt('line_id');
    $now = $clock->now();

    switch ($form->getString('form_action')) {
        case 'map':
            $companyId = $form->getInt('insurance_company_id');
            if ($mnemonic === '' || !$review->companyExists($companyId)) {
                $messages[] = ['danger', xl('Choose an insurance company for the payer.')];
                break;
            }
            $payers->set($mnemonic, $companyId, $now);
            $messages[] = ['success', sprintf(xl('%s mapped. Its held lines post on the next run.'), $mnemonic)];
            break;
        case 'paper':
            $name = trim($form->getString('gifford_name'));
            $type = $form->getInt('ins_type_code');
            if ($mnemonic === '' || $name === '' || $type <= 0) {
                $messages[] = ['danger', xl('A paper payer needs a name and an insurance type.')];
                break;
            }
            (new PayerImporter(new InsuranceCompanyService(), $clock, $payers))->apply([
                new PayerChange(PayerChangeKind::Create, new PayerMapping($mnemonic, $name, $type, null), null),
            ]);
            $messages[] = ['success', sprintf(xl('%s created as a paper payer and mapped. Add its address and payer ID when its first claim comes up.'), $mnemonic)];
            break;
        case 'skip':
            $messages[] = $review->skip($lineId, $now)
                ? ['success', xl('Line skipped; it will not be posted.')]
                : ['warning', xl('That line is no longer held.')];
            break;
        case 'release':
            $messages[] = $review->release($lineId, $now)
                ? ['success', xl('Line released; it posts on the next run.')]
                : ['warning', xl('That line is no longer held.')];
            break;
        case 'post':
            if ($settings === null) {
                $messages[] = ['danger', xl('Set the Facility ID and Billing Facility ID in Admin > Config > Gifford Charge Import first.')];
                break;
            }
            $poster = new ChargePoster(new ChargeLineRepository(), $parser, $payers, new PriceBook(), new SiteWriter(), $clock);
            $result = $poster->run($settings);
            $messages[] = ['success', sprintf(xl('Posted %d lines; %d held; %d duplicates.'), $result->posted, $result->held, $result->duplicates)];
            break;
    }
}

$insuranceTypes = [];
foreach (QueryUtils::fetchRecords('SELECT `id`, `type` FROM `insurance_type_codes` ORDER BY `id`') as $row) {
    if (is_numeric($row['id'] ?? null) && is_string($row['type'] ?? null)) {
        $insuranceTypes[] = ['id' => (int) $row['id'], 'label' => $row['type']];
    }
}

$twig = ServiceContainer::getTwig();
$loader = $twig->getLoader();
if (!$loader instanceof FilesystemLoader) {
    throw new RuntimeException('Unexpected Twig loader');
}
$loader->addPath(__DIR__ . '/../templates', 'gifford');
echo $twig->render('@gifford/review.html.twig', [
    'messages' => $messages,
    'postingOn' => $settings !== null,
    'unmappedPayers' => $review->unmappedPayers(),
    'companies' => $review->insuranceCompanies(),
    'insuranceTypes' => $insuranceTypes,
    'heldLines' => $review->heldLines(),
]);
