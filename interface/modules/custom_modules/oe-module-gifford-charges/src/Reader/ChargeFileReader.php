<?php

/**
 * Reads a Gifford charge file (.xlsx or .csv) into raw rows keyed by column name.
 *
 * The first row is the header. Column names are matched case-insensitively and
 * the columns may come in any order; extra columns are kept and ignored later.
 * Fully blank rows are skipped. Values are returned as trimmed strings: an
 * .xlsx number cell becomes its plain decimal form ("25917", not "25917.0"), so
 * Excel serial dates reach the parser unchanged.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Reader;

use League\Csv\Reader as CsvReader;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

final class ChargeFileReader
{
    /**
     * Columns every file must have. chg_pos, chg_hcpcs, the modifiers and the
     * insurance columns are optional: Gifford is still adding POS, and a row
     * may carry fewer than three policies.
     */
    public const REQUIRED_COLUMNS = [
        'encounter_num',
        'pat_mrn',
        'pat_last',
        'pat_first',
        'pat_dob',
        'pat_sex',
        'enc_dx_codes',
        'prov_rend_npi',
        'chg_dos',
        'chg_cpt',
        'chg_units',
        'chg_accession',
        'chg_location',
    ];

    /**
     * @return list<RawRow>
     */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new ChargeFileException('Charge file not found or not readable');
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $table = match ($extension) {
            'xlsx' => $this->xlsxTable($path),
            'csv' => $this->csvTable($path),
            default => throw new ChargeFileException('Charge file must be .xlsx or .csv'),
        };

        if ($table === []) {
            throw new ChargeFileException('Charge file is empty');
        }

        $header = array_map(
            static fn(string $name): string => strtolower(trim($name)),
            array_shift($table)
        );
        $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
        if ($missing !== []) {
            throw new ChargeFileException('Charge file is missing columns: ' . implode(', ', $missing));
        }

        $rows = [];
        foreach ($table as $index => $cells) {
            $values = [];
            foreach ($header as $position => $column) {
                if ($column === '') {
                    continue;
                }
                $values[$column] = trim($cells[$position] ?? '');
            }
            if (implode('', $values) === '') {
                continue;
            }
            // Line 1 is the header, so the first data row is line 2.
            $rows[] = new RawRow($index + 2, $values);
        }

        return $rows;
    }

    /**
     * @return list<list<string>>
     */
    private function xlsxTable(string $path): array
    {
        $reader = new Xlsx();
        $reader->setReadDataOnly(true);
        try {
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable $e) {
            throw new ChargeFileException('Charge file could not be read as .xlsx', 0, $e);
        }

        $table = [];
        foreach ($sheet->toArray(null, false, false, false) as $cells) {
            $table[] = array_map(self::cellText(...), array_values($cells));
        }
        return $table;
    }

    /**
     * @return list<list<string>>
     */
    private function csvTable(string $path): array
    {
        try {
            $reader = CsvReader::from($path, 'r');
            $table = [];
            foreach ($reader->getRecords() as $record) {
                $table[] = array_map(self::cellText(...), array_values($record));
            }
        } catch (\Throwable $e) {
            throw new ChargeFileException('Charge file could not be read as .csv', 0, $e);
        }
        return $table;
    }

    private static function cellText(mixed $value): string
    {
        return match (true) {
            is_string($value) => $value,
            is_int($value) => (string) $value,
            is_float($value) => floor($value) === $value ? sprintf('%.0f', $value) : (string) $value,
            is_bool($value) => $value ? '1' : '0',
            default => '',
        };
    }
}
