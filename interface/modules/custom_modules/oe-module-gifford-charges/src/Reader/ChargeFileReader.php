<?php

/**
 * Reads a Gifford charge file (.xlsx, .csv, or tab-separated .txt) into raw rows keyed
 * by column name.
 *
 * The first row is normally the header. Column names are matched case-insensitively and
 * the columns may come in any order; extra columns are kept and ignored later. A file
 * whose first row names none of the required columns is read without a header, in the
 * sample's column order (SAMPLE_LAYOUT), as Gifford's live export comes, but only when
 * every row has exactly that many fields. A .txt file is UTF-8, or UTF-16 with a
 * byte-order mark. Fully blank rows are skipped. Values are returned as trimmed strings:
 * an .xlsx number cell becomes its plain decimal form ("25917", not "25917.0"), so Excel
 * serial dates reach the parser unchanged.
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

    /** The columns of Gifford's sample (RAD_SAMPLE_20260915.xlsx), in order: a file without a header. */
    public const SAMPLE_LAYOUT = [
        'encounter_num', 'pat_mrn', 'pat_last', 'pat_first', 'pat_addr1', 'pat_addr2', 'pat_city', 'pat_state',
        'pat_zip', 'pat_phone', 'pat_email', 'pat_sex', 'pat_dob', 'enc_admit_date', 'enc_admit_time',
        'enc_discharge_date', 'enc_workcomp', 'enc_accident_date', 'enc_accident_time', 'enc_auth_num',
        'enc_ref_ind', 'enc_dx_codes', 'prov_ref_name', 'prov_ref_npi', 'prov_rend_name', 'prov_rend_npi',
        'chg_dos', 'chg_cpt', 'chg_hcpcs', 'chg_mod1', 'chg_mod2', 'chg_mod3', 'chg_units', 'chg_pos',
        'chg_clinical', 'chg_accession', 'chg_location', 'chg_dept', 'chg_comment', 'guar_last', 'guar_first',
        'guar_addr1', 'guar_addr2', 'guar_city', 'guar_state', 'guar_zip', 'guar_ssn', 'emp_name', 'emp_addr1',
        'emp_addr2', 'emp_city', 'emp_state', 'emp_zip', 'ins1_rank', 'ins1_mnemonic', 'ins1_payer_name',
        'ins1_payer_addr1', 'ins1_payer_addr2', 'ins1_payer_city', 'ins1_payer_state', 'ins1_payer_zip',
        'ins1_payer_phone', 'ins1_payer_contact', 'ins1_member_id', 'ins1_cert_num', 'ins1_group_num',
        'ins1_group_name', 'ins1_sub_name', 'ins1_sub_dob', 'ins1_sub_gender', 'ins1_sub_ssn',
        'ins1_sub_employer', 'ins1_relate', 'ins2_rank', 'ins2_mnemonic', 'ins2_payer_name', 'ins2_payer_addr1',
        'ins2_payer_addr2', 'ins2_payer_city', 'ins2_payer_state', 'ins2_payer_zip', 'ins2_payer_phone',
        'ins2_payer_contact', 'ins2_member_id', 'ins2_cert_num', 'ins2_group_num', 'ins2_group_name',
        'ins2_sub_name', 'ins2_sub_dob', 'ins2_sub_gender', 'ins2_sub_ssn', 'ins2_sub_employer', 'ins2_relate',
        'ins3_rank', 'ins3_mnemonic', 'ins3_payer_name', 'ins3_payer_addr1', 'ins3_payer_addr2',
        'ins3_payer_city', 'ins3_payer_state', 'ins3_payer_zip', 'ins3_payer_phone', 'ins3_payer_contact',
        'ins3_member_id', 'ins3_cert_num', 'ins3_group_num', 'ins3_group_name', 'ins3_sub_name', 'ins3_sub_dob',
        'ins3_sub_gender', 'ins3_sub_ssn', 'ins3_sub_employer', 'ins3_relate',
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
            'txt' => $this->textTable($path),
            default => throw new ChargeFileException('Charge file must be .xlsx, .csv or .txt'),
        };

        if ($table === []) {
            throw new ChargeFileException('Charge file is empty');
        }

        $firstRow = array_map(
            static fn(string $name): string => strtolower(trim($name)),
            $table[0]
        );
        if (array_intersect(self::REQUIRED_COLUMNS, $firstRow) === []) {
            $header = self::SAMPLE_LAYOUT;
            // No header: the first data row is line 1.
            $firstLine = 1;
            foreach ($table as $index => $cells) {
                if (count($cells) !== count($header) && trim(implode('', $cells)) !== '') {
                    throw new ChargeFileException(sprintf(
                        'Charge file has no header line and line %d has %d fields, not the sample layout\'s %d',
                        $index + 1,
                        count($cells),
                        count($header)
                    ));
                }
            }
        } else {
            $header = $firstRow;
            array_shift($table);
            // Line 1 is the header, so the first data row is line 2.
            $firstLine = 2;
            $missing = array_values(array_diff(self::REQUIRED_COLUMNS, $header));
            if ($missing !== []) {
                throw new ChargeFileException('Charge file is missing columns: ' . implode(', ', $missing));
            }
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
            $rows[] = new RawRow($index + $firstLine, $values);
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

    /**
     * A tab-separated text file, UTF-8 or UTF-16 by its byte-order mark. Fields are split
     * on tabs only: a quote is part of the value.
     *
     * @return list<list<string>>
     */
    private function textTable(string $path): array
    {
        $bytes = file_get_contents($path);
        if ($bytes === false) {
            throw new ChargeFileException('Charge file could not be read');
        }
        $text = match (true) {
            str_starts_with($bytes, "\xFF\xFE") => mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16LE'),
            str_starts_with($bytes, "\xFE\xFF") => mb_convert_encoding(substr($bytes, 2), 'UTF-8', 'UTF-16BE'),
            str_starts_with($bytes, "\xEF\xBB\xBF") => substr($bytes, 3),
            default => $bytes,
        };
        if (!mb_check_encoding($text, 'UTF-8')) {
            throw new ChargeFileException('Charge file is not UTF-8, or UTF-16 with a byte-order mark');
        }

        $table = [];
        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $line) {
            $table[] = explode("\t", $line);
        }
        // The newline ending the last line leaves an empty one.
        if ($table !== [] && $table[array_key_last($table)] === ['']) {
            array_pop($table);
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
