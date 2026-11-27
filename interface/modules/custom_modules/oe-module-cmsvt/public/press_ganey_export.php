<?php

/**
 * Press Ganey survey export: encounters in Press Ganey's upload CSV layout.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use Cmsvt\OpenEMR\Modules\Customizations\PressGaney\PressGaneyExportFilter;
use Cmsvt\OpenEMR\Modules\Customizations\PressGaney\PressGaneyRecordFormatter;
use Cmsvt\OpenEMR\Modules\Customizations\PressGaney\PressGaneyRepository;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Csrf\CsrfUtils;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Core\OEGlobalsBag;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('encounters', 'coding_a')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for encounters/coding_a: Press Ganey Export', xl('Press Ganey Export'));
}

$request = CurrentRequest::get();
$isPost = $request->isMethod('POST');
if ($isPost) {
    CsrfUtils::checkCsrfInput(INPUT_POST, dieOnFail: true);
}

$globals = OEGlobalsBag::getInstance();
$filter = PressGaneyExportFilter::fromPost($request->request);
$repository = new PressGaneyRepository();
$clientId = $globals->getString(CmsvtGlobals::PG_CLIENT_ID);
$surveyDesignator = $globals->getString(CmsvtGlobals::PG_SURVEY_DESIGNATOR);

if ($isPost && $request->request->getString('form_export') !== '') {
    $formatter = new PressGaneyRecordFormatter($surveyDesignator, $clientId);
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="press_ganey_export_' . date('Ymd_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream');
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, PressGaneyRecordFormatter::HEADERS, escape: '\\');
    foreach ($repository->fetchEncounters($filter) as $record) {
        fputcsv($output, $formatter->format($record), escape: '\\');
    }
    fclose($output);
    exit;
}

$twig = ServiceContainer::getTwig();
$loader = $twig->getLoader();
if (!$loader instanceof FilesystemLoader) {
    throw new RuntimeException('Unexpected Twig loader');
}
$loader->addPath(__DIR__ . '/../templates', 'cmsvt');
echo $twig->render('@cmsvt/press_ganey_export.html.twig', [
    'clientId' => $clientId,
    'surveyDesignator' => $surveyDesignator,
    'filter' => $filter,
    'providers' => $repository->providers(),
    'facilities' => $repository->facilities(),
    'categories' => $repository->encounterCategories(),
    'previewCount' => $isPost && $request->request->getString('form_preview') !== '' ? $repository->countEncounters($filter) : null,
]);
