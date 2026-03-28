<?php
/**
 * Export MIPS Measure 130 (Current Medications in the Medical Record)
 * to a Healthmonix registry upload spreadsheet.
 *
 * Numerator G4827 is attested for all qualifying encounters.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

// Enable this script via environment variable
if (!getenv('OPENEMR_ENABLE_MEASURE130_EXPORT')) {
    die('Set OPENEMR_ENABLE_MEASURE130_EXPORT=1 environment variable to enable this script' . PHP_EOL);
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute this script\n";
    echo "example use: php measure130_export.php default 2025 Measure130_Healthmonix_2025.xlsx\n";
    die;
}

if (!isset($argv[2])) {
    throw new RuntimeException(
        "Usage: php measure130_export.php <site> <year> [output_filename.xlsx]\n" .
        "  site:   OpenEMR site ID (e.g. default)\n" .
        "  year:   Performance period year (e.g. 2025)\n" .
        "  output: Optional filename (default: Measure130_Healthmonix_<year>.xlsx)\n"
    );
}

$_GET['site'] = $argv[1];
$ignoreAuth   = true;

require_once __DIR__ . "/../../../interface/globals.php";

use OpenEMR\Core\OEGlobalsBag;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;

// ── Arguments ─────────────────────────────────────────────────────────────────
$year       = (int) $argv[2];
$start_date = $year . '-01-01';
$end_date   = $year . '-12-31';

$output_dir  = OEGlobalsBag::getInstance()->getString('temporary_files_dir');
$output_file = $argv[3] ?? ('Measure130_Healthmonix_' . $year . '.xlsx');
$output_path = rtrim($output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $output_file;

// ── Qualifying E&M CPT codes for Measure 130 ─────────────────────────────────
$em_codes = implode("','", [
    '99202','99203','99204','99205',          // new patient office
    '99211','99212','99213','99214','99215',  // established patient office
    '99221','99222','99223',                  // initial hospital care
    '99231','99232','99233',                  // subsequent hospital care
    '99241','99242','99243','99244','99245',  // outpatient consult
]);

// ── Query ─────────────────────────────────────────────────────────────────────
$sql = "
    SELECT
        p.pubpid                                                           AS patient_id,
        p.lname                                                            AS last_name,
        p.fname                                                            AS first_name,
        DATE_FORMAT(p.DOB, '%m/%d/%Y')                                    AS dob,
        p.sex                                                              AS sex,
        DATE_FORMAT(fe.date, '%m/%d/%Y')                                  AS dos,
        MIN(CASE WHEN b_em.code IS NOT NULL THEN b_em.code END)           AS cpt,
        TRIM(MIN(CASE WHEN b_dx.code_type = 'ICD10' THEN b_dx.code END)) AS icd10,
        'G4827'                                                            AS numerator
    FROM form_encounter fe
    JOIN patient_data p ON p.pid = fe.pid
    JOIN billing b_em
        ON  b_em.encounter = fe.encounter
        AND b_em.pid       = fe.pid
        AND b_em.activity  = 1
        AND b_em.code_type = 'CPT4'
        AND b_em.code IN ('$em_codes')
    LEFT JOIN billing b_dx
        ON  b_dx.encounter = fe.encounter
        AND b_dx.pid       = fe.pid
        AND b_dx.activity  = 1
        AND b_dx.code_type = 'ICD10'
    WHERE
        fe.date BETWEEN ? AND ?
    GROUP BY
        fe.encounter, p.pubpid, p.lname, p.fname, p.DOB, p.sex, fe.date
    ORDER BY
        fe.date, p.lname, p.fname
";

echo "Querying Measure 130 encounters for $year...\n";
$result = sqlStatement($sql, [$start_date, $end_date]);

$rows = [];
while ($row = sqlFetchArray($result)) {
    $rows[] = $row;
}
echo "  → " . count($rows) . " qualifying encounters found\n";

if (empty($rows)) {
    echo "No data returned. Check that $year encounters exist in form_encounter.\n";
    exit(0);
}

$missing_dx = array_filter($rows, fn($r) => empty($r['icd10']));
if (!empty($missing_dx)) {
    echo "  ⚠  " . count($missing_dx) . " encounters have no ICD-10 — review before upload\n";
}

// ── Normalize sex field to M / F ─────────────────────────────────────────────
function normalizeSex(?string $raw): string
{
    $r = strtoupper(trim($raw ?? ''));
    if ($r === 'M' || $r === 'MALE')   return 'M';
    if ($r === 'F' || $r === 'FEMALE') return 'F';
    return $r;
}

// ── Build spreadsheet ─────────────────────────────────────────────────────────
echo "Building spreadsheet...\n";

$spreadsheet = new Spreadsheet();
$ws          = $spreadsheet->getActiveSheet();
$ws->setTitle('Measure 130');

// [header label, column width]
$columns = [
    'A' => ['Patient ID',  12],
    'B' => ['Last Name',   20],
    'C' => ['First Name',  16],
    'D' => ['DOB',         14],
    'E' => ['Sex',          6],
    'F' => ['DOS',         14],
    'G' => ['CPT',          9],
    'H' => ['ICD10',       12],
    'I' => ['Numerator',   12],
];

$header_style = [
    'font' => [
        'name'  => 'Arial',
        'bold'  => true,
        'size'  => 10,
        'color' => ['argb' => 'FFFFFFFF'],
    ],
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FF1F4E79'],
    ],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['argb' => 'FFD9D9D9'],
        ],
    ],
];

$data_style = [
    'font'      => ['name' => 'Arial', 'size' => 10],
    'alignment' => [
        'horizontal' => Alignment::HORIZONTAL_CENTER,
        'vertical'   => Alignment::VERTICAL_CENTER,
    ],
    'borders' => [
        'allBorders' => [
            'borderStyle' => Border::BORDER_THIN,
            'color'       => ['argb' => 'FFD9D9D9'],
        ],
    ],
];

$alt_fill = [
    'fill' => [
        'fillType'   => Fill::FILL_SOLID,
        'startColor' => ['argb' => 'FFEBF3FB'],
    ],
];

// Headers
foreach ($columns as $col => [$label, $width]) {
    $ws->setCellValue($col . '1', $label);
    $ws->getStyle($col . '1')->applyFromArray($header_style);
    $ws->getColumnDimension($col)->setWidth($width);
}
$ws->getRowDimension(1)->setRowHeight(20);

// Data rows
foreach ($rows as $i => $r) {
    $row    = $i + 2;
    $values = [
        'A' => $r['patient_id'],
        'B' => $r['last_name'],
        'C' => $r['first_name'],
        'D' => $r['dob'],
        'E' => normalizeSex($r['sex']),
        'F' => $r['dos'],
        'G' => $r['cpt'],
        'H' => $r['icd10'],
        'I' => $r['numerator'],
    ];

    foreach ($values as $col => $val) {
        $ws->setCellValue($col . $row, $val);
        $ws->getStyle($col . $row)->applyFromArray($data_style);
    }

    // Left-align name columns
    $ws->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    // Alternate row shading
    if ($row % 2 === 0) {
        $ws->getStyle('A' . $row . ':I' . $row)->applyFromArray($alt_fill);
    }
}

// Summary row
$summary_row = count($rows) + 3;
$ws->setCellValue('A' . $summary_row, 'Total encounters: ' . count($rows));
$ws->getStyle('A' . $summary_row)->getFont()->setBold(true)->setName('Arial')->setSize(10);
$ws->setCellValue('F' . $summary_row, 'Generated: ' . date('m/d/Y H:i'));
$ws->getStyle('F' . $summary_row)->getFont()->setItalic(true)->setSize(9)
   ->getColor()->setARGB('FF666666');

$ws->freezePane('A2');

// ── Save ──────────────────────────────────────────────────────────────────────
$writer = new Xlsx($spreadsheet);
$writer->save($output_path);

echo "  → Saved: $output_path (" . count($rows) . " data rows)\n";
