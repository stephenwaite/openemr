<?php

/**
 * Reads a CMS fee file: fixed-width records laid out by the COBOL copybook PROCFILE01.
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\Customizations\Fees;

/**
 * PROCFILE01, one 46-character record per line:
 *
 *     02 PROC-KEY.
 *         03 PROC-CDM    PIC X(4).     columns  1-4
 *         03 PROC-CPT    PIC X(5).     columns  5-9
 *         03 PROC-MOD    PIC X(2).     columns 10-11
 *     02 PROC-TYPE       PIC X.        column  12
 *     02 PROC-TITLE      PIC X(28).    columns 13-40
 *     02 PROC-AMOUNT     PIC 9(4)V99.  columns 41-46, two implied decimals
 */
final class ProcFileParser
{
    public const RECORD_LENGTH = 46;

    /**
     * @param iterable<string> $lines the file's lines, with or without line endings
     */
    public function parse(iterable $lines): ProcFileParseResult
    {
        $records = [];
        $errors = [];
        $lineNumber = 0;
        foreach ($lines as $line) {
            $lineNumber++;
            $line = rtrim($line, "\r\n");
            if (trim($line) === '') {
                continue;
            }
            try {
                $records[] = $this->parseLine($line, $lineNumber);
            } catch (ProcFileException $e) {
                $errors[] = $e->getMessage();
            }
        }

        return new ProcFileParseResult($records, $errors);
    }

    /**
     * @throws ProcFileException when the line is not a PROCFILE01 record
     */
    public function parseLine(string $line, int $lineNumber): ProcFileRecord
    {
        if (strlen($line) !== self::RECORD_LENGTH) {
            throw new ProcFileException(sprintf(
                'line %d: %d characters, a record has %d',
                $lineNumber,
                strlen($line),
                self::RECORD_LENGTH
            ));
        }
        $amount = substr($line, 40, 6);
        if (!ctype_digit($amount)) {
            throw new ProcFileException(sprintf("line %d: amount '%s' is not 6 digits", $lineNumber, $amount));
        }

        return new ProcFileRecord(
            $lineNumber,
            substr($line, 0, 4),
            trim(substr($line, 4, 5)),
            trim(substr($line, 9, 2)),
            substr($line, 11, 1),
            trim(substr($line, 12, 28)),
            (int) $amount,
        );
    }
}
