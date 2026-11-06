<?php

/**
 * Quality measures (Fees > Quality Measures): the coder answers each held charge's
 * measure questions from the radiologist's report, which lets it post with the claims
 * measures' G-codes; and exports the ACR registry CSV.
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
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\AcrRow;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\Measure;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Measure\MeasureRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Parse\ChargeRowParser;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Payer\PayerMap;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargeLineRepository;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\ChargePoster;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PostSettings;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\PriceBook;
use Cmsvt\OpenEMR\Modules\GiffordCharges\Post\SiteWriter;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Database\QueryUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Common\Session\SessionWrapperFactory;
use OpenEMR\Core\OEGlobalsBag;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('acct', 'bill')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for acct/bill: Quality Measures', xl('Quality Measures'));
}

$globals = OEGlobalsBag::getInstance();
$clock = ServiceContainer::getClock();
$measures = new MeasureRepository();
$settings = PostSettings::load($globals->getInt(GiffordGlobals::FACILITY_ID), $globals->getInt(GiffordGlobals::BILLING_FACILITY_ID));
$request = CurrentRequest::get();
$authUser = SessionWrapperFactory::getInstance()->getActiveSession()->get('authUserID');
$userId = is_numeric($authUser) ? (int) $authUser : 0;
$messages = [];

if ($request->isMethod('POST')) {
    CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);
    $form = $request->request;
    $now = $clock->now();
    switch ($form->getString('form_action')) {
        case 'answer':
            $answers = [];
            // Numeric measure names come back as int keys.
            foreach ($form->all('answer') as $measure => $value) {
                if (is_string($value) && $value !== '' && ctype_digit($value)) {
                    $answers[$measure] = (int) $value;
                }
            }
            $messages[] = $measures->answer($form->getInt('measure_id'), $answers, $userId, $now)
                ? ['success', xl('Saved. The charge posts on the next run, with its measure codes.')]
                : ['danger', xl('Answer every question for the charge, or it is no longer waiting.')];
            break;
        case 'release':
            $messages[] = $measures->release($form->getInt('measure_id'), $userId, $now)
                ? ['success', xl('Released without an answer. The charge posts on the next run, with no measure codes.')]
                : ['warning', xl('That charge is no longer waiting.')];
            break;
        case 'post':
            if ($settings === null) {
                $messages[] = ['danger', xl('Set the Facility ID and Billing Facility ID in Admin > Config > Gifford Charge Import first.')];
                break;
            }
            $poster = new ChargePoster(new ChargeLineRepository(), new ChargeRowParser(), new PayerMap(), new PriceBook(), new SiteWriter(), $clock);
            $result = $poster->run($settings);
            $messages[] = ['success', sprintf(xl('Posted %d lines; %d held; %d duplicates.'), $result->posted, $result->held, $result->duplicates)];
            break;
        case 'acr':
            $from = date_create_immutable($form->getString('from'));
            $to = date_create_immutable($form->getString('to'));
            if ($from === false || $to === false || $from > $to) {
                $messages[] = ['danger', xl('Give the export a date range, first date first.')];
                break;
            }
            $tin = QueryUtils::fetchSingleValue(
                'SELECT `federal_ein` FROM `facility` WHERE `id` = ?',
                'federal_ein',
                [$globals->getInt(GiffordGlobals::BILLING_FACILITY_ID)]
            );
            $practiceId = $globals->getString(GiffordGlobals::ACR_PRACTICE_ID);
            $lines = array_map(
                static fn (AcrRow $row): string => $row->line($practiceId, is_string($tin) ? $tin : ''),
                $measures->acrRows($from, $to)
            );
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="acr_' . $from->format('Ymd') . '_' . $to->format('Ymd') . '.csv"');
            echo implode("\n", $lines) . ($lines === [] ? '' : "\n");
            exit;
    }
}

$twig = ServiceContainer::getTwig();
$loader = $twig->getLoader();
if (!$loader instanceof FilesystemLoader) {
    throw new RuntimeException('Unexpected Twig loader');
}
$loader->addPath(__DIR__ . '/../templates', 'gifford');
$options = [];
foreach (Measure::cases() as $measure) {
    $options[$measure->value] = [
        'label' => $measure->label(),
        'question' => $measure->question(),
        'onClaim' => $measure->onClaim(),
        'options' => array_map(static fn ($o): array => ['value' => $o->value, 'label' => $o->label], $measure->options()),
    ];
}
echo $twig->render('@gifford/measures.html.twig', [
    'messages' => $messages,
    'pending' => $measures->pending(),
    'pendingCount' => $measures->pendingCount(),
    'measureOptions' => $options,
    'acrConfigured' => $globals->getString(GiffordGlobals::ACR_PRACTICE_ID) !== '',
    'postingOn' => $settings !== null,
    'today' => $clock->now()->format('Y-m-d'),
    'monthStart' => $clock->now()->format('Y-m-01'),
]);
