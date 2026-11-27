<?php

/**
 * Reads the NCCI edit file unloaded from the billing system's CCIFILE: one edit per line,
 * the column 1 code (5 characters), the column 2 code (5), and the modifier indicator
 * (0, 1 or 9), with nothing between them ("7350172170" + "1").
 *
 * @package   OpenEMR
 * @link      https://www.open-emr.org
 * @author    Stephen Waite <stephen.waite@cmsvt.com>
 * @copyright Copyright (c) 2026 Stephen Waite <stephen.waite@cmsvt.com>
 * @license   https://github.com/openemr/openemr/blob/master/LICENSE GNU General Public License 3
 */

declare(strict_types=1);

namespace Cmsvt\OpenEMR\Modules\GiffordCharges\Cci;

final class CciFileReader
{
    /** How many bad lines the exception names before it stops listing them. */
    private const MAX_REPORTED = 5;

    /**
     * @return list<CciEdit> in file order; a pair given twice keeps its last line
     */
    public function read(string $path): array
    {
        if (!is_file($path) || !is_readable($path)) {
            throw new CciFileException('NCCI edit file not found or not readable');
        }
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new CciFileException('NCCI edit file could not be read');
        }

        $edits = [];
        $bad = [];
        foreach ($lines as $index => $line) {
            $line = rtrim($line);
            if ($line === '') {
                continue;
            }
            if (preg_match('/^([0-9A-Z]{5})([0-9A-Z]{5})([019])$/', $line, $m) !== 1) {
                $bad[] = $index + 1;
                continue;
            }
            $edits[$m[1] . $m[2]] = new CciEdit($m[1], $m[2], CciIndicator::from($m[3]));
        }
        if ($bad !== []) {
            throw new CciFileException(sprintf(
                'NCCI edit file has %d line(s) that are not two 5-character codes and an indicator of 0, 1 or 9: line %s%s',
                count($bad),
                implode(', ', array_slice($bad, 0, self::MAX_REPORTED)),
                count($bad) > self::MAX_REPORTED ? ' ...' : ''
            ));
        }
        if ($edits === []) {
            throw new CciFileException('NCCI edit file is empty');
        }

        return array_values($edits);
    }
}
