<?php
/**
 * Generate a Healthmonix registry upload spreadsheet for any MIPS measure
 * from a supplied list of patient IDs (pubpid).
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    stephen waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2025 stephen waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

if (!getenv('OPENEMR_ENABLE_HMX_EXPORT')) {
    die('Set OPENEMR_ENABLE_HMX_EXPORT=1 environment variable to enable this script' . PHP_EOL);
}

if (php_sapi_name() !== 'cli') {
    echo "Only php cli can execute this script\n";
    echo "example use: php hmx_export.php default 2025 G4827 pids.txt\n";
    die;
}

if (!isset($argv[4])) {
    throw new RuntimeException(
        "Usage: php hmx_export.php <site> <year> <numerator_code> <pid_file>\n" .
        "  site:           OpenEMR site ID (e.g. default)\n" .
        "  year:           Performance period year (e.g. 2025)\n" .
        "  numerator_code: CPT II performance-met code (e.g. G4827)\n" .
        "  pid_file:       Path to text file with one pubpid per line\n"
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
$year           = (int) $argv[2];
$numerator_code = strtoupper(trim($argv[3]));
$pid_file       = $argv[4];
$start_date     = $year . '-01-01';
$end_date       = $year . '-12-31';

$output_dir  = OEGlobalsBag::getInstance()->getString('temporary_files_dir');
$output_file = 'HMX_' . $numerator_code . '_' . $year . '.xlsx';
$output_path = rtrim($output_dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $output_file;

// ── Load PIDs from file ───────────────────────────────────────────────────────
if (!file_exists($pid_file)) {
    throw new RuntimeException("PID file not found: $pid_file\n");
}

$pids = array_filter(
    array_map('trim', file($pid_file)),
    fn($line) => $line !== '' && $line[0] !== '#'
);

if (empty($pids)) {
    throw new RuntimeException("No PIDs found in $pid_file\n");
}

$placeholders = implode(',', array_fill(0, count($pids), '?'));
echo "Loaded " . count($pids) . " PIDs from $pid_file\n";

// ── Query ─────────────────────────────────────────────────────────────────────
$sql = "
    SELECT
        p.pubpid                                                           AS patient_id,
        p.lname                                                            AS last_name,
        p.fname                                                            AS first_name,
        DATE_FORMAT(p.DOB, '%m/%d/%Y')                                    AS dob,
        p.sex                                                              AS sex,
        DATE_FORMAT(fe.date, '%m/%d/%Y')                                  AS dos,
        MIN(CASE WHEN b.code_type = 'CPT4' THEN b.code END)              AS cpt,
        TRIM(MIN(CASE WHEN b.code_type = 'ICD10' THEN b.code END))       AS icd10
    FROM patient_data p
    JOIN form_encounter fe ON fe.pid = p.pid
    JOIN billing b
        ON  b.encounter = fe.encounter
        AND b.pid       = fe.pid
        AND b.activity  = 1
    WHERE
        fe.date BETWEEN ? AND ?
        AND p.pubpid IN ($placeholders)
    GROUP BY
        fe.encounter, p.pubpid, p.lname, p.fname, p.DOB, p.sex, fe.date
    ORDER BY
        fe.date, p.lname, p.fname
";

echo "Querying encounters for $year...\n";
$result = sqlStatement($sql, array_merge([$start_date, $end_date], array_values($pids)));

$rows = [];
while ($row = sqlFetchArray($result)) {
    $rows[] = $row;
}
echo "  → " . count($rows) . " encounters found\n";

if (empty($rows)) {
    echo "No encounters found for the supplied PIDs in $year.\n";
    exit(0);
}

// Warn on missing ICD-10
$missing_dx = array_filter($rows, fn($r) => empty($r['icd10']));
if (!empty($missing_dx)) {
    echo "  ⚠  " . count($missing_dx) . " encounters have no ICD-10 — review before upload\n";
}

// ── Normalize sex ─────────────────────────────────────────────────────────────
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
$ws->setTitle($numerator_code);

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

foreach ($columns as $col => [$label, $width]) {
    $ws->setCellValue($col . '1', $label);
    $ws->getStyle($col . '1')->applyFromArray($header_style);
    $ws->getColumnDimension($col)->setWidth($width);
}
$ws->getRowDimension(1)->setRowHeight(20);

foreach ($rows as $i => $r) {
    $row = $i + 2;

    $values = [
        'A' => $r['patient_id'],
        'B' => $r['last_name'],
        'C' => $r['first_name'],
        'D' => $r['dob'],
        'E' => normalizeSex($r['sex']),
        'F' => $r['dos'],
        'G' => $r['cpt'],
        'H' => $r['icd10'],
        'I' => $numerator_code,
    ];

    foreach ($values as $col => $val) {
        $ws->setCellValue($col . $row, $val);
        $ws->getStyle($col . $row)->applyFromArray($data_style);
    }

    $ws->getStyle('B' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);
    $ws->getStyle('C' . $row)->getAlignment()->setHorizontal(Alignment::HORIZONTAL_LEFT);

    if ($row % 2 === 0) {
        $ws->getStyle('A' . $row . ':I' . $row)->applyFromArray([
            'fill' => [
                'fillType'   => Fill::FILL_SOLID,
                'startColor' => ['argb' => 'FFEBF3FB'],
            ],
        ]);
    }
}

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

echo "  → Saved: $output_path\n";
