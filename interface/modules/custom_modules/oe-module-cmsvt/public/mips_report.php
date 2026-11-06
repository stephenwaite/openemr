<?php

/**
 * MIPS quality measures report: pick a measure and a performance year, see the
 * eligible patients, their qualifying visits and the measure's quality data codes.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Margaret Haywood <openemr@cmsvt.com>
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../../globals.php';

use Cmsvt\OpenEMR\Modules\Customizations\Globals\CmsvtGlobals;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsCriterion;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsMeasures;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsPeriod;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\MipsRepository;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Core\OEGlobalsBag;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('patients', 'med')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for patients/med: MIPS Quality Measures', xl('MIPS Quality Measures'));
}
$globals = OEGlobalsBag::getInstance();
if (!$globals->getBoolean(CmsvtGlobals::MIPS_REPORTS)) {
    AccessDeniedHelper::denyWithTemplate('MIPS quality measures report is not enabled for this site', xl('MIPS Quality Measures'));
}

$request = CurrentRequest::get();
$currentYear = (int) date('Y');
$year = $request->query->getInt('form_year', $currentYear);
if ($year < 2020 || $year > $currentYear) {
    $year = $currentYear;
}
$measures = MipsMeasures::enabled($globals->getString(CmsvtGlobals::MIPS_MEASURES));
$measure = MipsMeasures::find($request->query->getString('form_measure'), $measures);
$period = new MipsPeriod($year);
$report = $measure->build($period, $measure->fetch($period, new MipsRepository()));

$export = $request->query->getString('export');
if ($export === 'csv' || $export === 'csv_tracking') {
    $name = $export === 'csv' ? 'Encounter_Detail' : 'Patient_Tracking';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="MIPS_' . $measure->id() . '_' . $name . '_' . $year . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream');
    }
    fwrite($output, "\xEF\xBB\xBF");
    $statusHeaders = array_map(static fn(MipsCriterion $c): string => $c->label, $report->criteria);
    if ($export === 'csv') {
        fputcsv($output, [
            'Patient ID', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Date of Birth',
            'Age at Encounter', 'Encounter Date', 'Encounter ID', 'Visit Codes',
            'Measure Codes on This Fee Sheet', 'In Denominator', ...$statusHeaders, $measure->diagnosisLabel(),
        ], escape: '\\');
        foreach ($report->patients as $patient) {
            $statuses = $report->statusLabels($patient);
            foreach ($patient->encounters as $encounter) {
                fputcsv($output, [
                    $patient->pid, $patient->lname, $patient->fname, $patient->mname, $patient->sex, $patient->dob,
                    $encounter->age, $encounter->date, $encounter->encounter, $encounter->visitCodes,
                    implode('; ', $encounter->measureCodes), $patient->inDenominator ? 'Yes' : 'No',
                    ...$statuses, $patient->dxHistory,
                ], escape: '\\');
            }
        }
    } else {
        fputcsv($output, [
            'Patient ID', 'Last Name', 'First Name', 'Date of Birth', 'Qualifying Visits ' . $year, 'In Denominator',
            ...$report->columns, ...$statusHeaders, 'Flags', $measure->diagnosisLabel(),
        ], escape: '\\');
        foreach ($report->patients as $patient) {
            $columns = array_map(static fn(string $label): string => implode('; ', $patient->columns[$label] ?? []), $report->columns);
            fputcsv($output, [
                $patient->pid, $patient->lname, $patient->fname, $patient->dob, count($patient->encounters),
                $patient->inDenominator ? 'Yes' : 'No', ...$columns, ...$report->statusLabels($patient),
                implode('; ', $patient->flags), $patient->dxHistory,
            ], escape: '\\');
        }
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
echo $twig->render('@cmsvt/mips_report.html.twig', [
    'report' => $report,
    'measure' => $measure,
    'measures' => $measures,
    'years' => range($currentYear, 2020),
]);
