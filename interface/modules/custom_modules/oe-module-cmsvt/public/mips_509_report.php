<?php

/**
 * MIPS quality measure 509 (Melanoma: Tracking and Evaluation of Recurrence):
 * melanoma patients, their qualifying visits and the 509 codes on their fee sheets.
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
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Mips509Measure;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Mips509Report;
use Cmsvt\OpenEMR\Modules\Customizations\Mips\Mips509Repository;
use OpenEMR\BC\ServiceContainer;
use OpenEMR\Common\Acl\AccessDeniedHelper;
use OpenEMR\Common\Acl\AclMain;
use OpenEMR\Common\Http\CurrentRequest;
use OpenEMR\Core\OEGlobalsBag;
use Twig\Loader\FilesystemLoader;

if (!AclMain::aclCheckCore('patients', 'med')) {
    AccessDeniedHelper::denyWithTemplate('ACL check failed for patients/med: MIPS 509 Report', xl('MIPS 509 Report'));
}
if (!OEGlobalsBag::getInstance()->getBoolean(CmsvtGlobals::MIPS_509_REPORT)) {
    AccessDeniedHelper::denyWithTemplate('MIPS 509 report is not enabled for this site', xl('MIPS 509 Report'));
}

$request = CurrentRequest::get();
$currentYear = (int) date('Y');
$year = $request->query->getInt('form_year', $currentYear);
if ($year < 2020 || $year > $currentYear) {
    $year = $currentYear;
}

$measure = new Mips509Measure($year);
$repository = new Mips509Repository();
$patientRows = $repository->patients($measure);
$pids = [];
foreach ($patientRows as $row) {
    $pid = $row['pid'] ?? null;
    if (is_numeric($pid)) {
        $pids[] = (int) $pid;
    }
}
$report = Mips509Report::build(
    $measure,
    $patientRows,
    $repository->qualifyingEncounters($measure, $pids),
    $repository->trackedCodes($measure, $pids),
);

$export = $request->query->getString('export');
if ($export === 'csv' || $export === 'csv_tracking') {
    $name = $export === 'csv' ? 'Encounter_Detail' : 'Patient_Tracking';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="MIPS_509_' . $name . '_' . $year . '_' . date('Y-m-d') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    $output = fopen('php://output', 'w');
    if ($output === false) {
        throw new RuntimeException('Unable to open the CSV output stream');
    }
    fwrite($output, "\xEF\xBB\xBF");
    if ($export === 'csv') {
        fputcsv($output, [
            'Patient ID', 'Last Name', 'First Name', 'Middle Name', 'Sex', 'Date of Birth',
            'Age at Encounter', 'Encounter Date', 'Encounter ID', 'Visit Codes',
            'Measure Codes on This Fee Sheet', 'Criteria 1 Status', 'Criteria 2 Status',
            'Melanoma Diagnosis History',
        ], escape: '\\');
        foreach ($report->patients as $patient) {
            foreach ($patient->encounters as $encounter) {
                fputcsv($output, [
                    $patient->pid, $patient->lname, $patient->fname, $patient->mname, $patient->sex, $patient->dob,
                    $encounter->age, $encounter->date, $encounter->encounter, $encounter->visitCodes,
                    implode('; ', $encounter->measureCodes), $patient->criteria1Status, $patient->criteria2Status,
                    $patient->dxHistory,
                ], escape: '\\');
            }
        }
    } else {
        fputcsv($output, [
            'Patient ID', 'Last Name', 'First Name', 'Date of Birth', 'Qualifying Visits ' . $year,
            'Excision CPTs (Encounter Date)', 'Criteria 1 Codes (M1388/M1392/M1390)', 'Criteria 1 Status',
            'Criteria 2 Codes (M1391/M1392/M1393)', 'Criteria 2 Status',
        ], escape: '\\');
        foreach ($report->patients as $patient) {
            fputcsv($output, [
                $patient->pid, $patient->lname, $patient->fname, $patient->dob, count($patient->encounters),
                implode('; ', $patient->excisions), implode('; ', $patient->criteria1Codes), $patient->criteria1Status,
                implode('; ', $patient->criteria2Codes), $patient->criteria2Status,
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
echo $twig->render('@cmsvt/mips_509_report.html.twig', [
    'report' => $report,
    'measure' => $measure,
    'years' => range($currentYear, 2020),
    'measureCodes' => Mips509Measure::MEASURE_CODES,
    'excisionCodes' => Mips509Measure::EXCISION_CODES,
    'lookbackYears' => Mips509Measure::LOOKBACK_YEARS,
]);
