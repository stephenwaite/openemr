<?php

/**
 * Converts Gifford charge file values to OpenEMR's forms.
 *
 * Each method returns null (or an empty result) for a value it cannot use, so
 * the parser can report the column instead of guessing.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Parse;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

final class GiffordValues
{
    /**
     * Serial numbers above this are not plausible dates (it is 2173-10-14);
     * a larger number is an MRN or an account number in the wrong column.
     */
    private const MAX_EXCEL_SERIAL = 100000;

    /**
     * A date given as mm/dd/yyyy, yyyy-mm-dd, or an Excel serial day number,
     * which is how a date cell reaches us when Excel stored it as a number
     * (the same column can hold both forms).
     */
    public static function date(string $value): ?\DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }

        if (ctype_digit($value)) {
            $serial = (int) $value;
            if ($serial < 1 || $serial > self::MAX_EXCEL_SERIAL) {
                return null;
            }
            $date = ExcelDate::excelToDateTimeObject($serial);
            return \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        }

        // Four-digit years only: createFromFormat() would read 08/25/26 as year 26.
        $formats = [
            '!m/d/Y' => '#^\d{1,2}/\d{1,2}/\d{4}$#',
            '!Y-m-d' => '#^\d{4}-\d{2}-\d{2}$#',
        ];
        foreach ($formats as $format => $shape) {
            if (preg_match($shape, $value) !== 1) {
                continue;
            }
            $date = \DateTimeImmutable::createFromFormat($format, $value);
            $errors = \DateTimeImmutable::getLastErrors();
            if ($date !== false && ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0))) {
                return $date;
            }
        }

        return null;
    }

    /**
     * "Last, First Middle" as [last, first middle]. A name without a comma is
     * taken as the last name only.
     *
     * @return array{string, string}
     */
    public static function lastFirst(string $value): array
    {
        $parts = explode(',', $value, 2);
        return [trim($parts[0]), trim($parts[1] ?? '')];
    }

    /**
     * M or F as OpenEMR's sex list value; anything else is UNK.
     */
    public static function sex(string $value): string
    {
        return match (strtoupper($value)) {
            'M' => 'Male',
            'F' => 'Female',
            default => 'UNK',
        };
    }

    /**
     * The X12 individual relationship code (2000B SBR02 / 2000C PAT01) as
     * OpenEMR's subscriber relationship.
     */
    public static function relationship(string $value): string
    {
        return match ($value) {
            '18' => 'self',
            '01', '1' => 'spouse',
            '19' => 'child',
            default => 'other',
        };
    }

    /**
     * A place of service code as two digits ("5" becomes "05"), or null when
     * it is not a POS code.
     */
    public static function placeOfService(string $value): ?string
    {
        if (!ctype_digit($value) || strlen($value) > 2 || (int) $value < 1) {
            return null;
        }
        return str_pad($value, 2, '0', STR_PAD_LEFT);
    }

    /**
     * Comma-separated ICD-10-CM codes, upper-cased, in order, without repeats.
     * Codes that are not ICD-10-CM shaped are returned in the second list.
     *
     * @return array{list<string>, list<string>} [codes, rejected]
     */
    public static function diagnoses(string $value): array
    {
        $codes = [];
        $rejected = [];
        foreach (explode(',', $value) as $part) {
            $code = strtoupper(trim($part));
            if ($code === '') {
                continue;
            }
            if (preg_match('/^[A-Z][0-9][0-9A-Z](\.[0-9A-Z]{1,4})?$/', $code) !== 1) {
                $rejected[] = $code;
                continue;
            }
            if (!in_array($code, $codes, true)) {
                $codes[] = $code;
            }
        }
        return [$codes, $rejected];
    }
}
